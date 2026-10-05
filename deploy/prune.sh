#!/usr/bin/env bash
# Keeps the disk of the server in check after a deployment: the images that run or ran just before
# (current and previous of each environment) and the last KEEP images staging ran (so that any of
# them can still be promoted) stay; older ones of the repository go.
set -euo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
. "$here/lib.sh"

[[ "${BULKPILOT_LOCKED:-0}" == 1 ]] || take_lock
keep="${BULKPILOT_KEEP_RELEASES:-10}"
declare -A protected=()
for env in production staging; do
	for which in current previous; do
		ref="$(ref_of "$env" "$which")"
		[[ -n "$ref" ]] && protected["$ref"]=1
	done
done
while read -r ref; do
	[[ -n "$ref" ]] && protected["$ref"]=1
done < <(awk '{ print $3 }' "$(state_file staging history)" 2> /dev/null | tac | awk '!seen[$0]++' | head -n "$keep")

# Every local image of the repository, by digest (pulled images) or by tag (deploy-test's local ones).
while read -r image id digest; do
	ref="$image@$digest"
	[[ "$digest" == "<none>" ]] && ref="$image"
	[[ -n "${protected[$ref]:-}" ]] && continue
	log "pruning $ref"
	# By name: an image another environment still uses under another name stays.
	docker image rm "$ref" > /dev/null 2>&1 || docker image rm "$id" > /dev/null 2>&1 || true
done < <(docker images --digests --format '{{.Repository}}:{{.Tag}} {{.ID}} {{.Digest}}' "$BULKPILOT_IMAGE" |
	awk '{ split($1, r, ":"); repo = substr($1, 1, length($1) - length(r[length(r)]) - 1); if ($3 == "<none>") print $1, $2, $3; else print repo, $2, $3 }')
docker image prune --force > /dev/null 2>&1 || true
