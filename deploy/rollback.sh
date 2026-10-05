#!/usr/bin/env bash
# Goes back to the image that ran before the current one:
#   /opt/bulkpilot/bin/rollback.sh <production|staging> [--restore=<backup key>|--restore=latest]
# Without --restore the database stays as it is: right when the newer release only added to the
# schema (the expand/contract rule, docs/DEPLOYMENT.md). With --restore, php and the worker stop, the
# database is replaced by that backup (`latest`: the newest), the previous image starts on it (no new
# backup: the database is the backup), and the accounts erased since are erased again.
set -euo pipefail
trap '' HUP
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
. "$here/lib.sh"

env="${1:-}"
restore=""
case "${2:-}" in
	"") ;;
	--restore=*) restore="${2#--restore=}" ;;
	*) die "unknown option '${2:-}' (only --restore=<backup key>|latest)" ;;
esac
require_env "$env"
take_lock
previous="$(state_of "$env" previous)"
[[ -n "$previous" ]] || die "no previous release recorded for $env"
sha="${previous%% *}"
ref="${previous#* }"

if [[ -n "$restore" ]]; then
	"$here/restore.sh" "$env" "$ref" "$restore"
	BULKPILOT_SKIP_BACKUP=1
fi

log "rolling $env back to $sha"
# The previous image ran in this environment already: staging's history check does not apply.
BULKPILOT_SKIP_STAGING_CHECK=1 BULKPILOT_REF="$ref" BULKPILOT_SKIP_BACKUP="${BULKPILOT_SKIP_BACKUP:-0}" "$here/deploy.sh" "$sha" "$env"
if [[ -n "$restore" ]]; then
	compose_at "$env" "$ref" exec -T worker bin/console app:backup:reapply-erasures --no-debug ||
		die "restored and rolled back, but the erasures could not be applied again: run app:backup:reapply-erasures before reopening"
fi
