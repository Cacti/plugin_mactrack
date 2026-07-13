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
	-e "s/\$database_default *=.*/\$database_default  = 'cacti';/" \
	-e "s/\$database_username *=.*/\$database_username = 'cacti';/" \
	-e "s/\$database_password *=.*/\$database_password = 'mactrack-test';/" \
	"$CACTI_PATH/include/config.php"

test -f "$CACTI_PATH/plugins/mactrack/vendor/autoload.php"
php "$CACTI_PATH/cli/install_cacti.php" --accept-eula --install --force
php "$CACTI_PATH/cli/plugin_manage.php" --plugin=mactrack --install --enable --allperms
php "$CACTI_PATH/plugins/mactrack/tests/e2e/mactrack_smoke.php"
