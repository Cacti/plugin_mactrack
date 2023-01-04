# Cacti documentation

[TOC]

## Changes made in Mactrack plugin

### Fixing bug: mac addresses were not visible under MAC Addresses tab

https://github.com/Cacti/plugin_mactrack/commit/cc681b143cfdd43ec79a800009699308d5832407

The bug occurred when using Juniper EX2200 Switches.

```php
$Xvlanid = substr($num, 0, strpos($num, '.'));
```

was changed to

```php
$Xvlanid = substr($num, strpos($num, '.')+1, strpos($num, '.',1)-1);
```

and

```php
$ifIndex  = @$port_results[$mac_result];
```

was changed to

```php
$ifIndex  = @$port_results[".".strval($mac_result)];
```

in the `lib\mactrack_juniper.php` file.

An extra dot was added to the keys of the port dictionary when it was made from
the OID.

### More bug fixing with Juniper switches

https://github.com/Cacti/plugin_mactrack/commit/f2deee43c1229e5bd3110e19e685153b57bfc76c

Changes are available on the link above.

#### Fixing bug: The MAC addresses had 7 groups instead of 6. They were too
#### long.

Basically the start position of the substring was wrong.

#### Fixing Juniper trunk port counter

When using Juniper switches the trunk counter was not implemented.

```php
	/* get VLAN Trunk status */
	$vlan_trunkstatus = xform_standard_indexed_data('.1.3.6.1.4.1.2636.3.40.1.5.1.7.1.5', $device);
	foreach ($vlan_trunkstatus as $vts) {
		if ($vts == 2) {
			$device['ports_trunk']++;
		}
	}
```

was added to the code.

#### Fixing: port names issue

Changed Juniper port names, port numbers and port descriptions according to
Cisco's equivalent of the same fields in the DB. Port number became Interface
name (e.g. ge-0/0/0.0). Port name became Interface description. Port description
was added to $ifInterfaces. Tested with EX-2200 switches.

#### Interface description are now visible under the interfaces tab and
#### port_name cannot be null.

https://github.com/Cacti/plugin_mactrack/commit/bc5ba709134006488a80b4cf3beae782fd9fbd84

### Juniper port ignore

https://github.com/Cacti/plugin_mactrack/commits/develop (it was not accepted by the official Cacti repository when the documentation was made.)

Port ignore option was not implemented for Juniper switches. 

## Mactrack cheat sheet

### mactrack scanner

Main debugging tool: 

-d stands for debug mode.

-id=deviceid selects the device on which the scan will run

The result of the scan will be saved to the **mac_track_temp_ports** table in
mysql. It won't be merged into the **mac_track_ports** table which is used by
Cacti to build the view macs webpage.

```bash
php mactrack_scanner.php -d -id=10
```

### mactrack poller

It runs the SNMP poller on all devices

```bash
php poller_mactrack.php -d -f
```

-d stands for debug mode

-f means force (~ run now, don't wait for the next scheduled scan.)

### Terminated mactrack process

If the poller or the scanner was terminated you should clear the
**mac_track_processes** table by hand.

### Clear mac addresses

To clear the mac addresses from Cacti you should **TRUNCATE** the
**mac_track_ports** table.

### Duplicate device bug

When editing a device from Cacti you should check **mac_track_devices** table.
Sometimes it makes a duplicate entry. Simply delete the old one.

-----------------------------------------------
Copyright (c) 2004-2023 - The Cacti Group, Inc.
