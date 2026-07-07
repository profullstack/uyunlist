#!/usr/bin/env bash
#
# uyunlist — one-command deploy from your workstation. Idempotent.
#
# It SSHes into the droplet and runs deploy/provision.sh there (piped over the
# connection, so the copy in *this* checkout is the source of truth). No manual
# steps on the box: it installs Docker, creates the `ubuntu` user, puts the Tor
# keys on the attached block volume, brings the stack up, and prints the .onion.
#
# Usage:
#   ./deploy/setup.sh                      # deploys to the default host below
#   SSH_TARGET=root@1.2.3.4 ./deploy/setup.sh
#   REPO_BRANCH=my-branch ./deploy/setup.sh
#
# The first run must connect as a user that can sudo without a password
# (root works out of the box). After it completes, `ubuntu@<host>` also works
# for subsequent runs — pass SSH_TARGET=ubuntu@<host>.
set -euo pipefail
cd "$(dirname "$0")/.."

# root is required for the very first provision (Docker install, user creation).
SSH_TARGET="${SSH_TARGET:-root@137.184.123.182}"
REPO_URL="${REPO_URL:-https://github.com/profullstack/uyunlist.git}"
REPO_BRANCH="${REPO_BRANCH:-master}"
VOLUME_DIR="${VOLUME_DIR:-/mnt/unyunvolume}"
SSH_OPTS="${SSH_OPTS:--o StrictHostKeyChecking=accept-new}"

echo "🚀 Deploying uyunlist to $SSH_TARGET (branch: $REPO_BRANCH)"

# If the target is not root, run the provisioner under sudo on the far side.
RUNNER="bash"
case "$SSH_TARGET" in
  root@*|root) : ;;
  *) RUNNER="sudo -E bash" ;;
esac

# Copy the provisioner over and run it as a FILE (not piped via stdin): the
# provisioner uses `docker compose exec`, which attaches stdin, so piping the
# script into `bash -s` would let docker consume the rest of it and corrupt the
# parse. Running from a file (with stdin from /dev/null) avoids that entirely.
REMOTE_SCRIPT="/tmp/uyunlist-provision.$$.sh"
# shellcheck disable=SC2086
scp $SSH_OPTS deploy/provision.sh "$SSH_TARGET:$REMOTE_SCRIPT"
# shellcheck disable=SC2086
ssh $SSH_OPTS "$SSH_TARGET" \
  "REPO_URL='$REPO_URL' REPO_BRANCH='$REPO_BRANCH' VOLUME_DIR='$VOLUME_DIR' $RUNNER $REMOTE_SCRIPT </dev/null; rc=\$?; rm -f $REMOTE_SCRIPT; exit \$rc"

echo "✅ Deploy finished. Onion address:"
# shellcheck disable=SC2086
ssh $SSH_OPTS "$SSH_TARGET" 'cat /opt/uyunlist/ONION_ADDRESS.txt 2>/dev/null || echo "  (pending — check: docker compose exec tor cat /var/lib/tor/hidden_service/hostname)"'
