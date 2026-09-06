<?php

if (PHP_SAPI !== 'cli') {
	exit(1);
}

fwrite(STDERR, str_repeat('x', 256 * 1024));
exit(1);
