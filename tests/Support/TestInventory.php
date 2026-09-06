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

final class MactrackTestInventory {
	public static function findUnclaimed(array $roots, array $claimed, array $excluded = []) {
		$claimed = array_flip($claimed);
		$excluded = array_flip($excluded);
		$unclaimed = [];

		foreach ($roots as $root) {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
			);

			foreach ($iterator as $file) {
				$path = $file->getPathname();

				if ($file->getExtension() === 'php' && !isset($claimed[$path]) && !isset($excluded[$path])) {
					$unclaimed[] = $path;
				}
			}
		}

		sort($unclaimed);

		return $unclaimed;
	}
}
