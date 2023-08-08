## ChangeLog

--- develop ---

--- 4.6 ---

* issue#183: Undefined array key in lib/mactrack_functions.php
* issue#184: add H3C/3Com modern switches/routers
* issue#189: Add IP addresses retention
* feature: Prepared device types

--- 4.5 ---

* security: Fix potential security exposure with unserialize() function

* issue#165: The file mactrack_actions.php will throw and error if an the $save
  array is discarded by another plugin

* issue#169: Add back support for the MacTrack ARP Table

* issue#175: Uncaught TypeError: sizeof(): Argument #1 ($value) must be of type
  Countable|array

* feature#170: Add support for Cisco VRF ARP Table


--- 4.3 / 4.4 ---

* bug: unable to delete Mac Watch and Sites records

* bug: Mac Watch can not send mail

* bug: Number of devices scanned incorrect for main scanner

* bug: MacAuthorizations Interface not accessible through MacTrack tab

* bug: Mac Watch can not send mail

* issue#70: Fix issue with Prompt confirmation button

* issue#75: Command Line does not show output

* issue#77: dlink switch vlan name is null

* issue#75: Command Line does not show output

* issue#81: Add support for FontAwesome 5.0 under Cacti 1.2

* issue#82: Device Type shows twice / % does not work with Vendor SNMP Object ID
  Match

* issue#87: Cant save settings on Cacti devices when MacTrack plugin is enabled

* issue#94: Fix issue with Mac Watch emails disabling plugin

* issue#98: Fix DB maintenance not running automatically

* issue#105: Error when starting Mactrack scan

* issue#107: MACTRACK ERROR: Can not start MAC Tracking process. There is
  already one in progress

* issue#109: Column 'vlan_name' cannot be null

* issue#110: Undefined offset for all vlans retrieved.

* issue#111: String size overflow in
  file:cacti/plugins/mactrack/lib/mactrack_functions.php on line: 1774

* issue#112: Mactrack user settings typo says max addresses instead of mac
  addresses.

* issue#114: Uncaught Error: Class 'Net_DNS2_Resolver' not found in
  mactrack_resolver.php

* issue#115: ERROR: 802.1x Address Scanning Function Does Not Exist.

* issue#116: cacti 1.1.38 mactrack 4.2 ED IP ADDRESS - ED DNS Hostname

* issue#117: Juniper EX4200 issues with getJEX_switch_ports

* issue#118: Undefined property: Net_DNS2_RR_CNAME::$ptrdname in file

* issue#117: Juniper EX4200 issues with getJEX_switch_ports

* issue#119: No values for Dot1x and browser hangs

* issue#120: mactrack_import_ouidb.php has incorrect path to function
  definitions

* issue#128: Re-HUPping MacTrack Plugin post Maria upgrade getting the 'Error:
  Index column size too large. The maximum column size is 767 bytes.'

* issue#132: Changing device type sometimes create a duplicate entry

* issue#137: CMDPHP ERROR: A DB Exec Failed!, Error: Duplicate entry '7' for key
  'PRIMARY'

* issue#143: mactrack_resolver.php variable vlan_id line 176

* feature: PHP 7.2 compatibility

* feature: Switch port_number to integer type of performance reasons

* feature: Update Net_DNS2 to version 1.5

* feature: Use local DNS to resolve when either the resolver fails or there are
  no DNS servers listed

* feature: Update Net_DNS2 to version 1.5


--- 4.2 ---

* feature: Basic documentation under github wiki + screenshots

* issue#50: False positive errors in Cacti log relative to 802.1x functions

* issue#52: Issues with paths to icons and pages

* issue#53: Issues with unset $scan_date variable

* issue#54: Resolving SQL errors in mactrack_resolver.php

* issue#57: Correct percentage sign issues in setup.php

* issue#61: Correct issue with function detection in mactrack_scanner.php

* feature: new 802.1x scanning function (for cisco devices).

* feature: new IP Scanning function 'DHCP Snooping' (for cisco devices).

* feature: add port name column to IP Address view

* feature: cacti.pot updated

* feature: Added new TAB View Dot1x Sessions

* feature: Added mactrack.css for every theme.

* issue: Fix OUI Database import was removing existing records when failed.


--- 4.1 ---

* feature: Updates to facilitate i18n by contributors

* issue#25: Unable to remove Site

* issue#34: Ambiguous messages when running the MacTrack poller


--- 4.0 ---

* feature: Cacti 1.0 compatibility

* feature: i18n MacTrack

* feature: interface backgrounds in CSS

* feature: use jQuery exclusively for dom manipulation

* issue#22: Mactrack devices, 'Save' option missing.

* issue#17: MacTrack Show Site SQL Syntax

* issue#16: MacTrack Undefined indexes and offsets

* issue: MacTrack view Interfaces showed wrong color for interfaces UP.

* issue: MacTrack view MACs 'portname' filter option missing.

* feature: Removed deprecated ifInNUcastPkts and IfOutNUcastPkts and replaced by
  Ifin/OutMulticastPkt and Ifin/OutBroadcastPkt.

* feature: Updated spanish translations


--- 3.0 ---

* bug#0001838: support partitioned tables mac_track_ports

* bug#0001858: No device filter on Graphs tab

* bug#0001873: hook 'mactrack', 'page_head'

* bug#0001865: rescan doesn't work

* bug: don't use htmlspecialchars recursively in forms

* bug: make the device removal a global function

* bug: don't generate replace errors if device has an apostrophe

* bug: reorganize API functions to assist third party integrations

* bug: use qstr instead of addslashes as it's not reliable

* feature: Attempt to kill processes if they have run over their allotted time

* feature: Support new Theme engine


--- 2.9 ---

* bug#0001799: Select all checkbox on Mac Addresses tab not working

* bug#0001723: Can't import devices

* bug#0001751: Multiple ignore ports for Extreme Network switch

* bug#0001777: MacTrack MacAuth Filters not working

* bug#0001779: Site IP Range Report View

* bug#0001841: Mactrack ARP table unreadable

* bug: Saving an edited site redirects to blank page


--- 2.8 ---

* compat: Allow proper navigation text generation

* bug: Guest user could not access site


--- 2.7 ---

* bug#0001742: PHP error () in lib/mactrack_extreme.php

* bug#0001743: DEBUG: Authorized MAC ID: empty

* bug: Correcting SNMPv3 and Cisco support

* bug: Importing OUIDB broken with redefine function error

* bug: Exporting Devices from the Console did not work

* bug: Lastchange was not displaying correctly


--- 2.6 ---

* feature:#0001718: New get_arp_table function for Extreme Networks devices

* bug#0001665: New line missing when printing Cisco device stats

* bug#0001668: Mactrack sometimes shows "No results found" when results are
  shown

* bug#0001670: spikekill and mactrack JS functions clash stateChanged and
  getfromserver

* bug#0001677: index initialization errors (courtesy toe_cutter)

* bug#0001677: unintended overwrite of non-synced devices (courtesy toe_cutter)

* bug#0001717: Ip addresses range report a false value

* bug: Undefined index when paging through interfaces

* bug: Graph View still being called for one class of graphs incorrectly

* bug: With Scan Date set to 'All' rows counter was not correct for matches

* bug: When viewing IP's, device filter not operable


--- 2.5 ---

* bug#0001677: Undefined indexes

* bug: Undefined index reports in mactrak_view_graphs.php

* bug: Small visual issue with Site Details

* bug: Interfaces table not being created during upgrade

* feature: Portname search filter courtesy KAA and gthe


--- 2.4 ---

* bug: 0001546: mactrack_view_devices does not display the proper page numbers

* bug: 0001545: mactrack_scanner does not complete successfully for some hosts

* bug: 0001547: mactrack_view_sites does not show page numbers

* bug: 0001548: mactrack_view_macs various issues

* bug: IEEE Database import runs out of memory

* bug: Correct uninitialized error in mactrack_hp.php

* bug: Resolved issue where Vendor Mac was lost during resolver process

* bug: fix syntax of cacti_snmp_* calls

* feature: 0001638: Allow Interface Data to Be "Scaned" on Demand from the WebUI
  Similar to Scanner

* feature: 0001637: Enable Site Level Scanning Through the UI and Using Ajax

* feature: Adding support for ArpWatch

* feature: Adding Juniper Support

* feature: Support Foundry Dual Mode Ports

* feature: Implement MacWatch in code.  E-Mailing now.

* feature: Adding significant content to the mac_track_interfaces table

* feature: Implement MacAuth functionality and periodic reports

* feature: Implement Interfaces tab to MacTrack

* feature: Aggregated ports patch from Gthe!

* feature: Linux and DLink Scanners from Gthe!

* feature: add all device SNMP options to mac_track_devices

* feature: add full SNMP V3 support

* feature: deprecate readstrings; maintain "SNMP Option Sets" in favour of them

* feature: some Enterasys scanning functions (N7, C2, C3)

* feature: import cacti devices into mac_track_devices (new action hook for
  host.php)

* feature: optionally sync SNMP settings of mac_track_devices and cacti device,
  governed by a config setting (defaulting to "none") to allow either:

  mactrack -> host (when scanning) or host -> mactrack updates (when manually
  updating the host)

* feature: allow for "connecting" existing mactrack devices to cacti devices
  (via hostname, new action for mactrack_devices.php)

* feature: copy snmp settings from cacti devices to mactrack devices (via
  host_id connection, new action for mactrack_devices.php)

* feature: Allow mapping of Cacti graphs to MacTrack

* feature: Add columns for AutoNegotiation - Implementation TBD


--- 1.1 ---

* First Official Release


--- 0.1 ---

* Initial Release!  Oh, so long ago

## Changes made in Mactrack plugin by woranhun

###       Fixing bug: mac addresses were not visible under MAC Addresses tab

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

https://github.com/Cacti/plugin_mactrack/commits/develop (it was not accepted by
the official Cacti repository when the documentation was made.)

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
