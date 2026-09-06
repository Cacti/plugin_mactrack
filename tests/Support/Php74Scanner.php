<?php

if (PHP_SAPI !== 'cli') {
	exit(1);
}

final class MactrackPhp74Scanner {
	public static function forbiddenFunctions() {
		return [
			'str_contains',
			'str_starts_with',
			'str_ends_with',
			'array_is_list',
			'enum_exists',
		];
	}

	public static function forbiddenSyntaxTokens() {
		$tokens = [];

		foreach ([
			'T_NULLSAFE_OBJECT_OPERATOR' => 'the nullsafe operator',
			'T_MATCH' => 'a match expression',
			'T_ENUM' => 'an enum declaration',
			'T_READONLY' => 'a readonly declaration',
			'T_ATTRIBUTE' => 'an attribute',
		] as $constant => $description) {
			if (defined($constant)) {
				$tokens[constant($constant)] = $description;
			}
		}

		return $tokens;
	}

	public static function violations($source) {
		if (!is_string($source)) {
			throw new InvalidArgumentException('PHP 7.4 function analysis requires PHP source text');
		}

		$forbidden = array_flip(self::forbiddenFunctions());
		$violations = [];
		$tokens = token_get_all($source);
		$token_count = count($tokens);
		$name_tokens = [T_STRING];
		$syntax_tokens = self::forbiddenSyntaxTokens();

		if (defined('T_NAME_FULLY_QUALIFIED')) {
			$name_tokens[] = constant('T_NAME_FULLY_QUALIFIED');
		}

		for ($index = 0; $index < $token_count; $index++) {
			$token = $tokens[$index];

			if (is_array($token) && isset($syntax_tokens[$token[0]])) {
				$violations[$syntax_tokens[$token[0]]] = $syntax_tokens[$token[0]];
			}

			if (!is_array($token) || !in_array($token[0], $name_tokens, true)) {
				continue;
			}

			$name = strtolower(ltrim($token[1], '\\'));

			if (!isset($forbidden[$name])) {
				continue;
			}

			$next = $index + 1;

			while ($next < $token_count && is_array($tokens[$next]) && in_array($tokens[$next][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
				$next++;
			}

			if (($tokens[$next] ?? null) === '(') {
				$violations[$name] = $name . '()';
			}
		}

		return array_values($violations);
	}
}
