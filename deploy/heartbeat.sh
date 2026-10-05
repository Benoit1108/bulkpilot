#!/usr/bin/env bash
# Tells the external watcher (healthchecks.io, docs/MONITORING.md) that the server and production
# are up; run every 5 minutes by the bulkpilot-heartbeat systemd timer (bootstrap.sh). When the VM
# is down, this stops, and healthchecks.io raises the alert: Uptime Kuma, on the VM, cannot.
#   HEALTHCHECKS_HOST_URL in /opt/bulkpilot/env/server.env (empty: nothing is sent).
set -euo pipefail
BULKPILOT_ROOT="${BULKPILOT_ROOT:-/opt/bulkpilot}"
if [[ -r "$BULKPILOT_ROOT/env/server.env" ]]; then
	# shellcheck source=/dev/null
	. "$BULKPILOT_ROOT/env/server.env"
fi
url="${HEALTHCHECKS_HOST_URL:-}"
[[ -n "$url" ]] || exit 0
if curl -fsS --max-time 10 -o /dev/null "http://127.0.0.1:${BULKPILOT_PRODUCTION_PORT:-8080}/api/health"; then
	curl -fsS --max-time 10 --retry 3 -o /dev/null "$url"
else
	curl -fsS --max-time 10 --retry 3 -o /dev/null --data-raw "production /api/health failed" "$url/fail"
fi
