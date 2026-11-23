#!/usr/bin/env bash
# Entorno de revisión aislado de Tenisline (ver docs/GUIA-BACKEND.md §4).
#
#   APP_DIR=/ruta/al/worktree scripts/revision/revision-local.sh up     -> copia la BD y levanta la app SIN internet
#   APP_DIR=/ruta/al/worktree scripts/revision/revision-local.sh down   -> apaga y borra todo (tu BD original no se toca)
#   APP_DIR=/ruta/al/worktree scripts/revision/revision-local.sh status
#
# APP_DIR: carpeta con el código a revisar (un git worktree aparte, con vendor y npm run build).
# SRC_ENV: .env de donde salen DB_PASSWORD y las demás variables (por defecto el de la carpeta
#          principal Tenisline-Admin, junto a APP_DIR). Nunca puede ser el mismo .env de APP_DIR.
# Admin/tienda: http://localhost:3002
#
# Seguridad: la app corre en una red Docker --internal (sin internet), así que Twilio,
# Guatex, FEL, S3 y correo no pueden enviar nada. Además las llaves van falsas en el .env.
# Trabaja sobre una COPIA de la BD (volumen tl-rev-db-data).
set -euo pipefail

: "${APP_DIR:?Indica APP_DIR=/ruta/al/worktree a revisar}"
APP_DIR="$(cd "$APP_DIR" && pwd)"
SRC_ENV="${SRC_ENV:-$(cd "$APP_DIR/.." && pwd)/Tenisline-Admin/.env}"
MARCA="# REVISION-LOCAL: .env generado por revision-local.sh (llaves externas falsas)"
if [ "$(cd "$(dirname "$SRC_ENV")" && pwd)/.env" = "$APP_DIR/.env" ]; then
  echo "SRC_ENV no puede ser el .env de APP_DIR (se sobrescribiría tu .env real)."; exit 1
fi
if [ -f "$APP_DIR/.env" ] && ! grep -q "^$MARCA" "$APP_DIR/.env"; then
  echo "$APP_DIR/.env ya existe y no lo generó este script: no se toca. Usa un worktree aparte."; exit 1
fi
SRC_VOL="tenisline-admin_sail-mariadb"
SRC_DB="tenisline-admin-mariadb-1"
PORT=3002

up() {
  echo "1/7 Copiando tu BD local..."
  local was_running=0
  if [ "$(docker inspect -f '{{.State.Running}}' "$SRC_DB" 2>/dev/null)" = "true" ]; then was_running=1; docker stop "$SRC_DB" >/dev/null; fi
  docker rm -f tl-rev-db >/dev/null 2>&1 || true
  docker volume rm tl-rev-db-data >/dev/null 2>&1 || true
  docker volume create tl-rev-db-data >/dev/null
  docker run --rm -v "$SRC_VOL":/from:ro -v tl-rev-db-data:/to alpine sh -c 'cp -a /from/. /to/'
  [ "$was_running" = 1 ] && docker start "$SRC_DB" >/dev/null

  echo "2/7 Red aislada (sin internet)..."
  docker network create --internal tl-rev-net >/dev/null 2>&1 || true
  docker network create tl-rev-pub >/dev/null 2>&1 || true

  echo "3/7 MariaDB, Redis y Meilisearch de revisión..."
  local dbpass
  dbpass="$(grep -E '^DB_PASSWORD=' "$SRC_ENV" | cut -d= -f2-)"
  docker rm -f tl-rev-redis tl-rev-meili >/dev/null 2>&1 || true
  docker run -d --name tl-rev-db --network tl-rev-net -e MARIADB_ROOT_PASSWORD="$dbpass" \
    -v tl-rev-db-data:/var/lib/mysql mariadb:11 >/dev/null
  docker run -d --name tl-rev-redis --network tl-rev-net redis:alpine >/dev/null
  docker run -d --name tl-rev-meili --network tl-rev-net -e MEILI_MASTER_KEY=revision-key \
    -e MEILI_NO_ANALYTICS=true getmeili/meilisearch:v1.12 >/dev/null
  for _ in $(seq 1 60); do
    docker exec tl-rev-db healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1 && break; sleep 2
  done

  echo "4/7 .env de revisión (llaves externas falsas)..."
  { echo "$MARCA"; cat "$SRC_ENV"; } > "$APP_DIR/.env"
  python3 - "$APP_DIR/.env" "$PORT" <<'PY'
import sys, re
p, port = sys.argv[1], sys.argv[2]
s = open(p).read()
fake = 'revision-desactivado'
vals = {
  'APP_URL': f'http://localhost:{port}', 'DB_HOST': 'tl-rev-db', 'DB_PORT': '3306',
  'REDIS_HOST': 'tl-rev-redis', 'MEILISEARCH_HOST': 'http://tl-rev-meili:7700',
  'MEILISEARCH_KEY': 'revision-key', 'QUEUE_CONNECTION': 'sync',
  'MAIL_MAILER': 'log', 'MAIL_HOST': '127.0.0.1', 'MAIL_USERNAME': '', 'MAIL_PASSWORD': '',
  'AWS_ACCESS_KEY_ID': fake, 'AWS_SECRET_ACCESS_KEY': fake,
  'TWILIO_ACCOUNT_SID': 'AC' + '0' * 32, 'TWILIO_AUTH_TOKEN': fake,
  'GUATEX_URL_GUIAS': 'http://127.0.0.1:9/',
  'POSTMARK_TOKEN': '', 'RESEND_KEY': '', 'SLACK_BOT_USER_OAUTH_TOKEN': '',
}
# Guatex y FEL tienen valores por defecto reales en config/services.php: hay que poner algo falso.
for k in re.findall(r'^#?\s*((?:GUATEX|FEL)_[A-Z_]+)=', s, re.M):
    vals.setdefault(k, fake)
for k in ['FEL_USUARIO_API', 'FEL_LLAVE_API', 'FEL_USUARIO_FIRMA', 'FEL_IDENTIFICADOR', 'FEL_LLAVE_FIRMA',
          'GUATEX_USUARIO', 'GUATEX_PASSWORD', 'GUATEX_PASSWORD_MUNICIPIOS', 'GUATEX_CODIGO_COBRO',
          'GUATEX_CODIGO_COBRO_COD', 'GUATEX_CODIGO_COBRO_CAPITAL', 'GUATEX_CODIGO_COBRO_COD_CAPITAL']:
    vals.setdefault(k, fake)
for k, v in vals.items():
    if re.search(rf'^{k}=', s, re.M): s = re.sub(rf'^{k}=.*$', f'{k}={v}', s, flags=re.M)
    else: s += f'\n{k}={v}'
open(p, 'w').write(s.rstrip('\n') + '\n')
PY

  echo "5/7 App (PHP 8.4)..."
  docker rm -f tl-rev-app tl-rev-edge >/dev/null 2>&1 || true
  docker run -d --name tl-rev-app --network tl-rev-net --entrypoint "" -v "$APP_DIR":/var/www/html -w /var/www/html \
    -e XDEBUG_MODE=off -e PHP_CLI_SERVER_WORKERS=8 sail-8.4/app \
    php -d memory_limit=1G artisan serve --host=0.0.0.0 --port=80 >/dev/null
  sleep 3

  echo "6/7 Pasos del deploy (migrate, caches, índice de búsqueda)..."
  docker exec tl-rev-app php artisan optimize:clear >/dev/null
  docker exec tl-rev-app php artisan migrate --force
  docker exec tl-rev-app php artisan storage:link >/dev/null 2>&1 || true
  docker exec tl-rev-app sh -c 'php artisan scout:sync-index-settings >/dev/null 2>&1 || true'
  for m in $(cd "$APP_DIR" && grep -lE 'use Searchable;' app/Models/*.php | sed 's#app/Models/##;s#\.php##'); do
    docker exec tl-rev-app php artisan scout:import "App\\Models\\$m" >/dev/null 2>&1 || echo "  (no se indexó $m)"
  done

  echo "7/7 Publicando en http://localhost:$PORT ..."
  docker run -d --name tl-rev-edge --network tl-rev-pub -p 127.0.0.1:$PORT:$PORT alpine/socat \
    tcp-listen:$PORT,fork,reuseaddr tcp-connect:tl-rev-app:80 >/dev/null
  docker network connect tl-rev-net tl-rev-edge
  sleep 2
  status
}

down() {
  docker rm -f tl-rev-edge tl-rev-app tl-rev-base tl-rev-meili tl-rev-redis tl-rev-db >/dev/null 2>&1 || true
  docker network rm tl-rev-net tl-rev-pub >/dev/null 2>&1 || true
  docker volume rm tl-rev-db-data >/dev/null 2>&1 || true
  grep -q "^$MARCA" "$APP_DIR/.env" 2>/dev/null && rm -f "$APP_DIR/.env"
  echo "Revisión apagada, copia de BD y .env de revisión borrados."
}

status() {
  docker ps --filter name=tl-rev- --format '{{.Names}}\t{{.Status}}'
  printf 'Admin http://localhost:%s/admin -> ' "$PORT"; curl -s -o /dev/null -w '%{http_code}\n' -m 15 "http://localhost:$PORT/admin/login" || echo "sin respuesta"
  printf 'Internet desde la app -> '
  docker exec tl-rev-app sh -c 'curl -s -m 5 -o /dev/null https://api.twilio.com && echo "ABIERTO (revisar)" || echo "bloqueado"' 2>/dev/null || echo "-"
}

case "${1:-}" in
  up) up ;;
  down) down ;;
  status) status ;;
  *) echo "Uso: $0 up|down|status"; exit 1 ;;
esac
