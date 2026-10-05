#!/usr/bin/env bash
# Shared by the server scripts (sourced, never run). Layout on the server (BULKPILOT_ROOT,
# /opt/bulkpilot by default):
#   bin/                  these scripts and compose.yaml, owned by root, installed by bootstrap.sh only:
#                         no release, no CI job and not the deploy user can change what runs a deployment
#   env/server.env        the server's settings (image repository, signer): not secret
#   env/<env>.env         an environment's settings and secrets (mode 600, never in Git)
#   state/<env>.current   the image running ("<commit> <image>@sha256:…"); .previous the one before
#   state/<env>.history   "<UTC time> <commit> <image>@sha256:…" per successful deployment
#   state/deploy.lock     one deployment, rollback or prune at a time, whatever the environment
# shellcheck shell=bash

BULKPILOT_ROOT="${BULKPILOT_ROOT:-/opt/bulkpilot}"
export BULKPILOT_ROOT
# The server's settings: where images come from and who must have signed them (bootstrap.sh).
if [[ -r "$BULKPILOT_ROOT/env/server.env" ]]; then
	# shellcheck source=/dev/null
	. "$BULKPILOT_ROOT/env/server.env"
fi
# The image repository (GHCR); images are pulled by digest, never built here (ADR-0033).
BULKPILOT_IMAGE="${BULKPILOT_IMAGE:-ghcr.io/OWNER/bulkpilot-php}"
# 1: pull from the registry and check the signature (the server); 0: local images only (deploy-test).
BULKPILOT_PULL="${BULKPILOT_PULL:-1}"
export BULKPILOT_IMAGE BULKPILOT_PULL

log() { printf '%s %s\n' "$(date -u +%H:%M:%S)" "$*" >&2; }
die() { log "ERROR: $*"; exit 1; }

# A full 40-hex commit id, so that a release is never ambiguous.
require_sha() {
	[[ "${1:-}" =~ ^[0-9a-f]{40}$ ]] || die "expected a full commit id (40 hex characters), got '${1:-}'"
}

require_env() {
	case "${1:-}" in
		production | staging) ;;
		*) die "expected production or staging, got '${1:-}'" ;;
	esac
	[[ -r "$BULKPILOT_ROOT/env/$1.env" ]] || die "missing $BULKPILOT_ROOT/env/$1.env (copy deploy/environment.env.example)"
}

# What differs between the two environments of the server, defined here once: the loopback port the
# TLS proxy reaches, the subnet of the compose network (the only proxies Symfony trusts) and the
# resources staging may take from production.
port_of() { case "$1" in production) echo "${BULKPILOT_PRODUCTION_PORT:-8080}" ;; staging) echo "${BULKPILOT_STAGING_PORT:-8081}" ;; esac; }
subnet_of() { case "$1" in production) echo "${BULKPILOT_PRODUCTION_SUBNET:-10.231.10.0/24}" ;; staging) echo "${BULKPILOT_STAGING_SUBNET:-10.231.11.0/24}" ;; esac; }

# A value of an environment's settings file (KEY=value; quotes and comments after a space ignored).
setting() {
	sed -n "s/^${2}=[\"']\{0,1\}\([^\"' #]*\).*/\1/p" "$BULKPILOT_ROOT/env/$1.env" | tail -n 1
}

# docker compose for one environment running one image (repository@digest), with the server's compose file.
compose_at() {
	local env="$1" ref="$2" profiles=local-db
	shift 2
	[[ "$(setting "$env" EXTERNAL_DATABASE)" == 1 ]] && profiles=
	[[ "$env" == staging && -n "$(setting staging TS_AUTHKEY)" ]] && profiles="${profiles:+$profiles,}own-node"
	local mem=0 cpus=0
	[[ "$env" == staging ]] && mem="${BULKPILOT_STAGING_MEM_LIMIT:-1536m}" cpus="${BULKPILOT_STAGING_CPUS:-1}"
	BULKPILOT_ENV="$env" BULKPILOT_IMAGE_REF="$ref" BULKPILOT_PORT="$(port_of "$env")" BULKPILOT_SUBNET="$(subnet_of "$env")" \
		BULKPILOT_MEM_LIMIT="$mem" BULKPILOT_CPUS="$cpus" COMPOSE_PROFILES="$profiles" docker compose \
		-f "$BULKPILOT_ROOT/bin/compose.yaml" \
		--env-file "$BULKPILOT_ROOT/env/$env.env" \
		"$@"
}

state_file() { printf '%s/state/%s.%s' "$BULKPILOT_ROOT" "$1" "$2"; }

# "<commit> <image ref>" of what runs (current) or ran before (previous); empty when none.
state_of() { cat "$(state_file "$1" "$2")" 2> /dev/null || true; }
ref_of() { state_of "$1" "$2" | cut -s -d' ' -f2; }

# Every deployment, rollback and prune takes this lock; a second one fails at once.
take_lock() {
	mkdir -p "$BULKPILOT_ROOT/state"
	exec 9> "$BULKPILOT_ROOT/state/deploy.lock"
	flock -n 9 || die "another deployment, rollback or prune is running (state/deploy.lock)"
	export BULKPILOT_LOCKED=1
}

# The signature CI puts on an image once it passed every test (cosign keyless, GitHub OIDC): only
# images signed by this repository's CI workflow on main run here.
verify_image() {
	local ref="$1"
	if [[ -n "${BULKPILOT_VERIFY:-}" ]]; then
		"$BULKPILOT_VERIFY" "$ref"
		return
	fi
	[[ -n "${BULKPILOT_SIGNER:-}" ]] || die "BULKPILOT_SIGNER is not set in env/server.env: refusing an unverified image"
	docker run --rm --network host \
		ghcr.io/sigstore/cosign/cosign:v3.1.3@sha256:9e5c2f2edc34351160407ca3416c61855bdf9403c3c5936e0f0be7fc261611b8 \
		verify --certificate-oidc-issuer https://token.actions.githubusercontent.com \
		--certificate-identity "$BULKPILOT_SIGNER" "$ref" > /dev/null
}
