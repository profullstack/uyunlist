#!/usr/bin/env bash
#
# Onion Classifieds — unattended DigitalOcean droplet provisioning.
#
# Paste this whole file into the droplet's "user data" field when creating it
# (Create Droplet → Advanced → Add Initialization scripts). It runs once, as
# root, on first boot — NO SSH / login to the server is required. It installs
# Docker, fetches the code, generates all secrets + Supabase JWT keys, brings
# up the full stack (self-hosted Supabase + PHP app + Tor), applies the DB
# migrations, and wires the generated .onion address into the app.
#
# Recommended droplet: Ubuntu 24.04, >= 4 GB RAM (the full Supabase stack is
# memory-hungry), 2 vCPU.
#
# Tunables (edit before pasting, or set as env in a wrapping cloud-config):
REPO_URL="${REPO_URL:-https://codeberg.org/chovy/uyunlist.git}"
REPO_BRANCH="${REPO_BRANCH:-master}"
APP_DIR="${APP_DIR:-/opt/uyunlist}"

set -euo pipefail
exec > >(tee -a /var/log/uyunlist-setup.log) 2>&1
echo "=== uyunlist provisioning started $(date -u) ==="

export DEBIAN_FRONTEND=noninteractive

# 1) Base packages + Docker (official convenience script installs Engine +
#    Compose plugin + buildx).
apt-get update -y
apt-get install -y git ca-certificates curl
if ! command -v docker >/dev/null 2>&1; then
  curl -fsSL https://get.docker.com | sh
fi
systemctl enable --now docker

# 2) Fetch (or update) the code.
if [ -d "$APP_DIR/.git" ]; then
  git -C "$APP_DIR" fetch --depth=1 origin "$REPO_BRANCH"
  git -C "$APP_DIR" reset --hard "origin/$REPO_BRANCH"
else
  git clone --depth=1 --branch "$REPO_BRANCH" "$REPO_URL" "$APP_DIR"
fi
cd "$APP_DIR"

# 3) Generate a complete .env (secrets + Supabase ANON/SERVICE JWTs). Idempotent.
bash scripts/generate-env.sh

# 4) Bring up the whole stack (build the PHP image on first run).
docker compose up -d --build

# 5) Apply application DB migrations once the database is healthy.
bash scripts/apply-migrations.sh || echo "WARN: migrations step reported an issue (see above)"

# 6) Wait for Tor to publish the hidden service, then wire the .onion into the
#    app config and recreate the services that bake in the base URL.
echo "Waiting for the .onion hostname…"
ONION=""
for _ in $(seq 1 60); do
  ONION="$(docker compose exec -T tor cat /var/lib/tor/hidden_service/hostname 2>/dev/null | tr -d '\r\n' || true)"
  [ -n "$ONION" ] && break
  sleep 3
done

if [ -n "$ONION" ]; then
  echo "Onion address: http://$ONION"
  sed -i "s|^APP_BASE_URL=.*|APP_BASE_URL=http://$ONION|" .env
  sed -i "s|^SITE_URL=.*|SITE_URL=http://$ONION|" .env
  docker compose up -d app auth
  echo "http://$ONION" > "$APP_DIR/ONION_ADDRESS.txt"
else
  echo "WARN: .onion not ready yet — it will appear at $APP_DIR after Tor bootstraps."
  echo "  docker compose exec tor cat /var/lib/tor/hidden_service/hostname"
fi

echo "=== uyunlist provisioning finished $(date -u) ==="
echo "Onion:      $(cat "$APP_DIR/ONION_ADDRESS.txt" 2>/dev/null || echo 'pending — check tor logs')"
echo "Dashboard:  http://<droplet-ip>:3000 (creds in $APP_DIR/.env: DASHBOARD_USERNAME/PASSWORD)"
echo "Setup log:  /var/log/uyunlist-setup.log"
