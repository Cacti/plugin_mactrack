#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT="mactrack_e2e_$$"

: "${CACTI_SOURCE:?Set CACTI_SOURCE to a Cacti source checkout}"
: "${MACTRACK_SOURCE:=$(cd "$SCRIPT_DIR/../.." && pwd)}"
: "${DB_USER:=cacti}"
: "${DB_PASSWORD:=mactrack-test}"
export CACTI_SOURCE MACTRACK_SOURCE

cleanup() {
	cd "$SCRIPT_DIR"
	docker compose -p "$PROJECT" down -v --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT

cd "$SCRIPT_DIR"
docker compose -p "$PROJECT" up -d --build

database_ready=false

for _ in $(seq 1 36); do
	if docker compose -p "$PROJECT" exec -T -e MYSQL_PWD="$DB_PASSWORD" db mysql --protocol=tcp -h 127.0.0.1 --user="$DB_USER" --execute='SELECT 1' --silent; then
		database_ready=true
		break
	fi
	sleep 5
done

if [[ "$database_ready" != true ]]; then
	echo "Mactrack test database did not become ready" >&2
	exit 1
fi

docker compose -p "$PROJECT" exec -T web bash /var/www/html/cacti/plugins/mactrack/tests/e2e/bootstrap-mactrack.sh
curl --fail --silent --show-error "http://localhost:${MACTRACK_E2E_PORT:-18082}/cacti/plugins/mactrack/mactrack_view_devices.php" >/dev/null
