<?php

if (PHP_SAPI !== 'cli') {
	exit(1);
}

require_once __DIR__ . '/../Support/StandaloneTest.php';
require_once __DIR__ . '/../Support/E2eDatabaseGuard.php';

MactrackStandaloneTest::assertTrue(MactrackE2eDatabaseGuard::isDisposable('mactrack_e2e', 'mactrack_e2e'), 'the dedicated default E2E database is accepted');
MactrackStandaloneTest::assertTrue(MactrackE2eDatabaseGuard::isDisposable('mactrack_e2e_42', 'mactrack_e2e_42'), 'a dedicated per-run E2E database is accepted');
MactrackStandaloneTest::assertTrue(!MactrackE2eDatabaseGuard::isDisposable('cacti', 'cacti'), 'a matching production-style database is rejected');
MactrackStandaloneTest::assertTrue(!MactrackE2eDatabaseGuard::isDisposable('cacti', 'mactrack_e2e'), 'a configured/expected mismatch is rejected');
MactrackStandaloneTest::assertTrue(!MactrackE2eDatabaseGuard::isDisposable('mactrack_e2e', ''), 'an empty expected database is rejected');
foreach (["mactrack_e2e_feature/branch", 'mactrack_e2e_name&other', "mactrack_e2e_name'other"] as $unsafe_database) {
	MactrackStandaloneTest::assertTrue(!MactrackE2eDatabaseGuard::isDisposable($unsafe_database, $unsafe_database), 'a database name containing configuration metacharacters is rejected');
}

$runner = file_get_contents(__DIR__ . '/../e2e/run-mactrack-e2e.sh');
$bootstrap = file_get_contents(__DIR__ . '/../e2e/bootstrap-mactrack.sh');
$compose = file_get_contents(__DIR__ . '/../e2e/docker-compose.yml');
MactrackStandaloneTest::assertTrue($runner !== false, 'the E2E runner is readable');
MactrackStandaloneTest::assertTrue($bootstrap !== false, 'the E2E bootstrap is readable');
MactrackStandaloneTest::assertTrue($compose !== false, 'the E2E Compose definition is readable');
MactrackStandaloneTest::assertContains(': "${DB_USER:=cacti}"', $runner, 'the E2E runner defines the same default database user as Compose');
MactrackStandaloneTest::assertContains(': "${DB_PASSWORD:=mactrack-test}"', $runner, 'the E2E runner defines the same default database password as Compose');
MactrackStandaloneTest::assertContains('-e MYSQL_PWD="$DB_PASSWORD"', $runner, 'the readiness probe passes the overridden password without exposing it as an argument');
MactrackStandaloneTest::assertContains('--user="$DB_USER"', $runner, 'the readiness probe honors the database user override');
MactrackStandaloneTest::assertContains("--execute='SELECT 1'", $runner, 'the readiness probe verifies authenticated query access rather than accepting an access-denied ping');
MactrackStandaloneTest::assertContains("\$database_default  = getenv('DB_NAME');", $bootstrap, 'the generated Cacti config reads the database name without source interpolation');
MactrackStandaloneTest::assertContains("\$database_username = getenv('DB_USER');", $bootstrap, 'the generated Cacti config reads the database user without source interpolation');
MactrackStandaloneTest::assertContains("\$database_password = getenv('DB_PASS');", $bootstrap, 'the generated Cacti config reads the database password without source interpolation');
MactrackStandaloneTest::assertTrue(strpos($runner, '--password=') === false, 'the E2E runner does not expose the database password as a process argument');
MactrackStandaloneTest::assertContains('MYSQL_PWD="$${MYSQL_PASSWORD}" mysql --protocol=tcp', $compose, 'the database healthcheck scopes the overridden password to an authenticated client query');
MactrackStandaloneTest::assertContains('--execute="SELECT 1"', $compose, 'the Compose healthcheck cannot succeed before the configured application user exists');
MactrackStandaloneTest::assertContains('--transaction-isolation=READ-COMMITTED', $compose, 'the database matrix pins the concurrency check to READ COMMITTED');
MactrackStandaloneTest::assertTrue(strpos($compose, '      MYSQL_PWD:') === false, 'the database password is not exported globally where it can disrupt the MySQL entrypoint');
MactrackStandaloneTest::assertContains('--user="$${MYSQL_USER}"', $compose, 'the database healthcheck uses the overridden user without embedding the password');
MactrackStandaloneTest::assertTrue(strpos($compose, '-p${DB_PASSWORD') === false, 'the Compose healthcheck does not embed the password in its command');

foreach (['mactrack_scanning_functions.php', 'mactrack_schema_idempotency.php', 'mactrack_concurrent_default_site.php'] as $destructive_test) {
	$source = file_get_contents(__DIR__ . '/../e2e/' . $destructive_test);
	MactrackStandaloneTest::assertTrue($source !== false, $destructive_test . ' is readable');
	MactrackStandaloneTest::assertContains("getenv('MACTRACK_E2E_EXPECT_DB')", $source, $destructive_test . ' keeps the expected guard target separate from the live Cacti DSN');
}
MactrackStandaloneTest::assertContains('MACTRACK_E2E_EXPECT_DB=cacti', $bootstrap, 'negative guard probes leave the live Cacti DSN unchanged');
MactrackStandaloneTest::assertTrue(strpos($bootstrap, 'MACTRACK_E2E=1 DB_NAME=cacti') === false, 'negative guard probes never repoint the live Cacti DSN');
MactrackStandaloneTest::finish('MacTrack disposable-database guard');
