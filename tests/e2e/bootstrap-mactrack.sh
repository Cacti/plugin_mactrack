#!/usr/bin/env bash
set -euo pipefail

CACTI_PATH=/var/www/html/cacti

mkdir -p "$CACTI_PATH/cache/boost" "$CACTI_PATH/cache/mibcache" "$CACTI_PATH/cache/realtime" "$CACTI_PATH/cache/spikekill"
chown -R www-data:www-data "$CACTI_PATH/cache" "$CACTI_PATH/log" "$CACTI_PATH/rra"

# The Cacti source checkout can have been used by another disposable suite.
# Start from the distributed configuration so credentials cannot leak between
# independently named Compose projects.
cp "$CACTI_PATH/include/config.php.dist" "$CACTI_PATH/include/config.php"
sed -i \
	-e "s/\$database_hostname *=.*/\$database_hostname = 'db';/" \
	-e "s/\$database_default *=.*/\$database_default  = getenv('DB_NAME');/" \
	-e "s/\$database_username *=.*/\$database_username = getenv('DB_USER');/" \
	-e "s/\$database_password *=.*/\$database_password = getenv('DB_PASS');/" \
	"$CACTI_PATH/include/config.php"

test -f "$CACTI_PATH/plugins/mactrack/vendor/autoload.php"

# Import through a SQL client so client-side directives in cacti.sql work on
# both MariaDB and MySQL. The database container's init-file path sends the
# file directly to MySQL and cannot interpret DELIMITER.
MYSQL_PWD="$DB_PASS" mysql \
	--host="$DB_HOST" \
	--port="$DB_PORT" \
	--user="$DB_USER" \
	"$DB_NAME" < "$CACTI_PATH/cacti.sql"

# The plugin lifecycle does not need Cacti's optional device-template imports.
# Explicitly skip them to keep this disposable install focused and fast enough
# for CI while preserving the normal core install and plugin-management paths.
template_args=()
for template in "$CACTI_PATH"/install/templates/*.xml.gz; do
	template_args+=("--template=$(basename "$template"):0")
done

php "$CACTI_PATH/cli/install_cacti.php" --accept-eula --install --force "${template_args[@]}"
php "$CACTI_PATH/cli/plugin_manage.php" --plugin=mactrack --install --enable --allperms

for destructive_test in mactrack_scanning_functions.php mactrack_schema_idempotency.php mactrack_concurrent_default_site.php; do
	if MACTRACK_E2E=1 MACTRACK_E2E_EXPECT_DB=cacti php "$CACTI_PATH/plugins/mactrack/tests/e2e/$destructive_test" >/dev/null 2>&1; then
		guard_status=0
	else
		guard_status=$?
	fi

	if [[ "$guard_status" -ne 2 ]]; then
		echo "$destructive_test did not reject a non-E2E database" >&2
		exit 1
	fi
done

MACTRACK_E2E=1 MACTRACK_E2E_EXPECT_DB="$DB_NAME" php "$CACTI_PATH/plugins/mactrack/tests/e2e/mactrack_scanning_functions.php"
MACTRACK_E2E=1 MACTRACK_E2E_EXPECT_DB="$DB_NAME" php "$CACTI_PATH/plugins/mactrack/tests/e2e/mactrack_schema_idempotency.php"
MACTRACK_E2E=1 MACTRACK_E2E_EXPECT_DB="$DB_NAME" php "$CACTI_PATH/plugins/mactrack/tests/e2e/mactrack_concurrent_default_site.php"
php "$CACTI_PATH/plugins/mactrack/tests/e2e/mactrack_smoke.php"
