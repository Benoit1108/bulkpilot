#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	if [ "$APP_ENV" = 'prod' ]; then
		for required in APP_SECRET APP_ENCRYPTION_KEY DEFAULT_URI MAILER_DSN MAILER_FROM; do
			if [ -z "$(printenv "$required")" ]; then
				echo "$required must be provided in production." >&2
				exit 1
			fi
		done
		# Verification and reset emails must actually leave, with links that work.
		case "$MAILER_DSN" in null://*) echo 'MAILER_DSN must be a real transport in production.' >&2; exit 1 ;; esac
		case "$DEFAULT_URI" in *://localhost*) echo 'DEFAULT_URI must be the public URL in production.' >&2; exit 1 ;; esac
		# Email links and OAuth redirects carry secrets: the public URL is HTTPS, even when TLS ends
		# before this container (tailscale serve, docs/DEPLOYMENT.md).
		case "$DEFAULT_URI" in https://*) ;; *) echo 'DEFAULT_URI must be an https:// URL in production.' >&2; exit 1 ;; esac
		# Plain HTTP here only behind a TLS-terminating proxy whose headers are trusted: otherwise
		# Symfony would see http:// and the session cookie would lose its Secure flag.
		case "$SERVER_NAME" in
			:80|http://*)
				if [ -z "$TRUSTED_PROXIES" ]; then
					echo 'SERVER_NAME serves plain HTTP: TRUSTED_PROXIES must name the TLS proxy in front (docs/DEPLOYMENT.md).' >&2
					exit 1
				fi
				;;
		esac
		# The fake product database ships in the image for E2E runs only (docker-compose.test.yml).
		if [ "$FOOD_DATABASE" = 'fake' ] && [ "$ALLOW_FAKE_PROVIDERS" != '1' ]; then
			echo 'FOOD_DATABASE=fake is for tests: set it to openfoodfacts in production.' >&2
			exit 1
		fi
		# Photos live in a private bucket: a container's disk is neither private enough nor lasting.
		if [ "$MEDIA_STORAGE" != 's3' ]; then
			echo 'MEDIA_STORAGE must be s3 in production (ADR-0027).' >&2
			exit 1
		fi
		for required in S3_BUCKET S3_ACCESS_KEY S3_SECRET_KEY; do
			if [ -z "$(printenv "$required")" ]; then
				echo "$required must be provided in production." >&2
				exit 1
			fi
		done
		# Presigned links are bearer links to private photos: only over TLS.
		case "${S3_PUBLIC_ENDPOINT:-$S3_ENDPOINT}" in http://*) echo 'The S3 endpoint the browser reaches must be https:// in production.' >&2; exit 1 ;; esac
		# Automatic, encrypted, off-server backups (spec §106, ADR-0031): the worker makes them, and
		# only it holds their secrets (a break-in through the web server cannot delete them).
		# The worker, and the backup commands run in its image (deploy/restore.sh, the pre-deployment backup).
		case "$2" in messenger:consume|app:backup:*) backup_role=1 ;; *) backup_role=0 ;; esac
		if [ "$backup_role" = 1 ]; then
			if [ "$BACKUP_ENABLED" != '1' ]; then
				echo 'BACKUP_ENABLED must be 1 in production: the database is backed up every night (ADR-0031).' >&2
				exit 1
			fi
			for required in BACKUP_S3_BUCKET BACKUP_S3_ACCESS_KEY BACKUP_S3_SECRET_KEY BACKUP_PASSPHRASE; do
				if [ -z "$(printenv "$required")" ]; then
					echo "$required must be provided in production." >&2
					exit 1
				fi
			done
			if [ "${#BACKUP_PASSPHRASE}" -lt 32 ]; then
				echo 'BACKUP_PASSPHRASE must be at least 32 characters (openssl rand -base64 32).' >&2
				exit 1
			fi
			if [ "$BACKUP_S3_BUCKET" = "$S3_BUCKET" ] && [ "${BACKUP_S3_ENDPOINT}" = "${S3_ENDPOINT}" ]; then
				echo 'BACKUP_S3_BUCKET must not be the photos bucket.' >&2
				exit 1
			fi
			# The E2E gateway has a single key (ALLOW_SHARED_BACKUP_KEY=1 there only).
			if [ "$BACKUP_S3_ACCESS_KEY" = "$S3_ACCESS_KEY" ] && [ "${ALLOW_SHARED_BACKUP_KEY:-0}" != '1' ]; then
				echo 'BACKUP_S3_ACCESS_KEY must not be the photos key: each bucket has credentials of its own.' >&2
				exit 1
			fi
		elif [ -n "$BACKUP_PASSPHRASE" ] || [ -n "$BACKUP_S3_SECRET_KEY" ]; then
			echo 'The backup secrets belong to the worker only (docker-compose.yml): remove them from this container.' >&2
			exit 1
		fi
	fi

	# Content-Security-Policy of the production PWA (Caddyfile, pwa_static): its inline scripts by
	# their hashes (computed at build), photos and exports from the bucket's own origin.
	if [ -f /etc/frankenphp/csp-script-hashes ]; then
		CSP_SCRIPT_HASHES="$(cat /etc/frankenphp/csp-script-hashes)"
		export CSP_SCRIPT_HASHES
	fi
	media_endpoint="${S3_PUBLIC_ENDPOINT:-$S3_ENDPOINT}"
	if [ "$MEDIA_STORAGE" = 's3' ]; then
		if [ -z "$media_endpoint" ]; then
			CSP_MEDIA_SOURCES="https://${S3_BUCKET}.s3.${S3_REGION:-us-east-1}.amazonaws.com"
		else
			media_origin="$(printf '%s' "$media_endpoint" | sed -E 's#^(https?://[^/]+).*#\1#')"
			if [ "${S3_PATH_STYLE:-false}" = 'true' ]; then
				CSP_MEDIA_SOURCES="$media_origin"
			else
				CSP_MEDIA_SOURCES="$(printf '%s' "$media_origin" | sed -E "s#^(https?://)#\1${S3_BUCKET}.#")"
			fi
		fi
		export CSP_MEDIA_SOURCES
	fi

	# The PWA posts its error reports to the host of its DSN (docs/MONITORING.md): allowed in connect-src.
	# Only a well-formed https DSN: anything else would end up verbatim in the policy (key included).
	if [ -n "${PWA_SENTRY_DSN:-}" ]; then
		if ! printf '%s' "$PWA_SENTRY_DSN" | grep -Eq '^https://[A-Za-z0-9]+@[A-Za-z0-9.-]+(:[0-9]+)?/[0-9]+$'; then
			echo 'PWA_SENTRY_DSN must look like https://<key>@<host>/<project> (docs/MONITORING.md).' >&2
			exit 1
		fi
		CSP_REPORTING_SOURCES="$(printf '%s' "$PWA_SENTRY_DSN" | sed -E 's#^https://[^@]+@([^/]+)/.*#https://\1#')"
		export CSP_REPORTING_SOURCES
	fi

	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	echo 'Waiting for database to be ready...'
	ATTEMPTS_LEFT_TO_REACH_DATABASE=60
	until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(php bin/console dbal:run-sql -q "SELECT 1" 2>&1); do
		sleep 1
		ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
		echo "Still waiting for database to be ready... $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
	done

	if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
		echo 'The database is not up or not reachable:'
		echo "$DATABASE_ERROR"
		exit 1
	fi

	echo 'The database is now ready and reachable'

	# Single-instance deployment for now; with several replicas this becomes a one-off release job.
	# The worker leaves them to php (RUN_MIGRATIONS=0), which it starts after.
	if [ "${RUN_MIGRATIONS:-1}" = '1' ] && [ "$( find ./migrations -iname '*.php' -print -quit )" ]; then
		php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
		# The exercise library ships with the code: kept up to date on every start (idempotent).
		php bin/console training:library:import --no-interaction
	fi

	# Dev only: the container user (root) and www-data share var/ on the bind mount.
	if [ "$(id -u)" = '0' ]; then
		setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX var
		setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX var
	fi
fi

exec docker-php-entrypoint "$@"
