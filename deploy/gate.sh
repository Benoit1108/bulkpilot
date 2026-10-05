#!/usr/bin/env bash
# The only command a CI key may run on the server (installed by bootstrap.sh as
# /usr/local/sbin/bulkpilot-gate, owned by root). Each key's line in the deploy user's
# authorized_keys names the one environment it may deploy:
#   restrict,command="/usr/local/sbin/bulkpilot-gate staging" ssh-ed25519 AAAA… ci-staging
#   restrict,command="/usr/local/sbin/bulkpilot-gate production" ssh-ed25519 AAAA… ci-production
# Accepted requests (SSH_ORIGINAL_COMMAND), anything else is refused:
#   deploy <40-hex commit> [--skip-backup]
#   status
set -euo pipefail
BULKPILOT_ROOT="${BULKPILOT_ROOT:-/opt/bulkpilot}"

allowed="${1:-}"
case "$allowed" in
	production | staging) ;;
	*) echo "bulkpilot-gate: misconfigured key (expected production or staging, got '$allowed')" >&2; exit 2 ;;
esac

request="${SSH_ORIGINAL_COMMAND:-}"
if [[ "$request" =~ ^deploy\ ([0-9a-f]{40})(\ --skip-backup)?$ ]]; then
	exec "$BULKPILOT_ROOT/bin/deploy.sh" "${BASH_REMATCH[1]}" "$allowed" ${BASH_REMATCH[2]:+--skip-backup}
fi
if [[ "$request" == status ]]; then
	exec "$BULKPILOT_ROOT/bin/bp.sh" "$allowed" ps --format 'table {{.Service}}\t{{.State}}\t{{.Health}}'
fi
echo "bulkpilot-gate: refused '$request' (this key may only run: deploy <commit> [--skip-backup] | status, for $allowed)" >&2
exit 2
