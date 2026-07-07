#!/usr/bin/env bash
#
# Onion Classifieds — unattended DigitalOcean droplet provisioning (user-data).
#
# Paste this whole file into the droplet's "user data" field when creating it
# (Create Droplet → Advanced → Add Initialization scripts). It runs once, as
# root, on first boot — NO SSH / login to the server is required.
#
# It is a thin bootstrap: it fetches deploy/provision.sh from the repo and runs
# it. provision.sh does all the real work (Docker, the `ubuntu` user, putting
# the Tor keys on the attached block volume, the stack, migrations, wiring the
# .onion) and is idempotent, so re-running is always safe.
#
# Recommended droplet: Ubuntu 24.04, >= 4 GB RAM (the full Supabase stack is
# memory-hungry), 2 vCPU, with a block-storage volume attached and mounted at
# /mnt/unyunvolume (that's where the .onion keys are persisted).
#
# Tunables (edit before pasting):
REPO_URL="${REPO_URL:-https://github.com/profullstack/uyunlist.git}"
REPO_BRANCH="${REPO_BRANCH:-master}"
VOLUME_DIR="${VOLUME_DIR:-/mnt/unyunvolume}"

set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

apt-get update -y
apt-get install -y curl ca-certificates

RAW="https://raw.githubusercontent.com/profullstack/uyunlist/${REPO_BRANCH}/deploy/provision.sh"
echo "Fetching provisioner: $RAW"
# Download then run as a FILE (not `curl | bash`): the provisioner uses
# `docker compose exec`, which attaches stdin and would otherwise consume the
# piped script and corrupt the parse.
curl -fsSL "$RAW" -o /tmp/uyunlist-provision.sh
REPO_URL="$REPO_URL" REPO_BRANCH="$REPO_BRANCH" VOLUME_DIR="$VOLUME_DIR" \
  bash /tmp/uyunlist-provision.sh </dev/null
