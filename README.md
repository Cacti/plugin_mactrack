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

## Port history partitioning

New installs use weekly partitions, starting Monday at 00:00 UTC. To convert
an existing history, open **MacTrack Utilities > Configure Port Partitioning**.
Choose **Daily**, **Weekly**, or **Disabled**, optionally keep the original table
as a backup, and continue. The conversion runs in the background; **View Partition
Status** shows whether it ran successfully or could not start.

The same options are available from the Cacti installation directory:

```sh
php plugins/mactrack/mactrack_convert.php --partition=weekly
php plugins/mactrack/mactrack_convert.php --partition=daily --keep-old
php plugins/mactrack/mactrack_convert.php --partition=disabled
```

Without `--partition`, an unpartitioned table is converted to the Port Partition
Size setting and an already partitioned table is left unchanged. Choosing the
layout the table already has copies nothing.

Run conversions off hours. They copy the full history, need disk space for a
second copy of the table and its indexes, and skip scheduled scans and hold
database maintenance while running. Readers keep using the original until row counts
are verified and the tables are swapped. The copy neither deletes source rows
nor wraps the history in a transaction. If interrupted before the swap, repeat
the command or web action to restart the copy.

With `--keep-old` (or the web backup checkbox), the original remains as
`mac_track_ports_old`. Verify the converted data and remove that backup manually
before another conversion. A failed copy leaves the original table and setting
unchanged. A pending partition exchange or database upgrade must finish first.

Daily and weekly modes use timestamp range partitions, and maintenance keeps
two days of partitions created ahead of collection. Retention drops only whole expired partitions, so a weekly
layout may keep up to one extra week of history. Disabled uses the existing
unpartitioned retention path. The Port Partition Size setting controls future
partitions; use the Utilities conversion to change the layout of existing data.

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
