#!/bin/sh
# Executado pelo entrypoint oficial do nginx antes de subir o servidor.
set -eu

if [ -z "${EDITOR_USER:-}" ] || [ -z "${EDITOR_PASSWORD:-}" ]; then
    echo "[bpmn] ERRO: defina EDITOR_USER e EDITOR_PASSWORD (arquivo .env)." >&2
    exit 1
fi

# htpasswd do editor (gerado a cada start a partir das variáveis de ambiente)
printf '%s:%s\n' "$EDITOR_USER" "$(openssl passwd -apr1 "$EDITOR_PASSWORD")" > /etc/nginx/htpasswd
chown root:nginx /etc/nginx/htpasswd
chmod 640 /etc/nginx/htpasswd

# Pasta dos diagramas precisa ser gravável pelos workers do nginx
mkdir -p /data/diagrams
chown -R nginx:nginx /data/diagrams

echo "[bpmn] Editor liberado para o usuário '$EDITOR_USER'."
