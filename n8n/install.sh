#!/usr/bin/env bash
# Installs the Margin Shield alert email workflow into a running n8n Docker container.
#
#   bash n8n/install.sh
#
# - starts a local Mailpit inbox (SMTP :1025, UI http://localhost:8025) if it is not running
# - imports the "Mailpit (local SMTP)" credential (no password, local only)
# - imports the workflow with the token from .env (MARGIN_ALERT_WEBHOOK_TOKEN) and publishes it
# - restarts n8n so the webhook goes live
#
# Override the container names with N8N_CONTAINER / N8N_WORKER_CONTAINER.
set -euo pipefail
export MSYS_NO_PATHCONV=1

cd "$(dirname "$0")/.."

N8N_CONTAINER="${N8N_CONTAINER:-inbox-agent-n8n-1}"
N8N_WORKER_CONTAINER="${N8N_WORKER_CONTAINER:-inbox-agent-n8n-worker-1}"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

TOKEN="$(grep -E '^MARGIN_ALERT_WEBHOOK_TOKEN=' .env | cut -d= -f2- | tr -d '"\r')"
if [ -z "$TOKEN" ]; then
    TOKEN="$(openssl rand -hex 24)"
    printf '\nMARGIN_ALERT_WEBHOOK_TOKEN=%s\n' "$TOKEN" >> .env
    echo "Generated MARGIN_ALERT_WEBHOOK_TOKEN in .env"
fi

if ! docker ps --format '{{.Names}}' | grep -qx margin-mailpit; then
    docker start margin-mailpit 2>/dev/null \
        || docker run -d --name margin-mailpit --restart unless-stopped \
            -p 127.0.0.1:8025:8025 -p 127.0.0.1:1025:1025 axllent/mailpit
fi

sed "s/__MARGIN_ALERT_WEBHOOK_TOKEN__/$TOKEN/" n8n/margin-alert-workflow.json > "$TMP_DIR/workflow.json"
cat > "$TMP_DIR/credential.json" <<'JSON'
[{"id": "MailpitSmtpLocal", "name": "Mailpit (local SMTP)", "type": "smtp",
  "data": {"user": "", "password": "", "host": "host.docker.internal", "port": 1025, "secure": false, "disableStartTls": true}}]
JSON

docker cp "$TMP_DIR/workflow.json" "$N8N_CONTAINER:/tmp/margin-workflow.json"
docker cp "$TMP_DIR/credential.json" "$N8N_CONTAINER:/tmp/margin-credential.json"
docker exec -u root "$N8N_CONTAINER" chown node /tmp/margin-workflow.json /tmp/margin-credential.json
docker exec "$N8N_CONTAINER" n8n import:credentials --input=/tmp/margin-credential.json
docker exec "$N8N_CONTAINER" n8n import:workflow --input=/tmp/margin-workflow.json
docker exec "$N8N_CONTAINER" n8n publish:workflow --id=MarginShieldAlrt
docker exec "$N8N_CONTAINER" rm -f /tmp/margin-workflow.json /tmp/margin-credential.json

docker restart "$N8N_CONTAINER" "$N8N_WORKER_CONTAINER" > /dev/null
echo "Waiting for n8n..."
until [ "$(curl -s -o /dev/null -w '%{http_code}' -X POST http://localhost:5678/webhook/margin-alert)" = "401" ]; do
    sleep 3
done

echo "Done. Webhook: http://localhost:5678/webhook/margin-alert  Inbox: http://localhost:8025"
