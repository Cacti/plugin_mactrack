<?php

declare(strict_types=1);

use Cacti\Mactrack\Runtime\DependencyBootstrap;

beforeAll(function (): void {
	require_once __DIR__ . '/../../../../src/Runtime/DependencyBootstrap.php';
});

it('reports a missing Composer autoloader', function (): void {
	$messages = [];

	$loaded = DependencyBootstrap::load(
		__DIR__ . '/fixtures/does-not-exist.php',
		['MactrackMissingDependency'],
		static function (string $message) use (&$messages): void {
			$messages[] = $message;
		}
	);

	expect($loaded)->toBeFalse()
		->and($messages)->toBe([
			'Mactrack requires Composer dependencies. Run composer install --no-dev in the plugin directory.',
		]);
});

it('fails quietly when no reporter is supplied', function (): void {
	expect(DependencyBootstrap::load(
		__DIR__ . '/fixtures/does-not-exist.php',
		['MactrackMissingDependency']
	))->toBeFalse();
});

it('reports an autoloader failure', function (): void {
	$messages = [];

	$loaded = DependencyBootstrap::load(
		__DIR__ . '/fixtures/throwing-autoload.php',
		['MactrackThrowingDependency'],
		static function (string $message) use (&$messages): void {
			$messages[] = $message;
		}
	);

	expect($loaded)->toBeFalse()
		->and($messages)->toBe(['Mactrack could not load Composer dependencies: fixture failure']);
});

it('reports an unavailable required class', function (): void {
	$messages = [];

	$loaded = DependencyBootstrap::load(
		__DIR__ . '/fixtures/empty-autoload.php',
		['MactrackUnavailableDependency'],
		static function (string $message) use (&$messages): void {
			$messages[] = $message;
		}
	);

	expect($loaded)->toBeFalse()
		->and($messages)->toBe([
			'Mactrack Composer dependency is unavailable: MactrackUnavailableDependency. Run composer install --no-dev in the plugin directory.',
		]);
});

it('loads every required dependency', function (): void {
	$loaded = DependencyBootstrap::load(
		__DIR__ . '/fixtures/working-autoload.php',
		['MactrackFixtureDependency']
	);

	expect($loaded)->toBeTrue()
		->and(class_exists('MactrackFixtureDependency'))->toBeTrue();
});
