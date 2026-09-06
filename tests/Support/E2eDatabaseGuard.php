<?php

if (PHP_SAPI !== 'cli') {
	exit(1);
}

final class MactrackE2eDatabaseGuard {
	public static function isDisposable($configured_database, $expected_database) {
		return is_string($expected_database)
			&& preg_match('/^mactrack_e2e(?:_[A-Za-z0-9]+)?$/', $expected_database) === 1
			&& $configured_database === $expected_database;
	}
}
