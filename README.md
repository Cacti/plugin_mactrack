# mactrack

[All published release notes](docs/RELEASE_HISTORY.md)

[Version 5.0 release notes and upgrade guidance](docs/RELEASE_NOTES_5.0.md) · [Contributors and change history](docs/COMMITS_5.0.md)

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

Mactrack requires Cacti 1.2.29 or later and PHP 8.2 for its supported runtime and toolchain.

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

## Port, IP and 802.1x history partitioning

MacTrack keeps the MAC address history in `mac_track_ports` and the IP address
history in `mac_track_ips`, and 802.1x events in `mac_track_dot1x`. These tables
are partitioned by scan date (MySQL 8.0+
or MariaDB with the partition plugin), so retention drops whole expired
partitions instead of deleting millions of rows, and a search for one scan reads
one partition. A server without partitioning support keeps unpartitioned tables,
which MacTrack fully supports.

### Setting it up

**New installs** create all three tables weekly partitioned, starting Monday at
00:00 UTC, and the poller's daily maintenance keeps the next two days of
partitions created ahead of collection. Nothing needs to be done.

**Existing installs** keep their tables as they are until you rebuild them. Pick
the size, then run the rebuild once:

1. Open **Console > Settings > Mactrack** and set **Port and IP Address Partition Size**:
   *Weekly* (the default, fewest partitions), *Daily*, *Hourly* or *One per
   Scan* (maintained by the poller; the rebuild script cannot build these two),
   or *Disabled* for unpartitioned tables. It sets the size of the partitions
   created from now on and never rewrites the ones that exist.
2. Set the retention (**Data Retention** for MAC addresses and 802.1x events,
   and **Data Retention IPs** for IP addresses). Partitions older than the retention are dropped by the daily
   maintenance, so with weekly partitions up to one extra week of history can
   stay.
3. Rebuild the existing history to match, either with **MacTrack Utilities >
   Configure Port and IP Address Partitioning** or from the Cacti installation directory:

```sh
php plugins/mactrack/cli/upgrade_partitioning.php
php plugins/mactrack/cli/upgrade_partitioning.php --partition=weekly
php plugins/mactrack/cli/upgrade_partitioning.php --partition=daily --debug
php plugins/mactrack/cli/upgrade_partitioning.php --partition=disabled
```

Run it as the user that runs the Cacti poller, off hours (see below).

### Checking and changing the scheme

`cli/upgrade_partitioning.php` checks all three tables against the scheme and rebuilds
only the ones that have drifted:

- Without `--partition` the scheme is the **Port and IP Address Partition Size** setting. This
  is the command to run after changing the setting, after an upgrade, or from
  time to time to make sure the tables are as they should be.
- With `--partition=daily|weekly|disabled` that scheme is used and saved as the
  **Port and IP Address Partition Size** setting.
- `--debug` prints every scan date copied. `--version` and `--help` print the
  version and the usage (`-v` and `-h` still work).

A table is reported as drifted, and rebuilt, when it is not partitioned, uses the
earlier daily layout, has partitions MacTrack does not recognize, mixes sizes,
keeps rows outside every partition, or is not the selected size. A table that
already follows the scheme is left alone, and the script says so. **View
Partition Status** in the Utilities shows the layout of all three tables, the last
rebuild and the last maintenance.

### What a rebuild does

The tables are rebuilt one after the other, the MAC address table first, with the
same steps for each:

1. The table is renamed to `<table>_rebuild`.
2. The table is created again with the partitions for the retention period and
   the two days kept ahead of collection. History older than the retention
   period is copied into the first partition, which retention drops once it has
   expired, so nothing is lost by the rebuild.
3. Each distinct scan date is copied from the renamed table, oldest first.
4. When every scan date is copied and the row counts match, the renamed table is
   dropped.

### While it runs

Run it off hours: a rebuild needs disk space for a second copy of each table and
its indexes, and a large history can take hours (the run is registered in Cacti's
process registry for 24 hours, so two runs cannot overlap).

- Scheduled scans and the daily database maintenance are paused. If the
  maintenance window passes meanwhile, the poller runs the maintenance as soon as
  the rebuild is over.
- MAC address, IP address and 802.1x searching shows a maintenance-mode message, and its
  results are incomplete until the rebuild has finished.
- Changes to the history are refused with a message until the rebuild has
  finished, including after an interruption: removing devices and sites,
  authorizing and revoking MAC addresses, MacAuth changes, and the Utilities that
  remove scan results, recreate the aggregated data or rebuild the scan dates.
  The background database upgrade waits as well, so nothing is applied to a
  partly copied table.
- The script prints progress at least every 30 seconds. **Process Status** shows
  that MacTrack is repartitioning.

If the run is stopped (Ctrl-C, a reboot, a crash), the renamed table and the partly
filled new table stay as they are, scanning stays paused, and the maintenance
message stays up. Run the same command again to resume from the last scan date
copied. A pending partition exchange or a running database upgrade must finish
first; the script says so and changes nothing.

### Ongoing maintenance

Nothing needs to be scheduled besides the poller. Its daily database maintenance
drops partitions that have expired (their upper bound is older than the
retention), creates the partitions for the next two days (a slot per day, or per
week), and moves any rows that landed outside every partition into a recovery
partition. Retention drops only whole expired partitions, and `Disabled` uses the
unpartitioned retention, which deletes rows. The Utilities page can rebuild and
analyze the newest port partition to reclaim space.

### Troubleshooting

- *"A scan is running"*: run the script again when the poller has finished.
- *Maintenance message does not go away*: a `*_rebuild` table is left; run the
  script again to resume.
- *"The history needs more partitions than the limit"*: choose weekly, or lower
  the retention and run the maintenance first.
- A partitioning failure is recorded in the Cacti log (`MACTRACK`) and under
  **View Partition Status**.

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
name and the port alias, for example `(uplink|trunk)` or `^Po[0-9]+$`. The
expression fields are text areas like the device's **Ports to Ignore**: put each
expression on its own line to give several, and a port matching any line is
ignored. Leave it
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
