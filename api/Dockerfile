# syntax=docker/dockerfile:1

#--------------------------------------------------------------------------
# Base: FrankenPHP + PHP 8.5 + extensions shared by every target
#--------------------------------------------------------------------------
FROM dunglas/frankenphp:1.12-php8.5-trixie AS frankenphp_base

WORKDIR /app

# hadolint ignore=DL3008
RUN apt-get update && apt-get install -y --no-install-recommends \
	acl \
	&& rm -rf /var/lib/apt/lists/*

RUN set -eux; \
	install-php-extensions \
		@composer \
		apcu \
		exif \
		gd \
		intl \
		opcache \
		pdo_pgsql \
		zip \
	;

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/frankenphp/Caddyfile

ENTRYPOINT ["docker-entrypoint"]

# Probes PHP itself (internal listener, see Caddyfile), not only Caddy: a wedged worker is unhealthy.
HEALTHCHECK --start-period=60s --start-interval=1s --interval=30s \
	CMD curl -fsS -o /dev/null http://127.0.0.1:2080/api/health || exit 1
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]

#--------------------------------------------------------------------------
# Dev: sources are bind-mounted, Xdebug + PCOV available, file watcher on
#--------------------------------------------------------------------------
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev
ENV XDEBUG_MODE=off
# Only application code is watched: vendor/ changes (composer) need `make restart`.
ENV FRANKENPHP_WORKER_CONFIG="watch ./{src,config}/**/*.{php,yaml,yml}"

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# hadolint ignore=DL3008
RUN apt-get update && apt-get install -y --no-install-recommends \
	git \
	&& rm -rf /var/lib/apt/lists/*

RUN set -eux; \
	install-php-extensions \
		pcov \
		xdebug \
	;

COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/

CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch" ]

#--------------------------------------------------------------------------
# PWA build: static Nuxt SPA, served by Caddy in production.
# Requires the "pwa" build context (see docker-compose.yml).
#--------------------------------------------------------------------------
FROM node:24-alpine AS pwa_build

WORKDIR /pwa
ENV COREPACK_ENABLE_DOWNLOAD_PROMPT=0
ENV NUXT_TELEMETRY_DISABLED=1
RUN corepack enable

COPY --from=pwa --link package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN --mount=type=cache,target=/root/.local/share/pnpm/store \
	pnpm install --frozen-lockfile --ignore-scripts

COPY --from=pwa --link . ./
# The build fails when the app outgrows its size budget (pwa/bundle-budget.json, ADR-0025).
RUN pnpm nuxt prepare && pnpm generate && node scripts/bundle-budget.mjs

#--------------------------------------------------------------------------
# Prod: immutable image, no dev dependencies or tooling, non-root
#--------------------------------------------------------------------------
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod
ARG USER=app

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/

# Dependencies first to leverage the layer cache
COPY --link composer.* symfony.* ./
RUN --mount=type=cache,target=/root/.composer/cache \
	set -eux; \
	composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

COPY --link . ./
RUN rm -Rf frankenphp/
COPY --from=pwa_build --link /pwa/.output/public /app/pwa

RUN set -eux; \
	mkdir -p var/cache var/log var/share; \
	composer dump-autoload --classmap-authoritative --no-dev; \
	composer dump-env prod; \
	composer run-script --no-dev post-install-cmd; \
	chmod +x bin/console; \
	rm -f /usr/local/bin/composer; \
	sync;

# Run as an unprivileged user; FrankenPHP keeps the right to bind ports 80/443.
# hadolint ignore=DL3008
RUN set -eux; \
	useradd --system --uid 1000 --home-dir /app --shell /usr/sbin/nologin ${USER}; \
	apt-get update && apt-get install -y --no-install-recommends libcap2-bin; \
	setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp; \
	apt-get purge -y libcap2-bin && apt-get autoremove -y && rm -rf /var/lib/apt/lists/*; \
	chown -R ${USER}:${USER} /data/caddy /config/caddy var

USER ${USER}
