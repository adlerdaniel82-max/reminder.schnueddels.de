#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$PROJECT_ROOT/private/.env"

for command in php composer mariadb; do
  command -v "$command" >/dev/null 2>&1 || {
    echo "Fehlende Voraussetzung: $command" >&2
    exit 1
  }
done

if [[ ! -f "$ENV_FILE" ]]; then
  install -d -m 700 "$PROJECT_ROOT/private"
  cp "$PROJECT_ROOT/.env.example" "$ENV_FILE"
  chmod 600 "$ENV_FILE"
  echo "Konfiguration angelegt: $ENV_FILE"
  echo "Bitte DB_*, SMTP_* und PROJECT_API_SECRET setzen und das Skript erneut ausführen." >&2
  exit 1
fi

env_value() {
  local key="$1"
  sed -n "s/^[[:space:]]*${key}[[:space:]]*=//p" "$ENV_FILE" | tail -n 1 | sed 's/^[[:space:]]*//;s/[[:space:]]*$//'
}

for key in DB_HOST DB_NAME DB_USER DB_PASS PROJECT_API_SECRET; do
  if [[ -z "$(env_value "$key")" ]]; then
    echo "Fehlender Wert in $ENV_FILE: $key" >&2
    exit 1
  fi
done

(
  cd "$PROJECT_ROOT/public_html"
  composer install --no-dev --optimize-autoloader
)

DB_HOST_VALUE="$(env_value DB_HOST)"
DB_NAME_VALUE="$(env_value DB_NAME)"
DB_USER_VALUE="$(env_value DB_USER)"
DB_PASS_VALUE="$(env_value DB_PASS)"

MYSQL_ARGS=(--user="$DB_USER_VALUE" --password="$DB_PASS_VALUE" --database="$DB_NAME_VALUE")
if [[ "$DB_HOST_VALUE" == /* ]]; then
  MYSQL_ARGS+=(--socket="$DB_HOST_VALUE")
else
  MYSQL_ARGS+=(--host="$DB_HOST_VALUE")
fi

mariadb "${MYSQL_ARGS[@]}" < "$PROJECT_ROOT/sql/schema.sql"
mariadb "${MYSQL_ARGS[@]}" < "$PROJECT_ROOT/public_html/backend/sql/2026-04-09_views-colors.sql"

echo "Installation abgeschlossen. Für den Versand den Cronjob aus README.md einrichten."
