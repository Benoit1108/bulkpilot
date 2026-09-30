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
