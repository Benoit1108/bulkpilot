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
	if [ "$( find ./migrations -iname '*.php' -print -quit )" ]; then
		php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
	fi

	# Dev only: the container user (root) and www-data share var/ on the bind mount.
	if [ "$(id -u)" = '0' ]; then
		setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX var
		setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX var
	fi
fi

exec docker-php-entrypoint "$@"
