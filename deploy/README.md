# Deploying to a DigitalOcean droplet (zero-touch)

The whole stack — self-hosted **Supabase** + the **PHP SSR app** + a **Tor**
hidden service — comes up on a fresh droplet **without you ever SSHing in**.

## 1. Create the droplet

- **Image:** Ubuntu 24.04 LTS
- **Size:** ≥ 4 GB RAM / 2 vCPU (the full Supabase stack is memory-hungry)
- **Advanced → Add Initialization scripts (user data):** paste the entire
  contents of [`deploy/cloud-init.sh`](cloud-init.sh).

That's it. On first boot the script (running as root, no login required):

1. installs Docker + Compose,
2. clones this repo to `/opt/uyunlist`,
3. runs `scripts/generate-env.sh` → a complete `.env` with random secrets and
   correctly-signed Supabase `ANON_KEY` / `SERVICE_ROLE_KEY` JWTs,
4. `docker compose up -d --build` (Supabase + app + Tor),
5. `scripts/apply-migrations.sh` → creates the app schema,
6. reads the generated **`.onion`** address, writes it into `APP_BASE_URL` /
   `SITE_URL`, and recreates the app.

Progress is logged to `/var/log/uyunlist-setup.log`. The onion address is
written to `/opt/uyunlist/ONION_ADDRESS.txt`.

> The repo must be reachable by `git clone`. If it's private, set `REPO_URL` at
> the top of the script to an authenticated URL (deploy token). The droplet
> itself still needs no SSH login.

## 2. Set the real payment config (before going live)

`generate-env.sh` fills everything except the values only you can provide.
Edit `/opt/uyunlist/.env` (via the DO web console or your first SSH) and set:

- `TATUM_API_KEY` — exchange rates (https://tatum.io)
- `PAYOUT_BTC` / `PAYOUT_XMR` / `PAYOUT_ETH` / `PAYOUT_SOL` / `PAYOUT_DOGE`

then `docker compose up -d app`.

## 3. Continuous deploys with Forgejo Actions

Setup is SSH-free; ongoing deploys use [Forgejo Actions](https://forgejo.org/docs/latest/user/actions/quick-start/)
(Codeberg CI). `.forgejo/workflows/`:

- **`ci.yml`** — on every push/PR: `php -l` the source, `composer install` from
  the lockfile, and validate the compose config + generated JWT keys.
- **`deploy.yml`** — on push to `master`: SSH to the droplet and
  `git pull && docker compose up -d --build && apply-migrations`. It **no-ops
  until you set the secrets**, so CI stays green in the meantime.

Set these repo secrets (Settings → Actions → Secrets) to enable CD:

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
