-- 004: Per-user crypto wallet addresses + preferred payment currency.
--
-- Members can store their own receiving addresses (imported from a coinpay
-- wallet block or entered per-coin) and pick a preferred currency. Idempotent —
-- safe to run more than once and to apply by hand to an already-provisioned DB.

ALTER TABLE users ADD COLUMN IF NOT EXISTS wallet_btc  TEXT NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS wallet_xmr  TEXT NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS wallet_eth  TEXT NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS wallet_sol  TEXT NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS wallet_doge TEXT NOT NULL DEFAULT '';

-- Preferred payout currency (one of BTC/XMR/ETH/SOL/DOGE), or '' for none.
ALTER TABLE users ADD COLUMN IF NOT EXISTS preferred_currency TEXT NOT NULL DEFAULT '';
