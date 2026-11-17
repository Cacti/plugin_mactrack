# mactrack

The MacTrack plugin is designed to scan network switches, routers and
intelligent hubs for connected devices, and record their location either based
upon the portname or alias of the switch or hub.  It also attempts to discover
the ip address of the mac address from the routers included in the MacTrack
database.  MacTrack can also use arpwatch to gather IP to MAC address
associations.

MacTrack has the ability to also notify admins or security personnel when the
MAC address of a missing or stolen computer re-appears on the network.  This can
be helpful in recovering lost equipment.  Through MacTrack's interface
monitoring feature, a Network Administrator can get a very good idea of where
utilization is, where there are errors, etc within their network.

## Features

* Scans Devices

* Finds Macs

* Associates Macs with their IP's

* Keeps a Nice Inventory of Port Counts

* Finds Stolen/Lost PC's

* Tells you when someone is connected who shouldn't be

## Prerequisites

Mactrack requires Cacti 1.2.14 or later and PHP 8.2 for its supported runtime and toolchain.

## Installation

Just like any Cacti plugin, untar the package to the Cacti plugins directory
and rename the directory to `mactrack`. Then use Cacti's Plugin Management
interface to install and enable Mactrack.

Net_DNS2 ships with the plugin under `Net/`, so there is nothing else to
install.

## Documentation

Some basic documentation and steps to follow as well as some troubleshooting
tips you can find on the [MacTrack
Wiki](https://github.com/Cacti/plugin_mactrack/wiki)!

## Workflow

Configure mactrack - Console -> Settings -> Mactrack tab
Add Mactrack Site - Console -> Mactrack -> Sites
Add/import devices - Console -> Mactrack -> Devices -> Add device or Management -> Choose host -> Import into Mactrack Database
Create new or enable prepared Device Types - Console -> Mactrack -> Device Types

## Port and IP history partitioning

The MAC address history (`mac_track_ports`) and the IP address history
(`mac_track_ips`) are partitioned by scan date. New installs use weekly
partitions, starting Monday at 00:00 UTC. The **Port Partition Size** setting
(One per Scan, Hourly, Daily, Weekly or Disabled) sets the size of the
partitions created from now on; it never rewrites partitions that already exist.
Retention then drops whole expired partitions instead of deleting rows.

Changing the size, or upgrading an existing install, can leave a table that
does not follow the setting. `cli/upgrade_partitioning.php` checks both tables
against the scheme, tells you when there is no drift, and rebuilds the tables
that have drifted. From the Cacti installation directory:

```sh
php plugins/mactrack/cli/upgrade_partitioning.php
php plugins/mactrack/cli/upgrade_partitioning.php --partition=weekly
php plugins/mactrack/cli/upgrade_partitioning.php --partition=daily --debug
php plugins/mactrack/cli/upgrade_partitioning.php --partition=disabled
```

Without `--partition`, the Port Partition Size setting is the scheme. With
`--partition=daily|weekly|disabled`, that scheme is rebuilt to and saved as the
Port Partition Size setting. The per-scan and hourly sizes are maintained by the
poller and are not built by this tool. **MacTrack Utilities > Configure Port
Partitioning** starts the same command in the background, and **View Partition
Status** shows its last result.

A table is rebuilt when it is not partitioned, uses the earlier daily layout, has
partitions MacTrack does not recognize, mixes sizes, keeps rows outside every
partition, or is not the selected size. A table that already follows the scheme
is left alone and reported. The tables are rebuilt one after the other, ports
first, with the same steps for each:

1. The table is renamed to `<table>_rebuild`.
2. The table is created again with the partitions for the retention period and
   the two days kept ahead of collection. History older than the retention
   period is copied into the first partition, which retention drops once it has
   expired.
3. Each distinct scan date is copied from the renamed table, oldest first.
4. When every scan date is copied and the row counts match, the renamed table is
   dropped.

While it runs, scheduled scans and database maintenance are paused, MAC and IP
address searching shows a maintenance message, and the command prints progress
at least every 30 seconds (`--debug` prints every scan date). Run it off hours:
it needs disk space for a second copy of each table and its indexes, and a large
history can take hours. The run is registered with Cacti's process registry for
24 hours so two runs cannot overlap. If it stops, the renamed table and the
partly filled new table stay as they are, the poller stays paused, and running
the command again resumes from the last scan date copied. A pending partition
exchange or database upgrade must finish first.

Daily and weekly modes use timestamp range partitions, and maintenance keeps
two days of partitions created ahead of collection. Retention drops only whole
expired partitions, so a weekly layout may keep up to one extra week of history.
Disabled uses the existing unpartitioned retention path (rows are deleted).

## Ignoring ports while scanning

Ports that carry many MAC addresses, such as uplinks and trunks, can be kept out
of the collected data instead of hidden in the views, which keeps the database
smaller. There are three ways to do it, and a port is ignored when any of them
matches:

- **Ports to Ignore** on a device: an exact list of port names, separated by
  colons, pipes, commas or spaces. With the delimiter setting on Auto Detect,
  names that contain a colon, such as `1:5` on a stacked Extreme switch, work
  when the list is separated by pipes, commas or spaces.
- **Ports to Ignore (Regular Expression)** on a device or a site (Console >
  MacTrack > Devices or Sites).
- **Ports to Ignore While Scanning (Regular Expression)** in Console > Settings
  > MacTrack, for every device.

A regular expression is matched without regard to case against both the port
name and the port alias, for example `(uplink|trunk)` or `^Po[0-9]+$`. Leave it
empty to collect every port. An invalid expression is logged and skipped, so a
typo never stops a scan. The run message of a device shows how many MACs were
skipped, and the new settings apply to new scans only; existing history is not
removed. The global **Ports to Ignore** setting is unrelated: it only filters the
interface list.

## Bugs and Feature Enhancements

Bug and feature enhancements for the Mactrack plugin are handled in GitHub. You
may want try first searching the Cacti forums for a solution before creating an
issue in GitHub.

## Special Thanks

* *Jimmy Conner (cigamit)*

  For bringing the plugin architecture to the world of Cacti and providing
  continual support of my development efforts.

* *Larry Adams (TheWitness)*

  For supporting this monster on his free time even though he no longer works in
  the network space.

* *Reinhard Scheck (gandalf)*

  The European Cacti strongman and Cacti/RRDtool guru.  Thanks for keeping me
  honest.

* *Cacti Users Everywhere*

  For just using Cacti.  Thanks for giving me a hobby to live for.

-----------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.
