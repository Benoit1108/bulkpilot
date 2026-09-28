# syntax=docker/dockerfile:1
# Development image only: in production the SPA is built into the API image (api/Dockerfile).
FROM node:24-alpine AS pwa_dev

WORKDIR /app
ENV COREPACK_ENABLE_DOWNLOAD_PROMPT=0
ENV NUXT_TELEMETRY_DISABLED=1
# node_modules lives in a named volume that inherits these permissions: world-writable so that
# compose can run the container with the uid of whoever owns the checkout (see docker-compose.dev.yml).
RUN corepack enable \
	&& mkdir -p /app/node_modules \
	&& chmod 0777 /app /app/node_modules

USER node
EXPOSE 3000
CMD ["sh", "-c", "pnpm install --frozen-lockfile && pnpm dev"]
