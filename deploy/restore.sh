#!/usr/bin/env bash
# Replaces an environment's database by a backup, with one image's tools (called by rollback.sh, or
# by hand: docs/DEPLOYMENT.md, "Restaurer"):
#   /opt/bulkpilot/bin/restore.sh <production|staging> <image ref> <backup key|latest>
# php and the worker stop first (nothing writes meanwhile); everything written since the backup is
# lost. The decrypted dump only lives in a temporary directory, removed whatever happens.
set -euo pipefail
trap '' HUP
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
. "$here/lib.sh"

env="${1:-}"
ref="${2:-}"
key="${3:-}"
require_env "$env"
[[ -n "$ref" && -n "$key" ]] || die "usage: restore.sh <env> <image ref> <backup key|latest>"
[[ "${BULKPILOT_LOCKED:-0}" == 1 ]] || take_lock
key_option=""
[[ "$key" == latest ]] || key_option="--key=$key"

work="$(mktemp -d "$BULKPILOT_ROOT/state/restore-XXXXXX")"
trap 'rm -rf "$work"' EXIT
# The image runs as uid 1000: the directory is its for the time of the restore.
chmod 777 "$work"

log "stopping php and the worker of $env"
compose_at "$env" "$ref" stop php worker
log "fetching and decrypting the backup ($key)"
# shellcheck disable=SC2086 # key_option is one word or nothing
compose_at "$env" "$ref" run --rm --no-deps -e RUN_MIGRATIONS=0 -v "$work:/restore" worker \
	bin/console app:backup:fetch /restore/restore.dump $key_option --no-debug
log "replacing the database by the backup"
# The schema is emptied then filled from the dump: tables added since the backup do not stay.
# shellcheck disable=SC2016 # expanded in the container, from its own DATABASE_URL
compose_at "$env" "$ref" run --rm --no-deps -e RUN_MIGRATIONS=0 -v "$work:/restore" --entrypoint sh worker -c '
	set -e
	url="${DATABASE_URL%%\?*}"
	psql -v ON_ERROR_STOP=1 -q -d "$url" -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"
	pg_restore --no-owner --no-privileges --exit-on-error -d "$url" /restore/restore.dump'
log "$env database restored from $key"
