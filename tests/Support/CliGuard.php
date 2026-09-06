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

final class MactrackCliGuard {
	public static function isFirstStatement($source) {
		$significant = [];

		foreach (token_get_all($source) as $token) {
			if (is_array($token) && in_array($token[0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
				continue;
			}

			$significant[] = is_array($token) ? [$token[0], $token[1]] : $token;
		}

		$expected = [
			[T_IF, 'if'],
			'(',
			[T_STRING, 'PHP_SAPI'],
			[T_IS_NOT_IDENTICAL, '!=='],
			[T_CONSTANT_ENCAPSED_STRING, "'cli'"],
			')',
			'{',
			[T_EXIT, 'exit'],
			'(',
			[T_LNUMBER, '1'],
			')',
			';',
			'}',
		];

		return array_slice($significant, 0, count($expected)) === $expected;
	}
}
