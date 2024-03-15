# mactrack

The MacTrack plugin is designed to scan network switches, routers and
intelligent hubs for connected devices, and record their location either based
upon the portname or alias of the switch or hub.  It also attempts to discover
the ip address of the mac address from the routers included in the MacTrack
database.  MacTrack can also use arpwatch to gether IP to MAC address
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

The MacTrack on GitHub requires Cacti 1.2.14 as a minimum.

## Installation

Just like any Cacti plugin, untar the package to the Cacti plugins directory,
rename the directory to 'mactrack', and then from Cacti's Plugin Management
interface, Install and Enable the plugin.

## Documentation

Some basic documentation and steps to follow as well as some troubleshooting
tips you can find on the [MacTrack
Wiki](https://github.com/Cacti/plugin_mactrack/wiki)!

## Workflow

Configure mactrack - Console -> Settings -> Device Tracking tab
Add Mactrack Site - Console -> Device Tracking -> Sites
Add/import devices - Console -> Device Tracking -> Devices -> Add device or Management -> Choose host -> Import into Device Tracking Database
Create new or enable prepared Device Types - Console -> Device Tracking -> Device Types

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
Copyright (c) 2004-2024 - The Cacti Group, Inc.
