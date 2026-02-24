#!/usr/bin/env bash
set -euo pipefail

if ! command -v docker >/dev/null 2>&1; then
  echo "docker is required to run MySQL tests." >&2
  exit 1
fi

MYSQL_IMAGE="${MYSQL_IMAGE:-mysql:8.0}"
MYSQL_DATABASE="${MYSQL_DATABASE:-lqbc_test}"
MYSQL_USERNAME="${MYSQL_USERNAME:-lqbc}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-lqbc}"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-root}"
MYSQL_PORT="${MYSQL_PORT:-3307}"

CONTAINER_NAME="lqbc-mysql-${$}"

cleanup() {
  docker stop "${CONTAINER_NAME}" >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker run --rm -d \
  --name "${CONTAINER_NAME}" \
  -e MYSQL_DATABASE="${MYSQL_DATABASE}" \
  -e MYSQL_USER="${MYSQL_USERNAME}" \
  -e MYSQL_PASSWORD="${MYSQL_PASSWORD}" \
  -e MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD}" \
  -p "${MYSQL_PORT}:3306" \
  "${MYSQL_IMAGE}" >/dev/null

echo "Waiting for MySQL to be ready..."
for _ in {1..60}; do
  if docker exec "${CONTAINER_NAME}" mysqladmin ping --protocol=TCP -h 127.0.0.1 -P 3306 -uroot -p"${MYSQL_ROOT_PASSWORD}" --silent >/dev/null 2>&1; then
    break
  fi
  sleep 1
done

if ! docker exec "${CONTAINER_NAME}" mysqladmin ping --protocol=TCP -h 127.0.0.1 -P 3306 -uroot -p"${MYSQL_ROOT_PASSWORD}" --silent >/dev/null 2>&1; then
  echo "MySQL did not become ready in time." >&2
  exit 1
fi

MYSQL_HOST=127.0.0.1 \
MYSQL_PORT="${MYSQL_PORT}" \
MYSQL_DATABASE="${MYSQL_DATABASE}" \
MYSQL_USERNAME="${MYSQL_USERNAME}" \
MYSQL_PASSWORD="${MYSQL_PASSWORD}" \
php vendor/bin/phpunit
