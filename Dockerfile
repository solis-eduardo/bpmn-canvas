# --- Etapa 1: baixa o bpmn-js (servido localmente, sem CDN) ---
FROM node:22-alpine AS vendor
ARG BPMN_JS_VERSION=18.30.0
WORKDIR /build
RUN npm install --no-audit --no-fund --omit=dev bpmn-js@${BPMN_JS_VERSION}

# --- Etapa 2: nginx (módulo DAV já vem na imagem oficial) ---
FROM nginx:stable-alpine
RUN apk add --no-cache openssl

COPY --from=vendor /build/node_modules/bpmn-js/dist/assets/                                 /usr/share/nginx/html/vendor/bpmn-js/assets/
COPY --from=vendor /build/node_modules/bpmn-js/dist/bpmn-modeler.production.min.js          /usr/share/nginx/html/vendor/bpmn-js/
COPY --from=vendor /build/node_modules/bpmn-js/dist/bpmn-navigated-viewer.production.min.js /usr/share/nginx/html/vendor/bpmn-js/

COPY nginx/default.conf              /etc/nginx/conf.d/default.conf
COPY --chmod=755 entrypoint/40-bpmn-setup.sh /docker-entrypoint.d/40-bpmn-setup.sh
COPY web/                            /usr/share/nginx/html/

VOLUME ["/data/diagrams"]
EXPOSE 80
