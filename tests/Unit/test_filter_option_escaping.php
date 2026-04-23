<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$payload = 'device"><script>alert(1)</script>';
$escaped = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');

if (strpos($escaped, '<script>') === false && strpos($escaped, '&lt;script&gt;') !== false) {
	print "OK\n";
	exit(0);
}

fwrite(STDERR, "Expected option label content to be escaped\n");
exit(1);
