<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * build_InterfacesTable() and mactrack_find_host_graphs() assemble their
 * INSERT statements as a column list plus a separately concatenated value
 * tuple, so nothing but position ties a value to its column. These tests
 * pair the two back up and check each value landed in the column named for
 * it.
 *
 * The SNMP walk and the few Cacti helpers below are not in the shared
 * bootstrap. array_rekey() and the db_qstr() fallback mirror Cacti's
 * lib/functions.php and lib/database.php.
 */

if (!defined('SNMP_POLLER')) {
	define('SNMP_POLLER', 0);
}

if (!defined('SNMP_STRING_OUTPUT_GUESS')) {
	define('SNMP_STRING_OUTPUT_GUESS', 1);
}

if (!defined('SNMP_STRING_OUTPUT_HEX')) {
	define('SNMP_STRING_OUTPUT_HEX', 3);
}

if (!defined('POLLER_VERBOSITY_HIGH')) {
	define('POLLER_VERBOSITY_HIGH', 4);
}

beforeAll(function () {
	require_once dirname(__DIR__, 2) . '/lib/mactrack_functions.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']        = array();
	$GLOBALS['__test_next_returns']    = array();
	$GLOBALS['__test_matched_returns'] = array();
	$GLOBALS['__test_config_options']  = array('mt_collection_timing' => 'disabled');
	$GLOBALS['__test_snmp_walk']       = array();
});

/**
 * Return the first db_execute_prepared() statement that starts with $prefix.
 *
 * @param string $prefix
 *
 * @return array
 */
function mactrack_test_find_insert($prefix) {
	foreach ($GLOBALS['__test_db_calls'] as $call) {
		if ($call['fn'] === 'db_execute_prepared' && strpos(ltrim($call['sql']), $prefix) === 0) {
			return $call;
		}
	}

	throw new RuntimeException("No db_execute_prepared() call starting with $prefix");
}

/**
 * Split a single-row INSERT into column => value.
 *
 * @param array $call
 *
 * @return array<string, string>
 */
function mactrack_test_insert_row($call) {
	preg_match('/^\s*INSERT INTO \w+\s*\(([^)]*)\)\s*VALUES\s*\((.*?)\)/s', $call['sql'], $parts);
	$columns = array_map('trim', explode(',', $parts[1]));
	$values  = [];
	$index   = 0;

	foreach (array_map('trim', explode(',', $parts[2])) as $value) {
		$values[] = $value === '?' ? (string) $call['params'][$index++] : $value;
	}

	expect($index)->toBe(cacti_sizeof($call['params']));
	expect(cacti_sizeof($values))->toBe(cacti_sizeof($columns));

	return array_combine($columns, $values);
}

test('interface counter deltas are stored in the columns named for them', function () {
	$counters = array(
		'ifInOctets'         => '.1.3.6.1.2.1.2.2.1.10',
		'ifOutOctets'        => '.1.3.6.1.2.1.2.2.1.16',
		'ifHCInOctets'       => '.1.3.6.1.2.1.31.1.1.1.6',
		'ifHCOutOctets'      => '.1.3.6.1.2.1.31.1.1.1.10',
		'ifInUcastPkts'      => '.1.3.6.1.2.1.2.2.1.11',
		'ifOutUcastPkts'     => '.1.3.6.1.2.1.2.2.1.17',
		'ifInDiscards'       => '.1.3.6.1.2.1.2.2.1.13',
		'ifInErrors'         => '.1.3.6.1.2.1.2.2.1.14',
		'ifInUnknownProtos'  => '.1.3.6.1.2.1.2.2.1.15',
		'ifOutDiscards'      => '.1.3.6.1.2.1.2.2.1.19',
		'ifOutErrors'        => '.1.3.6.1.2.1.2.2.1.20',
		'ifInMulticastPkts'  => '.1.3.6.1.2.1.31.1.1.1.2',
		'ifOutMulticastPkts' => '.1.3.6.1.2.1.31.1.1.1.4',
		'ifInBroadcastPkts'  => '.1.3.6.1.2.1.31.1.1.1.3',
		'ifOutBroadcastPkts' => '.1.3.6.1.2.1.31.1.1.1.5',
	);

	// Each counter grows by a different multiple of 1000 since the last scan.
	$previous = array(
		'ifIndex'        => 1,
		'ifOperStatus'   => 1,
		'ifSpeed'        => 1000000000,
		'ifHighSpeed'    => 1000,
		'last_up_time'   => '2026-01-01 00:00:00',
		'last_down_time' => '2026-01-01 00:00:00',
		'stateChanges'   => 0,
	);
	$step = array();
	$k    = 1;

	foreach ($counters as $name => $oid) {
		$step[$name]                            = $k;
		$previous[$name]                        = 50000;
		$GLOBALS['__test_snmp_walk'][$oid] = [['oid' => $oid . '.1', 'value' => 50000 + $k * 1000]];
		$k++;
	}

	$GLOBALS['__test_snmp_walk']['.1.3.6.1.2.1.2.2.1.1'] = [['oid' => '.1.3.6.1.2.1.2.2.1.1.1', 'value' => 1]];
	$GLOBALS['__test_snmp_walk']['.1.3.6.1.2.1.31.1.1.1.1'] = [['oid' => '.1.3.6.1.2.1.31.1.1.1.1.1', 'value' => 'Gi1/0/1']];
	$GLOBALS['__test_snmp_walk']['.1.3.6.1.2.1.2.2.1.8'] = [['oid' => '.1.3.6.1.2.1.2.2.1.8.1', 'value' => 1]];

	// Only db_fetch_cell_prepared() matches on SQL, so the remaining stubs answer in call order.
	mactrack_test_queue_return('db_fetch_assoc_prepared', array($previous));

	$device = array(
		'site_id'              => 1,
		'device_id'            => 7,
		'host_id'              => 0,
		'hostname'             => '192.0.2.1',
		'snmp_sysUptime'       => 100,
		'snmp_readstring'      => 'public',
		'snmp_version'         => 2,
		'snmp_context'         => '',
		'snmp_username'        => '',
		'snmp_password'        => '',
		'snmp_auth_protocol'   => '',
		'snmp_priv_passphrase' => '',
		'snmp_priv_protocol'   => '',
		'snmp_engine_id'       => '',
		'snmp_port'            => 161,
		'snmp_timeout'         => 500,
		'snmp_retries'         => 0,
		'max_oids'             => 10,
		'last_rundate'         => date('Y-m-d H:i:s', time() - 300),
	);
	$ifIndexes = array();

	build_InterfacesTable($device, $ifIndexes, false, false);

	$row = mactrack_test_insert_row(mactrack_test_find_insert('INSERT INTO mac_track_interfaces'));

	// The scan interval divides every delta alike, so compare against ifInOctets (step 1).
	$base = (float) $row['int_ifInOctets'];

	expect($base)->toBeGreaterThan(0.0);

	$stored = array();

	foreach ($step as $name => $expected) {
		$stored[$name] = (int) round((float) $row['int_' . $name] / $base);
	}

	expect($stored)->toBe($step);
});

test('interface graph rows keep the graph template and data query ids in their own columns', function () {
	mactrack_test_queue_return('db_fetch_assoc_prepared', array(
		array('id' => 11, 'snmp_query_id' => 5, 'field_name' => 'ifName', 'field_value' => 'Gi1/0/1'),
	));
	mactrack_test_queue_return('db_fetch_assoc_prepared', array(
		array('id' => 21, 'graph_template_id' => 9),
	));
	mactrack_test_queue_return('db_fetch_assoc', array(
		array('device_id' => 7, 'ifIndex' => 1, 'ifName' => 'Gi1/0/1'),
	));

	mactrack_find_host_graphs(7, 3);

	$row = mactrack_test_insert_row(mactrack_test_find_insert('INSERT INTO mac_track_interface_graphs'));

	expect($row['local_graph_id'])->toBe('21');
	expect($row['device_id'])->toBe('7');
	expect($row['host_id'])->toBe('3');
	expect($row['graph_template_id'])->toBe('9');
	expect($row['snmp_query_id'])->toBe('5');
});
