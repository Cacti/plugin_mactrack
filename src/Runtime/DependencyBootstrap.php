<?php

declare(strict_types=1);

namespace Cacti\Mactrack\Runtime;

use Throwable;

final class DependencyBootstrap {
	/**
	 * @param list<class-string>       $requiredClasses
	 * @param null|callable(string):void $reporter
	 */
	public static function load(
		string $autoloadPath,
		array $requiredClasses = ['Net_DNS2_Resolver'],
		?callable $reporter = null
	): bool {
		$reporter ??= static function (string $_message): void {
		};

		if (!is_file($autoloadPath)) {
			$reporter('Mactrack requires Composer dependencies. Run composer install --no-dev in the plugin directory.');

			return false;
		}

		try {
			require_once $autoloadPath;
		} catch (Throwable $error) {
			$reporter('Mactrack could not load Composer dependencies: ' . $error->getMessage());

			return false;
		}

		foreach ($requiredClasses as $requiredClass) {
			if (!class_exists($requiredClass)) {
				$reporter('Mactrack Composer dependency is unavailable: ' . $requiredClass . '. Run composer install --no-dev in the plugin directory.');

				return false;
			}
		}

		return true;
	}
}
