<?php

if (PHP_SAPI !== 'cli') {
	exit(1);
}

print "Warning fixture: 1 assertion passed\n";
trigger_error('simulated warning', E_USER_WARNING);
