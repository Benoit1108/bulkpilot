#!/usr/bin/env bash
# `make deploy-test`: the server scripts end to end on this machine, against a throwaway "server"
# (a temporary BULKPILOT_ROOT, compose projects bulkpilot-deploytest-*, local images
# bulkpilot-deploytest-php:sha-*), with the production image built from the working tree. Images
# are local (no registry); a stand-in verifier plays cosign: it refuses the "unsigned" commit.
#   - the SSH gate: each key deploys its own environment, nothing else;
#   - argument checks; an unsigned image is refused; production only runs what succeeded in staging;
#   - behind the TLS proxy, Symfony sees the browser's scheme and host;
#   - /api/health?scope=monitoring watches every part, backups included;
#   - a release that never becomes healthy, and one whose migration fails, are rolled back;
#   - the same release twice keeps the previous one; the lock refuses a second deployment;
#   - production backs up before deploying (a real S3 gateway here), refuses to deploy when the
#     backup fails, and --skip-backup is a deliberate way through;
#   - rollback.sh, and rollback.sh --restore=latest (the database really comes back);
#   - prune keeps what runs and what may be promoted.
# Everything it created is removed at the end, whatever happens.
set -euo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# The tree the image is built from: the working tree by default, another checkout if given.
source_tree="${DEPLOY_TEST_SOURCE:-$repo}"
work="$(mktemp -d "${TMPDIR:-/tmp}/bulkpilot-deploytest-XXXXXX")"
export BULKPILOT_ROOT="$work/server"
export BULKPILOT_IMAGE=bulkpilot-deploytest-php
export BULKPILOT_PROJECT=bulkpilot-deploytest
export BULKPILOT_PULL=0
export BULKPILOT_WAIT_TIMEOUT=240
export BULKPILOT_SETTLE_SECONDS=5
# prune.sh runs in its own step: the test's images are created ahead of their deployments.
export BULKPILOT_PRUNE=0
export BULKPILOT_VERIFY="$work/verify"
# Free ports of this machine, not the server's 8080/8081.
BULKPILOT_PRODUCTION_PORT="$(python3 -c 'import socket; s=socket.socket(); s.bind(("127.0.0.1", 0)); print(s.getsockname()[1])')"
BULKPILOT_STAGING_PORT="$(python3 -c 'import socket; s=socket.socket(); s.bind(("127.0.0.1", 0)); print(s.getsockname()[1])')"
export BULKPILOT_PRODUCTION_PORT BULKPILOT_STAGING_PORT
good=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
bad=bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb
newer=cccccccccccccccccccccccccccccccccccccccc
broken_migration=dddddddddddddddddddddddddddddddddddddddd
unsigned=eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee
old=ffffffffffffffffffffffffffffffffffffffff
bin="$BULKPILOT_ROOT/bin"
s3=bulkpilot-deploytest-s3

step() { printf '\n--- %s\n' "$*"; }
fail() { printf 'DEPLOY TEST FAILED: %s\n' "$*" >&2; exit 1; }
current() { cut -d' ' -f1 "$BULKPILOT_ROOT/state/$1.current" 2> /dev/null || true; }
previous() { cut -d' ' -f1 "$BULKPILOT_ROOT/state/$1.previous" 2> /dev/null || true; }
image() { printf '%s:sha-%s' "$BULKPILOT_IMAGE" "$1"; }
port() { if [[ "$1" == production ]]; then echo "$BULKPILOT_PRODUCTION_PORT"; else echo "$BULKPILOT_STAGING_PORT"; fi; }
serves() { curl -fsS --max-time 5 -o /dev/null "http://127.0.0.1:$(port "$1")/api/health"; }
# Runs a command that must fail, and checks its error says why.
must_fail() {
	local expected="$1" output
	shift
	if output="$("$@" 2>&1)"; then fail "expected a failure ($expected), it succeeded: $*"; fi
	grep -qF -- "$expected" <<< "$output" || { printf '%s\n' "$output" | tail -20 >&2; fail "expected '$expected' in the error of: $*"; }
	echo "refused as expected: $expected"
}
# The schema version the production database is at (the newest migration it ran).
schema_of() {
	docker exec "$BULKPILOT_PROJECT-$1-database-1" psql -U app -d app -tAc 'SELECT max(version) FROM doctrine_migration_versions'
}
deploy() { "$bin/deploy.sh" "$@"; }

cleanup() {
	set +e
	for env in staging production; do
		BULKPILOT_ENV=$env BULKPILOT_IMAGE_REF=unused BULKPILOT_PORT=1 BULKPILOT_SUBNET=10.231.99.0/24 \
			COMPOSE_PROFILES=local-db docker compose -p "$BULKPILOT_PROJECT-$env" -f "$bin/compose.yaml" \
			--env-file "$BULKPILOT_ROOT/env/$env.env" down --volumes --remove-orphans > /dev/null 2>&1
	done
	docker rm -f "$s3" > /dev/null 2>&1
	for sha in "$good" "$bad" "$newer" "$broken_migration" "$unsigned" "$old"; do
		docker image rm "$(image "$sha")" > /dev/null 2>&1
	done
	rm -rf "$work"
}
trap cleanup EXIT

mkdir -p "$BULKPILOT_ROOT"/{bin,env,state}
for file in deploy.sh rollback.sh restore.sh prune.sh bp.sh lib.sh compose.yaml; do
	cp "$repo/deploy/$file" "$bin/"
done
cp "$repo/deploy/gate.sh" "$work/gate"
# The stand-in for cosign: every image but the unsigned one passes.
# shellcheck disable=SC2016 # $1 is the stand-in's own argument
printf '#!/usr/bin/env bash\n[[ "$1" != *sha-%s ]] || { echo "no matching signatures" >&2; exit 1; }\n' "$unsigned" > "$BULKPILOT_VERIFY"
chmod +x "$BULKPILOT_VERIFY" "$work/gate"
# A readable, non-secret 32-byte key, encoded here so that no key-looking literal sits in the repository.
test_key="$(printf 'deploy-test-only-not-a-secret-32' | base64)"
for env in staging production; do
	cat > "$BULKPILOT_ROOT/env/$env.env" <<EOF
PUBLIC_HOST=deploytest.invalid
APP_SECRET=deploy-test-only-not-a-secret-$env
APP_ENCRYPTION_KEY=$test_key
REGISTRATION_ENABLED=false
POSTGRES_PASSWORD=deploy-test-only
POSTGRES_SHARED_BUFFERS=128MB
POSTGRES_EFFECTIVE_CACHE_SIZE=256MB
MAILER_DSN=smtp://user:pass@127.0.0.1:2525
MAILER_FROM=BulkPilot <deploy-test@example.invalid>
OPEN_FOOD_FACTS_USER_AGENT=BulkPilot/deploy-test (deploy-test@example.invalid)
S3_ENDPOINT=https://photos.invalid
S3_BUCKET=photos
S3_ACCESS_KEY=photos-key
S3_SECRET_KEY=photos-secret
BACKUP_PASSPHRASE=deploy-test-only-passphrase-not-a-secret
BACKUP_S3_ENDPOINT=http://$s3:7070
BACKUP_S3_BUCKET=bulkpilot-backups
BACKUP_S3_REGION=us-east-1
BACKUP_S3_ACCESS_KEY=deploytest-backups
BACKUP_S3_SECRET_KEY=deploytest-only-not-a-secret
EOF
done

step "building the production image from the working tree (as CI does)"
tar -C "$source_tree" --exclude=node_modules --exclude=vendor --exclude=var --exclude=.nuxt --exclude=.output \
	--exclude=test-results --exclude=playwright-report --exclude=lighthouse-report -cf - api pwa |
	(mkdir -p "$work/source" && tar -x -C "$work/source")
DOCKER_BUILDKIT=1 docker build -q --file "$work/source/api/Dockerfile" --target frankenphp_prod \
	--build-context "pwa=$work/source/pwa" --build-arg "APP_RELEASE=sha-$good" \
	--tag "$(image "$good")" "$work/source/api" > /dev/null
rm -rf "$work/source"
# A release whose web server never answers its healthcheck.
printf 'FROM %s\nUSER root\nRUN rm public/index.php\nUSER app\n' "$(image "$good")" | docker build -q -t "$(image "$bad")" - > /dev/null
# A release whose migration fails (all or nothing: nothing of it stays).
mkdir -p "$work/broken"
cat > "$work/broken/Version29991231000000.php" <<'EOF'
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version29991231000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE deploy_test_half (id INT)');
        $this->addSql('SELECT no_such_function()');
    }
}
EOF
printf 'FROM %s\nCOPY Version29991231000000.php migrations/\n' "$(image "$good")" | docker build -q -t "$(image "$broken_migration")" -f - "$work/broken" > /dev/null
for sha in "$newer" "$unsigned" "$old"; do docker tag "$(image "$good")" "$(image "$sha")"; done

step "the SSH gate: each key deploys its own environment, nothing else"
must_fail "refused" env SSH_ORIGINAL_COMMAND="rm -rf /" BULKPILOT_ROOT="$BULKPILOT_ROOT" "$work/gate" staging
must_fail "refused" env SSH_ORIGINAL_COMMAND="deploy $good production" BULKPILOT_ROOT="$BULKPILOT_ROOT" "$work/gate" staging
must_fail "refused" env SSH_ORIGINAL_COMMAND="deploy $good; id" BULKPILOT_ROOT="$BULKPILOT_ROOT" "$work/gate" staging
must_fail "misconfigured key" env SSH_ORIGINAL_COMMAND="deploy $good" BULKPILOT_ROOT="$BULKPILOT_ROOT" "$work/gate" prod

step "arguments, signature and the staging gate"
must_fail "expected a full commit id" deploy abc123 staging
must_fail "expected production or staging" deploy "$good" prod
must_fail "unknown option" deploy "$good" staging --force
must_fail "not signed by this repository's CI" deploy "$unsigned" staging
must_fail "never ran in staging" deploy "$good" production

step "the good release reaches staging, then production (the digest staging recorded)"
SSH_ORIGINAL_COMMAND="deploy $good" BULKPILOT_ROOT="$BULKPILOT_ROOT" "$work/gate" staging
[[ "$(current staging)" == "$good" ]] || fail "staging does not record $good"
deploy "$good" production
[[ "$(current production)" == "$good" ]] || fail "production does not record $good"
grep -q " $good $(image "$good")\$" "$BULKPILOT_ROOT/state/production.history" || fail "production's history does not name the image it ran"

step "behind the TLS proxy, the browser's scheme and host are the ones Symfony sees"
sign_in() { curl -s -o /dev/null -w '%{http_code}' -X POST "http://127.0.0.1:$BULKPILOT_STAGING_PORT/api/auth/sign-in" \
	-H 'Content-Type: application/json' -H "Origin: $1" --data '{"email":"nobody@example.invalid","password":"not-the-password"}'; }
[[ "$(sign_in https://deploytest.invalid)" != 403 ]] || fail "a write from the public origin was refused: X-Forwarded-Proto/Host are not trusted"
[[ "$(sign_in https://evil.example)" == 403 ]] || fail "a write from another site was accepted"

step "/api/health?scope=monitoring watches every part, backups included"
monitoring="$(curl -s "http://127.0.0.1:$BULKPILOT_STAGING_PORT/api/health?scope=monitoring")"
python3 - "$monitoring" <<'EOF' || fail "monitoring scope: $monitoring"
import json, sys
monitors = json.loads(sys.argv[1])['monitors']
assert set(monitors) >= {'worker', 'backup', 'backup_verify', 'disk'}, monitors
assert monitors['backup'] != 'disabled', 'the web container does not watch the backups: %s' % monitors
EOF

step "a release that never becomes healthy is rolled back"
must_fail "runs $good again" deploy "$bad" staging
[[ "$(current staging)" == "$good" ]] || fail "staging should still record $good"
serves staging || fail "staging is not serving after the rollback"
serves production || fail "production stopped serving while staging failed"
[[ -s "$BULKPILOT_ROOT/state/staging.failure.log" ]] || fail "the failed release's logs were not kept"
must_fail "never ran in staging" deploy "$bad" production

step "a release whose migration fails is rolled back, and leaves nothing behind"
schema_before="$(schema_of staging)"
must_fail "runs $good again" deploy "$broken_migration" staging
[[ "$(schema_of staging)" == "$schema_before" ]] || fail "the failed migration changed the schema"
[[ -z "$(docker exec "$BULKPILOT_PROJECT-staging-database-1" psql -U app -d app -tAc "SELECT to_regclass('deploy_test_half')")" ]] ||
	fail "half of the failed migration stayed"

step "deploying the same release again keeps the previous one; the lock refuses a second deployment"
deploy "$newer" staging
deploy "$newer" staging
[[ "$(previous staging)" == "$good" ]] || fail "a redeployment of $newer overwrote the previous release"
(
	exec 8> "$BULKPILOT_ROOT/state/deploy.lock"
	flock 8
	must_fail "another deployment" deploy "$good" staging
)

step "production: a backup before deploying, to a real S3 gateway"
docker run -d --name "$s3" --network "$BULKPILOT_PROJECT-production_default" --tmpfs /data --entrypoint sh \
	versity/versitygw:v1.8.0@sha256:30292fc2eeacc67a36993b01f7a7a5e3361a19cced0e80c1d71cfa2a4b0a2499 \
	-c 'mkdir -p /data/bulkpilot-backups && exec versitygw --access deploytest-backups --secret deploytest-only-not-a-secret posix /data' > /dev/null # gitleaks:allow
for _ in $(seq 1 30); do docker exec "$s3" nc -z 127.0.0.1 7070 2> /dev/null && break; sleep 1; done
deploy "$newer" production 2> "$work/deploy.log" || { tail -30 "$work/deploy.log" >&2; fail "production did not deploy $newer"; }
grep -q "backing up production" "$work/deploy.log" || fail "production deployed without its backup"
[[ -n "$(docker exec "$s3" find /data/bulkpilot-backups -type f -name '*.dump.enc')" ]] || fail "no backup reached the bucket"
[[ "$(current production)" == "$newer" ]] || fail "production does not record $newer"

step "rollback.sh --restore=latest brings the previous release back on the backup"
docker exec "$BULKPILOT_PROJECT-production-database-1" psql -U app -d app -qc "CREATE TABLE deploy_test_after_backup (id INT)"
"$bin/rollback.sh" production --restore=latest
[[ "$(current production)" == "$good" ]] || fail "rollback.sh did not bring production back to $good"
[[ -z "$(docker exec "$BULKPILOT_PROJECT-production-database-1" psql -U app -d app -tAc "SELECT to_regclass('deploy_test_after_backup')")" ]] ||
	fail "the database was not restored (a table created after the backup is still there)"
serves production || fail "production is not serving after the restore"

step "production refuses to deploy when its backup fails; --skip-backup goes through"
docker stop "$s3" > /dev/null
must_fail "pre-deployment backup failed" deploy "$newer" production
[[ "$(current production)" == "$good" ]] || fail "production should still run $good"
deploy "$newer" production --skip-backup 2> "$work/deploy.log"
grep -q "no backup before this deployment" "$work/deploy.log" || fail "--skip-backup was not said"
[[ "$(current production)" == "$newer" ]] || fail "production does not record $newer"

step "rollback.sh without restore"
docker start "$s3" > /dev/null
for _ in $(seq 1 30); do docker exec "$s3" nc -z 127.0.0.1 7070 2> /dev/null && break; sleep 1; done
"$bin/rollback.sh" production
[[ "$(current production)" == "$good" && "$(previous production)" == "$newer" ]] || fail "rollback.sh: current/previous of production are wrong"
awk '{ print $2 }' "$BULKPILOT_ROOT/state/production.history" | grep -qx "$bad" && fail "a failed release is in production's history"

step "prune keeps what runs, what ran just before and what staging may promote"
deploy "$old" staging
BULKPILOT_KEEP_RELEASES=1 "$bin/prune.sh"
for sha in "$good" "$newer" "$old"; do
	docker image inspect "$(image "$sha")" > /dev/null 2>&1 || fail "prune removed $(image "$sha"), which is still needed"
done
docker image inspect "$(image "$bad")" > /dev/null 2>&1 && fail "prune kept $(image "$bad"), which never ran"
docker image inspect "$(image "$broken_migration")" > /dev/null 2>&1 && fail "prune kept $(image "$broken_migration"), which never ran"

step "the monitoring compose file is valid"
docker compose -f "$repo/deploy/ops.compose.yaml" config -q

printf '\nDEPLOY TEST OK\n'
