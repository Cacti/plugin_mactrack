<?php

if (PHP_SAPI !== 'cli') {
	exit(1);
}
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

final class MactrackStandaloneTest {
	private static $assertions = 0;

	public static function assertSame($expected, $actual, $message) {
		self::$assertions++;

		if ($expected !== $actual) {
			self::fail($message . '; expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
		}
	}

	public static function assertTrue($condition, $message) {
		self::$assertions++;

		if ($condition !== true) {
			self::fail($message);
		}
	}

	public static function assertContains($needle, $haystack, $message) {
		self::$assertions++;

		if (strpos((string) $haystack, (string) $needle) === false) {
			self::fail($message . '; missing ' . var_export($needle, true));
		}
	}

	public static function finish($name) {
		if (self::$assertions === 0) {
			self::fail("$name registered no assertions");
		}

		print $name . ': ' . self::$assertions . " assertions passed\n";
	}

	private static function fail($message) {
		fwrite(STDERR, "FAIL: $message\n");
		exit(1);
	}
}
