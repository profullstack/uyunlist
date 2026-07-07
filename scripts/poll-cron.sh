#!/usr/bin/env bash
# Cron helper: nudge the app to poll CoinPay for open invoices and settle paid
# ones. A Tor hidden service can't receive webhooks, so this runs on the box.
# No-op until COINPAY_API_KEY is set. Installed by deploy/provision.sh.
cd /opt/uyunlist || exit 0
SEC=$(grep '^APP_SECRET=' .env 2>/dev/null | cut -d= -f2-)
[ -z "$SEC" ] && exit 0
curl -s -m 30 "http://localhost:8080/cron/poll-payments?token=${SEC}" >/dev/null 2>&1
