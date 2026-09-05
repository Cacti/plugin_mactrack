# Net_DNS2

Bundled copy of the Net_DNS2 PHP DNS resolver, used by `mactrack_resolver.php`
to turn collected IP addresses into hostnames.

Cacti plugins install by unpacking a tarball, so runtime dependencies ship
in-tree rather than through Composer. `plugin_flowview` bundles the same
library the same way.

## Provenance

| | |
|---|---|
| Upstream | https://github.com/mikepultz/netdns2 |
| Version  | 1.5.5 |
| Tag      | `v1.5.5` |
| Commit   | `ea39ef5a97d5c2b9893a8c35af7b5fd5b0e40bc9` |
| Released | 2025-05-17 |
| License  | BSD 3-Clause, see `LICENSE` |

The previous bundle was 1.5.0 (`c5448f3d94ec5ab4962ac22f662fdb7d654bc333`,
2020-10-08). Between the two releases upstream replaced
`Net/DNS2/Socket/Sockets.php` and `Net/DNS2/Socket/Streams.php` with a single
`Net/DNS2/Socket.php`, added `Net/DNS2/Names.php`, and added the `ZONEMD`
record type.

The 2.x line is namespaced and is not API compatible with the `Net_DNS2_*`
class names this plugin uses, so 1.5.x is the line to track.

## Updating

```
git clone https://github.com/mikepultz/netdns2 /tmp/netdns2
git -C /tmp/netdns2 checkout <tag>
rm -rf Net/DNS2 Net/DNS2.php
cp -R /tmp/netdns2/Net/. Net/
```

Then update the table above and confirm `mactrack_resolver.php` still resolves
against a real nameserver.
