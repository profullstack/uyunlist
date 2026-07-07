# Deploying to a DigitalOcean droplet

The whole stack — self-hosted **Supabase** + the **PHP SSR app** + a **Tor**
hidden service — is stood up by one idempotent script,
[`deploy/provision.sh`](provision.sh). There are no manual steps on the box.

Everything runs through that one script, so all paths do the same thing and are
safe to re-run:

1. installs Docker + Compose and `git` (skipped if present),
2. creates the **`ubuntu`** sudo/docker user (copying root's SSH key) so
   `ubuntu@<host>` works,
3. **persists the Tor hidden-service keys on the attached block volume**
   (`/mnt/unyunvolume/tor`) so the **`.onion` address is permanent** — it
   survives `docker compose down`, a full rebuild, and even reattaching the
   volume to a new droplet,
4. clones this repo to `/opt/uyunlist` and runs `scripts/generate-env.sh` → a
   complete `.env` (random secrets + correctly-signed Supabase `ANON_KEY` /
   `SERVICE_ROLE_KEY` JWTs). **Idempotent — an existing `.env` is left alone.**
5. `docker compose up -d --build` (Supabase + app + Tor),
6. `scripts/apply-migrations.sh` → creates the app schema,
7. reads the generated **`.onion`**, writes it into `APP_BASE_URL` / `SITE_URL`,
   recreates the app.

Progress is logged to `/var/log/uyunlist-setup.log`; the onion address to
`/opt/uyunlist/ONION_ADDRESS.txt`.

## Option A — deploy from your workstation (recommended)

```bash
./deploy/setup.sh                          # → root@137.184.123.182 (default)
SSH_TARGET=root@1.2.3.4 ./deploy/setup.sh  # some other host
REPO_BRANCH=my-branch ./deploy/setup.sh    # deploy a specific branch
```

`setup.sh` SSHes in and runs `provision.sh` over the connection. The first run
must connect as root (or a passwordless sudoer) to install Docker + create the
user; afterwards `ubuntu@<host>` works too (`SSH_TARGET=ubuntu@<host>`).

## Option B — zero-touch on droplet creation (user-data)

- **Image:** Ubuntu 24.04 LTS
- **Size:** ≥ 4 GB RAM / 2 vCPU (the full Supabase stack is memory-hungry)
- **Attach a block-storage volume**, mounted at `/mnt/unyunvolume` (this is
  where the `.onion` keys are persisted).
- **Advanced → Add Initialization scripts (user data):** paste the entire
  contents of [`deploy/cloud-init.sh`](cloud-init.sh) — a thin bootstrap that
  fetches and runs `provision.sh`. No SSH login required.

> The repo must be reachable by `git clone`. If it's private, set `REPO_URL` at
> the top of the script to an authenticated URL (deploy token).

## Sizing / lean by default

The PHP app talks to Postgres **directly** and does not use the Supabase API
layer, so the stack runs lean by default — only **db + app + tor**. That fits a
**1 GB** droplet (2 GB comfortable); `provision.sh` also adds a 2 GB swapfile.

The full Supabase dashboard/API (Studio, Kong, GoTrue, PostgREST, Realtime,
Storage, imgproxy, postgres-meta, edge-functions) is gated behind a `full`
profile and is **not needed by this app**. Only run it on a **≥ 4 GB** box:

```bash
docker compose --profile full up -d
```

## Where persistent state lives (the block volume)

All precious state is bind-mounted onto the attached block volume (default
`/mnt/unyunvolume`) via [`deploy/docker-compose.volume.yml`](docker-compose.volume.yml),
so it survives `docker compose down`, a full rebuild, or reattaching the volume
to a new droplet:

| Volume | Host path | What |
|---|---|---|
| `tor_data` | `${TOR_DATA_DIR}` (`…/tor`) | `.onion` hidden-service keys (the address) |
| `db_data` | `${DB_DATA_DIR}` (`…/db`) | Postgres — listings, users, … |
| `uploads_data` | `${UPLOADS_DIR}` (`…/uploads`) | uploaded images (listings, avatars, thumbs) |
| `db_backups` | `${DB_BACKUPS_DIR}` (`…/backups`) | pg backups |

`provision.sh` creates those dirs and wires the override in by writing
`COMPOSE_FILE=docker-compose.yml:deploy/docker-compose.volume.yml` into `.env`,
so every `docker compose` command on the box uses it automatically. To rotate
the onion address, stop the stack and delete `/mnt/unyunvolume/tor/hidden_service`.

## 2. Set the real payment config (before going live)

`generate-env.sh` fills everything except the values only you can provide.
Edit `/opt/uyunlist/.env` (via the DO web console or your first SSH) and set:

- `TATUM_API_KEY` — exchange rates (https://tatum.io)
- `PAYOUT_BTC` / `PAYOUT_XMR` / `PAYOUT_ETH` / `PAYOUT_SOL` / `PAYOUT_DOGE`

then `docker compose up -d app`.

## Continuous deploys with GitHub Actions

Ongoing deploys use [GitHub Actions](https://docs.github.com/actions).
`.github/workflows/`:

- **`ci.yml`** — on every push/PR: `php -l` the source, `composer install` from
  the lockfile, and validate the compose config + generated JWT keys.
- **`deploy.yml`** — on push to `master`: SSH to the droplet and re-run the
  idempotent `deploy/provision.sh` (pull + rebuild + migrate). It **no-ops
  until you set the secrets**, so CI stays green in the meantime.

Set these repo secrets (Settings → Secrets and variables → Actions) to enable CD:

| Secret | Value |
|---|---|
| `DEPLOY_HOST` | droplet IP |
| `DEPLOY_USER` | ssh user (default `root`) |
| `DEPLOY_SSH_KEY` | a private key whose public half is in the droplet's `authorized_keys` (CD only — not needed for setup) |

## Local dev

```bash
./scripts/generate-env.sh      # writes .env (idempotent)
docker compose up -d --build
./scripts/apply-migrations.sh
docker compose exec tor cat /var/lib/tor/hidden_service/hostname   # your .onion
```

App: http://localhost:8080 · Supabase Studio: http://localhost:3000
(`DASHBOARD_USERNAME`/`DASHBOARD_PASSWORD` in `.env`).
