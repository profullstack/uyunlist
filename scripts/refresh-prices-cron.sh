#!/usr/bin/env bash
# Cron helper: recompute cached crypto prices for active listings from current
# exchange rates. Runs hourly (installed by deploy/provision.sh).
cd /opt/uyunlist || exit 0
SEC=$(grep '^APP_SECRET=' .env 2>/dev/null | cut -d= -f2-)
[ -z "$SEC" ] && exit 0
curl -s -m 60 "http://localhost:8080/cron/refresh-prices?token=${SEC}" >/dev/null 2>&1
