FROM nginx:stable-alpine

ENV NGINX_ENVSUBST_FILTER='^(IDENTITY_SERVICE_HOST|IDENTITY_SERVICE_PORT|TRADING_SERVICE_HOST|TRADING_SERVICE_PORT|WALLET_SERVICE_HOST|WALLET_SERVICE_PORT)$'

COPY nginx/default.conf.template /etc/nginx/templates/default.conf.template

EXPOSE 8080

HEALTHCHECK --interval=15s --timeout=3s --start-period=5s --retries=3 \
  CMD wget -q -O /dev/null http://127.0.0.1:8080/healthz || exit 1
