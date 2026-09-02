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

composer install \
	--working-dir="$CACTI_PATH/plugins/mactrack" \
	--no-dev \
	--prefer-dist \
	--no-progress \
	--no-interaction
test -f "$CACTI_PATH/plugins/mactrack/vendor/autoload.php"

php "$CACTI_PATH/cli/install_cacti.php" --accept-eula --install --force
php "$CACTI_PATH/cli/plugin_manage.php" --plugin=mactrack --install --enable --allperms
php "$CACTI_PATH/plugins/mactrack/tests/e2e/mactrack_smoke.php"

# Prove the installed plugin can reach a real SNMP agent through the same
# scanner entry point launched by the Mactrack poller in production.
snmpget -v2c -c public -On snmp-agent .1.3.6.1.2.1.1.2.0
device_id="$(php "$CACTI_PATH/plugins/mactrack/tests/e2e/mactrack_production_probe.php" seed)"
php "$CACTI_PATH/plugins/mactrack/mactrack_scanner.php" "-id=$device_id" --debug -t
php "$CACTI_PATH/plugins/mactrack/tests/e2e/mactrack_production_probe.php" assert "$device_id"

# The master poller must launch the scanner successfully too; this checks the
# actual scheduler-to-worker boundary rather than a test-only function call.
php "$CACTI_PATH/plugins/mactrack/poller_mactrack.php" -sid=1 --force --debug
php "$CACTI_PATH/plugins/mactrack/tests/e2e/mactrack_production_probe.php" assert "$device_id"

# Exercise the resolver entry point from an installed-tree copy without
# Composer output. It must fail closed with an actionable error, never fatal.
missing_tree=/tmp/cacti-mactrack-missing-dependencies
cp -a "$CACTI_PATH" "$missing_tree"
rm -rf "$missing_tree/plugins/mactrack/vendor"
set +e
resolver_output="$(cd "$missing_tree/plugins/mactrack" && php mactrack_resolver.php 2>&1)"
resolver_status=$?
set -e

if [ "$resolver_status" -ne 1 ]; then
	printf 'Resolver returned %s instead of 1 without Composer dependencies\n%s\n' "$resolver_status" "$resolver_output" >&2
	exit 1
fi

if ! grep -q 'requires Composer dependencies' <<<"$resolver_output"; then
	printf 'Resolver did not emit the dependency remediation message\n%s\n' "$resolver_output" >&2
	exit 1
fi

if grep -q 'Fatal error' <<<"$resolver_output"; then
	printf 'Resolver fatally crashed without Composer dependencies\n%s\n' "$resolver_output" >&2
	exit 1
fi
