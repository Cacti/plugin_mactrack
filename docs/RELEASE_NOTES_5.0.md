# MacTrack 5.0: clearer scan visibility and more manageable address history

Released November 17, 2026. [Download MacTrack 5.0](https://github.com/Cacti/plugin_mactrack/releases/tag/v5.0).

MacTrack 5.0 adds a dashboard, extends partitioning to MAC and IP address
history, and improves recovery from interrupted scans and maintenance. New
filters help keep uplink and trunk sightings out of your results, while expanded
status reporting shows what MacTrack is doing and why a scan was skipped.

## Highlights

- **See what collection is doing.** The new Dashboard tab brings status cards
  together. Process Status reports scan phases, skipped runs, maintenance timings
  and table sizes, so an administrator has somewhere to start investigating a delay.
- **Manage MAC and IP history together.** Both history tables support partitioning
  by scan date. Retention drops whole expired partitions; existing installations
  can rebuild their tables to use daily, weekly or unpartitioned layouts.
- **Keep uplink and trunk sightings out of scans.** Set regular expressions for
  port names and aliases globally, per site or per device. The scanner reports
  how many MAC sightings it skipped.
- **Keep useful observations through interruptions.** Recovery fixes preserve
  completed scans during interrupted partition exchanges and retain staging data
  when recovery cannot finish. Address reports also retain Layer 2 sightings
  without current ARP data and identify older observations.
- **Read device and address results more clearly.** Collection fixes cover Cisco,
  Aruba and other scanners. Interface and authorization status pills, theme
  styles, and corrected MAC, IPv6 and vendor handling improve the reports used
  during day-to-day troubleshooting.

The release also includes security hardening across administrative actions,
imports, exports and device collection. See the [full changelog](../CHANGELOG.md)
for individual changes.

## Before upgrading

MacTrack 5.0 requires **Cacti 1.2.29 or later and PHP 8.2 or later**. Back up the
plugin configuration and database before replacing the plugin files. Review the
[installation and maintenance documentation](../README.md).

### Check partitioning on an existing installation

Upgrading does not automatically rebuild existing history tables. To apply or
check the scheme for both MAC and IP history:

1. Choose **Port and IP Address Partition Size** under **Console > Settings >
   Mactrack**, and review the separate MAC and IP retention settings.
2. Plan an off-hours maintenance window and allow disk space for another copy of
   each table and its indexes. A large rebuild can take hours.
3. Open **MacTrack Utilities > Configure Port and IP Address Partitioning**, or
   run the following from the Cacti installation directory as the poller user:

   ```sh
   php plugins/mactrack/cli/upgrade_partitioning.php
   ```

4. Check **View Partition Status** and **Process Status** after the operation.
   The utility leaves tables that already match the chosen scheme alone.

Scheduled scans and database maintenance pause during a rebuild, and MAC/IP
search results are incomplete until it finishes. If interrupted, run the same
command again to resume. See [partitioning and recovery](../README.md#port-and-ip-history-partitioning)
for layout choices, retention behavior and troubleshooting.

### Review changed operational behavior

- **MacAuth reports:** configured report frequencies are now honored. An
  installation whose reports previously never sent may begin sending them after
  upgrading; review the frequency and recipients.
- **Historical MAC values:** corrected binary SNMP decoding changes previously
  malformed address values. Old sightings may not match new scans; review affected
  authorizations and history rather than assuming an endpoint itself changed.
- **Device exports:** SNMP community strings and authentication/privacy secrets
  are omitted. Imports keep existing stored credentials; new devices need their
  credentials entered separately.

## Contributors and complete history

Thank you to the community members who developed, translated, tested and reviewed
this release. The [commit index](COMMITS_5.0.md) links every commit between v4.8
and v5.0 and credits the contributors associated with it. Testing and feedback
also made the release possible, even when they did not result in an authored commit.

[Browse the complete release comparison](https://github.com/Cacti/plugin_mactrack/compare/v4.8...v5.0).
