#!/usr/bin/env bash
#
# uyunlist — idempotent, unattended provisioning that runs ON the droplet.
#
# This is the single source of truth for standing the stack up. It is safe to
# re-run any number of times (installs are skipped if already present, the
# .onion keys are reused, no secret is regenerated). It is invoked by:
#   • deploy/setup.sh   — from your workstation, over SSH (recommended)
#   • deploy/cloud-init.sh — DigitalOcean user-data, zero-touch on first boot
#   • directly:  sudo REPO_BRANCH=master bash deploy/provision.sh
#
# What it does, in order:
#   1. install Docker (+ compose plugin) and git if missing
#   2. create the `ubuntu` sudo/docker user (so `ubuntu@host` works) if missing
#   3. put the Tor hidden-service keys on the attached block volume so the
#      .onion address is permanent (see deploy/docker-compose.volume.yml)
#   4. clone/update the repo, generate a complete .env (idempotent)
#   5. bring the whole stack up, apply DB migrations
#   6. read the generated .onion and wire it into APP_BASE_URL / SITE_URL
#
# Must run as root (or via sudo).
set -euo pipefail

# ── Tunables (override via env) ───────────────────────────────────────────────
REPO_URL="${REPO_URL:-https://github.com/profullstack/uyunlist.git}"
REPO_BRANCH="${REPO_BRANCH:-master}"
APP_DIR="${APP_DIR:-/opt/uyunlist}"
APP_USER="${APP_USER:-ubuntu}"
# The attached block-storage volume mount, and where on it the persistent state
# lives (onion keys, database, uploaded images, backups).
VOLUME_DIR="${VOLUME_DIR:-/mnt/unyunvolume}"
TOR_DATA_DIR="${TOR_DATA_DIR:-$VOLUME_DIR/tor}"
DB_DATA_DIR="${DB_DATA_DIR:-$VOLUME_DIR/db}"
UPLOADS_DIR="${UPLOADS_DIR:-$VOLUME_DIR/uploads}"
DB_BACKUPS_DIR="${DB_BACKUPS_DIR:-$VOLUME_DIR/backups}"

log() { echo "=== $* ==="; }

if [ "$(id -u)" -ne 0 ]; then
  echo "This script must run as root (use sudo)." >&2
  exit 1
fi

exec > >(tee -a /var/log/uyunlist-setup.log) 2>&1
log "uyunlist provisioning started $(date -u)"
export DEBIAN_FRONTEND=noninteractive

# ── 1) Base packages + Docker ────────────────────────────────────────────────
log "Installing base packages"
apt-get update -y
apt-get install -y git ca-certificates curl
if ! command -v docker >/dev/null 2>&1; then
  log "Installing Docker"
  curl -fsSL https://get.docker.com | sh
fi
systemctl enable --now docker

# ── 1b) Ensure some swap on small droplets (idempotent) ──────────────────────
# The lean stack (db + app + tor) fits in ~1 GB, but a little swap keeps a 1 GB
# box off the OOM-killer during image builds and Postgres init.
SWAPFILE="/swapfile"
if ! swapon --show=NAME --noheadings 2>/dev/null | grep -q "$SWAPFILE" && [ ! -f "$SWAPFILE" ]; then
  log "Creating 2G swapfile"
  if fallocate -l 2G "$SWAPFILE" 2>/dev/null || dd if=/dev/zero of="$SWAPFILE" bs=1M count=2048; then
    chmod 600 "$SWAPFILE"
    mkswap "$SWAPFILE" >/dev/null
    swapon "$SWAPFILE"
    grep -q "^$SWAPFILE " /etc/fstab || echo "$SWAPFILE none swap sw 0 0" >> /etc/fstab
  fi
fi

# ── 2) Create the ubuntu sudo/docker user (idempotent) ───────────────────────
# The base image only ships a root login; make `ubuntu@host` work by copying
# root's authorized_keys, and let it run docker + sudo without a password.
if ! id "$APP_USER" >/dev/null 2>&1; then
  log "Creating user '$APP_USER'"
  useradd --create-home --shell /bin/bash "$APP_USER"
fi
usermod -aG sudo,docker "$APP_USER"
echo "$APP_USER ALL=(ALL) NOPASSWD:ALL" > "/etc/sudoers.d/90-$APP_USER"
chmod 440 "/etc/sudoers.d/90-$APP_USER"
if [ -f /root/.ssh/authorized_keys ]; then
  install -d -m 700 -o "$APP_USER" -g "$APP_USER" "/home/$APP_USER/.ssh"
  install -m 600 -o "$APP_USER" -g "$APP_USER" \
    /root/.ssh/authorized_keys "/home/$APP_USER/.ssh/authorized_keys"
fi

# ── 3) Prepare the tor key directory on the block volume ─────────────────────
if ! mountpoint -q "$VOLUME_DIR"; then
  log "WARNING: $VOLUME_DIR is not a mounted volume"
  echo "  The .onion keys will be created under $TOR_DATA_DIR anyway, but they"
  echo "  will NOT be on persistent block storage. Attach the volume and mount"
  echo "  it at $VOLUME_DIR, then re-run, to make the address survive rebuilds."
fi
log "Persisting state on the volume: tor keys, database, uploads, backups"
mkdir -p "$TOR_DATA_DIR/hidden_service" "$DB_DATA_DIR" "$DB_BACKUPS_DIR" \
         "$UPLOADS_DIR"/avatars "$UPLOADS_DIR"/listings "$UPLOADS_DIR"/thumbnails
# The tor container (running as root) chowns these to the tor user on start;
# 700 on the key dir keeps tor happy in the meantime.
chmod 700 "$TOR_DATA_DIR/hidden_service"
# A bind mount shadows the image's baked-in uploads/ subdirs with an empty host
# dir, so create them here. The app runs as www-data (uid 33) and writes here.
chown -R 33:33 "$UPLOADS_DIR" 2>/dev/null || true

# ── 4) Fetch/update the code, generate .env ──────────────────────────────────
# We run git as root but the checkout is owned by $APP_USER — tell git that's
# fine (idempotent) so re-runs don't trip "dubious ownership".
git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true
if [ -d "$APP_DIR/.git" ]; then
  log "Updating repo in $APP_DIR ($REPO_BRANCH)"
  git -C "$APP_DIR" fetch --depth=1 origin "$REPO_BRANCH"
  git -C "$APP_DIR" checkout -B "$REPO_BRANCH" "origin/$REPO_BRANCH"
  git -C "$APP_DIR" reset --hard "origin/$REPO_BRANCH"
else
  log "Cloning repo into $APP_DIR ($REPO_BRANCH)"
  git clone --depth=1 --branch "$REPO_BRANCH" "$REPO_URL" "$APP_DIR"
fi
chown -R "$APP_USER:$APP_USER" "$APP_DIR"
cd "$APP_DIR"

log "Generating .env (idempotent)"
bash scripts/generate-env.sh   # no-op if .env already exists

# Pin the tor volume onto the block storage and make every compose command in
# this dir use the override automatically (docker compose reads these keys from
# .env). Both writes are idempotent.
set_env() {  # set_env KEY VALUE — replace or append KEY=VALUE in .env
  local k="$1" v="$2"
  if grep -q "^${k}=" .env; then
    sed -i "s|^${k}=.*|${k}=${v}|" .env
  else
    printf '%s=%s\n' "$k" "$v" >> .env
  fi
}
set_env TOR_DATA_DIR "$TOR_DATA_DIR"
set_env DB_DATA_DIR "$DB_DATA_DIR"
set_env UPLOADS_DIR "$UPLOADS_DIR"
set_env DB_BACKUPS_DIR "$DB_BACKUPS_DIR"
set_env COMPOSE_FILE "docker-compose.yml:deploy/docker-compose.volume.yml"

# ── 5) Bring up the stack + migrate ──────────────────────────────────────────
# Stop anything from a previous run first (incl. now-profiled Supabase API
# services and removed analytics/vector orphans) so we converge on the lean
# db+app+tor set. The .onion keys live on the volume, so tor's address is
# unchanged across the restart.
log "Bringing up the lean stack (db + app + tor)"
docker compose down --remove-orphans || true
docker compose up -d --build

log "Applying database migrations"
bash scripts/apply-migrations.sh || echo "WARN: migrations step reported an issue (see above)"

# ── 6) Wire the .onion address into the app ──────────────────────────────────
log "Waiting for the .onion hostname"
ONION=""
for _ in $(seq 1 60); do
  ONION="$(docker compose exec -T tor cat /var/lib/tor/hidden_service/hostname 2>/dev/null | tr -d '\r\n' || true)"
  [ -n "$ONION" ] && break
  sleep 3
done

if [ -n "$ONION" ]; then
  log "Onion address: http://$ONION"
  set_env APP_BASE_URL "http://$ONION"
  set_env SITE_URL "http://$ONION"
  docker compose up -d app
  echo "http://$ONION" > "$APP_DIR/ONION_ADDRESS.txt"
  chown "$APP_USER:$APP_USER" "$APP_DIR/ONION_ADDRESS.txt"
else
  echo "WARN: .onion not ready yet — it will appear once Tor bootstraps:"
  echo "  docker compose exec tor cat /var/lib/tor/hidden_service/hostname"
fi

log "uyunlist provisioning finished $(date -u)"
echo "Onion:      $(cat "$APP_DIR/ONION_ADDRESS.txt" 2>/dev/null || echo 'pending — check tor logs')"
echo "Keys:       $TOR_DATA_DIR/hidden_service (on the block volume)"
echo "Dashboard:  http://<droplet-ip>:3000 (creds in $APP_DIR/.env)"
echo "Setup log:  /var/log/uyunlist-setup.log"
