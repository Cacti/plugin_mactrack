<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/lib/mactrack_functions.php';

/*
 * MACs learned on the bridge root port, or on a port that carries an IP
 * address, belong to the rest of the network and are not user ports.
 */
beforeEach(function () {
	$GLOBALS['__test_next_returns'] = array();
	$GLOBALS['__test_config_options']['mt_ignorePorts_delim'] = '-1';

	$this->device = array(
		'device_id' => 1, 'host_id' => 0, 'site_id' => 1, 'device_name' => 'sw1', 'hostname' => '192.0.2.1',
		'snmp_readstring' => 'x', 'snmp_version' => 2, 'snmp_username' => '', 'snmp_password' => '',
		'snmp_auth_protocol' => '', 'snmp_priv_passphrase' => '', 'snmp_priv_protocol' => '',
		'snmp_context' => '', 'snmp_engine_id' => '', 'snmp_port' => 161, 'snmp_timeout' => 500,
		'snmp_retries' => 1, 'max_oids' => 10, 'ignorePorts' => '', 'snmp_sysDescr' => '',
	);

	$this->walk = function (string $base, array $rows): void {
		$GLOBALS['__test_snmp_walk'][$base] = array();

		foreach ($rows as $index => $value) {
			$GLOBALS['__test_snmp_walk'][$base][] = array('oid' => ltrim($base, '.') . '.' . $index, 'value' => $value);
		}
	};

	($this->walk)('.1.3.6.1.2.1.17.1.4.1.2', array(1 => '1', 2 => '2'));
	// both ports up, so the scan reads the bridge tables
	($this->walk)('.1.3.6.1.2.1.2.2.1.8', array(1 => '1', 2 => '1'));
});

afterEach(function () {
	unset($GLOBALS['__test_snmp_walk'], $GLOBALS['__test_config_options']['mt_ignorePorts_delim']);
});

it('skips the root port when no port range is set', function () {
	($this->walk)('.1.3.6.1.2.1.17.7.1.2.2.1.3', array('1.0.0.0.0.0.1' => '3', '1.0.0.0.0.0.2' => '3'));
	($this->walk)('.1.3.6.1.2.1.17.7.1.2.2.1.2', array('1.0.0.0.0.0.1' => '1', '1.0.0.0.0.0.2' => '2'));
	mactrack_test_queue_return('cacti_snmp_get', '1');

	$ifInterfaces = array(1 => array('ifType' => 6), 2 => array('ifType' => 6));
	$ports        = get_base_dot1qTpFdbEntry_ports('site', $this->device, $ifInterfaces, '', false, 0, 0);

	expect(array_column($ports, 'mac_address'))->toBe(array('000000000002'));
});

it('skips ports that carry an IP address', function () {
	($this->walk)('.1.3.6.1.2.1.17.4.3.1.3', array('0.0.0.0.0.1' => '3', '0.0.0.0.0.2' => '3'));
	($this->walk)('.1.3.6.1.2.1.17.4.3.1.2', array('0.0.0.0.0.1' => '1', '0.0.0.0.0.2' => '2'));
	($this->walk)('.1.3.6.1.2.1.17.4.3.1.1', array('0.0.0.0.0.1' => "\0\0\0\0\0\1", '0.0.0.0.0.2' => "\0\0\0\0\0\2"));

	$ifInterfaces = array(1 => array('ifType' => 6, 'linkPort' => true), 2 => array('ifType' => 6, 'linkPort' => ''));
	$ports        = get_base_dot1dTpFdbEntry_ports('site', $this->device, $ifInterfaces, '', false, 0, 0);

	expect(array_column($ports, 'mac_address'))->toBe(array('000000000002'));
});
