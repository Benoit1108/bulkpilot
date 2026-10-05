#!/usr/bin/env bash
# Runs one commit's image in one environment, and goes back to the previous one if it does not come up:
#   /opt/bulkpilot/bin/deploy.sh <commit> <production|staging> [--skip-backup]
# The image is never built here: CI built it once, tested it and signed it (ADR-0033).
# 1. staging pulls ghcr…:sha-<commit> and records its digest; production takes that very digest from
#    staging's history (a commit that never succeeded in staging is refused); the signature is checked;
# 2. production only: an encrypted backup before any migration (by the running worker, or a one-off
#    one if it is down); --skip-backup skips it (a deliberate choice: docs/DEPLOYMENT.md, incidents);
# 3. `up -d --wait`: php passes the migrations (all or nothing), every service must be healthy, then
#    stay up (no restart) and answer /api/health through the loopback port the TLS proxy uses;
# 4. on failure the previous image runs again and the command fails; on success it is recorded.
# Migrations only add (expand/contract, docs/DEPLOYMENT.md): the previous code still reads the schema.
# Logs never go to the caller (a CI job): they are kept in state/<env>.failure.log.
set -euo pipefail
# A cancelled CI job closes the SSH session: the deployment carries on rather than stopping half-way.
trap '' HUP
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
. "$here/lib.sh"

sha="${1:-}"
env="${2:-}"
skip_backup="${BULKPILOT_SKIP_BACKUP:-0}"
[[ "${3:-}" == --skip-backup ]] && skip_backup=1
[[ -z "${3:-}" || "${3:-}" == --skip-backup ]] || die "unknown option '${3:-}' (only --skip-backup)"
require_sha "$sha"
require_env "$env"
[[ "${BULKPILOT_LOCKED:-0}" == 1 ]] || take_lock

# The image to run, as repository@digest.
if [[ "$env" == production && "${BULKPILOT_SKIP_STAGING_CHECK:-0}" != 1 ]]; then
	ref="$(awk -v sha="$sha" '$2 == sha { ref = $3 } END { print ref }' "$(state_file staging history)" 2> /dev/null || true)"
	[[ -n "$ref" ]] || die "$sha never ran in staging (state/staging.history): deploy it there and check it first"
elif [[ -n "${BULKPILOT_REF:-}" ]]; then
	ref="$BULKPILOT_REF"
else
	tag="$BULKPILOT_IMAGE:sha-$sha"
	if [[ "$BULKPILOT_PULL" == 1 ]]; then
		docker pull --quiet "$tag" > /dev/null || die "cannot pull $tag: is it built and signed by CI yet?"
		ref="$(docker image inspect --format '{{range .RepoDigests}}{{println .}}{{end}}' "$tag" | grep -m1 "^$BULKPILOT_IMAGE@sha256:" || true)"
		[[ -n "$ref" ]] || die "no digest for $tag"
	else
		docker image inspect "$tag" > /dev/null 2>&1 || die "no local image $tag"
		ref="$tag"
	fi
fi
if [[ "$BULKPILOT_PULL" == 1 ]]; then
	docker pull --quiet "$ref" > /dev/null || die "cannot pull $ref"
fi
verify_image "$ref" || die "$ref is not signed by this repository's CI: refusing it"

previous="$(state_of "$env" current)"
previous_ref="$(ref_of "$env" current)"
port="$(port_of "$env")"
failure_log="$(state_file "$env" failure.log)"

healthy() {
	local attempt
	for attempt in $(seq 1 30); do
		if curl -fsS --max-time 5 -o /dev/null "http://127.0.0.1:$port/api/health"; then
			return 0
		fi
		log "health check $attempt/30 failed, retrying"
		sleep 2
	done
	return 1
}

# Healthy, and still healthy a little later: a service that crashes right after starting (a worker
# that cannot reach its transport) restarts, which `--wait` alone does not see.
stays_up() {
	local container restarts
	sleep "${BULKPILOT_SETTLE_SECONDS:-15}"
	for container in $(compose_at "$env" "$1" ps -q php worker); do
		restarts="$(docker inspect --format '{{.RestartCount}} {{.State.Running}}' "$container")"
		[[ "$restarts" == "0 true" ]] || { log "container $container restarted or stopped ($restarts)"; return 1; }
	done
}

start() {
	if [[ "$BULKPILOT_PULL" == 1 ]]; then
		compose_at "$env" "$1" pull --quiet --ignore-buildable database tailscale 2> /dev/null || true
	fi
	compose_at "$env" "$1" up -d --remove-orphans --wait --wait-timeout "${BULKPILOT_WAIT_TIMEOUT:-300}" && stays_up "$1" && healthy
}

backup() {
	log "backing up $env before deploying (running ${previous%% *})"
	compose_at "$env" "$previous_ref" exec -T worker bin/console app:backup:create --no-debug && return 0
	log "the running worker could not back up: trying a one-off worker"
	compose_at "$env" "$previous_ref" run --rm --no-deps worker bin/console app:backup:create --no-debug
}

if [[ "$previous_ref" == "$ref" ]]; then
	log "$ref already runs in $env: starting it again"
fi

if [[ "$env" == production && -n "$previous_ref" ]]; then
	if [[ "$skip_backup" == 1 ]]; then
		log "no backup before this deployment (--skip-backup)"
	else
		backup || die "the pre-deployment backup failed: nothing deployed (docs/DEPLOYMENT.md, incidents)"
	fi
fi

log "deploying $sha to $env ($ref)"
if start "$ref"; then
	if [[ "$previous_ref" != "$ref" ]]; then
		[[ -n "$previous" ]] && printf '%s\n' "$previous" > "$(state_file "$env" previous)"
		printf '%s %s\n' "$sha" "$ref" > "$(state_file "$env" current)"
	fi
	printf '%s %s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$sha" "$ref" >> "$(state_file "$env" history)"
	log "$env now runs $sha"
	if [[ "${BULKPILOT_PRUNE:-1}" == 1 ]]; then
		"$here/prune.sh" || log "pruning old images failed (ignored)"
	fi
	exit 0
fi

log "$sha did not come up in $env (logs: $failure_log)"
compose_at "$env" "$ref" ps --format 'table {{.Service}}\t{{.State}}\t{{.Health}}' >&2 || true
(umask 077 && compose_at "$env" "$ref" logs --no-color --tail=200 php worker > "$failure_log" 2>&1) || true
if [[ -z "$previous_ref" || "$previous_ref" == "$ref" ]]; then
	die "no previous release to go back to: $env is down"
fi
log "rolling $env back to ${previous%% *}"
if start "$previous_ref"; then
	die "$sha failed; $env runs ${previous%% *} again"
fi
die "$sha failed and ${previous%% *} did not come back: $env is down (docs/DEPLOYMENT.md, incidents)"
