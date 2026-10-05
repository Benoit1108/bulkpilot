#!/usr/bin/env bash
# docker compose on the image an environment runs, for day-to-day operations on the server:
#   /opt/bulkpilot/bin/bp.sh production ps
#   /opt/bulkpilot/bin/bp.sh production logs -f worker
#   /opt/bulkpilot/bin/bp.sh production exec worker bin/console app:backup:create
set -euo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
. "$here/lib.sh"

env="${1:-}"
require_env "$env"
shift
ref="$(ref_of "$env" current)"
[[ -n "$ref" ]] || die "nothing deployed to $env yet"
compose_at "$env" "$ref" "$@"
