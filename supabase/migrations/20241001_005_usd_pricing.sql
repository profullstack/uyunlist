-- 005: USD-based listing pricing with a cached crypto amount.
--
-- Sellers enter a USD price and pick a coin (one they have a wallet for). The
-- crypto amount is computed server-side and cached here, then refreshed for
-- active listings by an hourly cron (see scripts/refresh-prices via
-- /cron/refresh-prices). Idempotent.

ALTER TABLE listings ADD COLUMN IF NOT EXISTS price_usd_cents       INTEGER NOT NULL DEFAULT 0;
ALTER TABLE listings ADD COLUMN IF NOT EXISTS price_currency        TEXT NOT NULL DEFAULT '';
ALTER TABLE listings ADD COLUMN IF NOT EXISTS price_crypto          NUMERIC NOT NULL DEFAULT 0;
ALTER TABLE listings ADD COLUMN IF NOT EXISTS price_rate_updated_at TIMESTAMPTZ;

-- Backfill: nothing to convert (legacy price_sats was raw BTC and unused going
-- forward). New/edited listings populate the USD fields.
