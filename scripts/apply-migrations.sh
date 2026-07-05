#!/usr/bin/env bash
# Apply the application's SQL migrations to the running Supabase Postgres.
#
# The supabase/postgres init only runs files directly in its migrations dir
# (not the mounted `custom/` subdir), so app schema is applied here, after the
# DB is healthy. Runs every file in supabase/migrations/ in sorted order; safe
# to re-run once the schema exists (errors on the first, already-applied file
# are reported but non-fatal on repeat runs — first boot is the happy path).
set -uo pipefail
cd "$(dirname "$0")/.."

COMPOSE="docker compose"
command -v docker-compose >/dev/null 2>&1 && COMPOSE="docker-compose"

echo "⏳ Waiting for database to be healthy…"
for _ in $(seq 1 60); do
  if $COMPOSE exec -T db pg_isready -U postgres >/dev/null 2>&1; then break; fi
  sleep 3
done

# Skip if the app schema is already present (idempotent first-boot guard).
have=$($COMPOSE exec -T db psql -U postgres -tAc \
  "SELECT to_regclass('public.users') IS NOT NULL;" 2>/dev/null | tr -d '[:space:]')
if [ "$have" = "t" ]; then
  echo "✅ App schema already present — skipping migrations."
  exit 0
fi

echo "🗄️  Applying app migrations…"
for f in supabase/migrations/*.sql; do
  [ -e "$f" ] || continue
  echo "   → $(basename "$f")"
  if ! $COMPOSE exec -T db psql -U postgres -v ON_ERROR_STOP=1 -q < "$f"; then
    echo "❌ Migration failed: $f" >&2
    exit 1
  fi
done

count=$($COMPOSE exec -T db psql -U postgres -tAc \
  "SELECT count(*) FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE';" | tr -d '[:space:]')
echo "✅ Migrations applied — ${count} public tables."
