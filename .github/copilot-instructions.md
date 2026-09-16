# GitHub Copilot Instructions

## Priority Guidelines

When generating code for this repository:

1. **Version Compatibility**: This is a Cacti plugin (`mactrack`, "Device Tracking", version 4.9) targeting Cacti 1.2.14+; `tests/Security/Php74CompatibilityTest.php` guards `setup.php` against PHP 8.0+-only syntax as a legacy regression check.
2. **Context Files**: Prioritize patterns and standards defined in this file (`.github/copilot-instructions.md`)
3. **Codebase Patterns**: When context files don't provide specific guidance, scan the codebase for established patterns
4. **Architectural Consistency**: Maintain plugin-based architecture extending Cacti core
5. **Code Quality**: Prioritize security, maintainability, and compatibility in all generated code

## Technology Stack

### Core Technologies
- **PHP**: 7.4+ syntax floor (guarded by `tests/Security/Php74CompatibilityTest.php`); CI integration matrix runs against a pinned Cacti release
- **Platform**: Cacti Plugin Architecture (Cacti 1.2.14+)
- **Database**: MySQL/MariaDB via Cacti's DB abstraction layer
- **SNMP**: Bulk MAC/ARP/interface/VLAN discovery via Cacti's SNMP library

### Key Dependencies
- Cacti core framework (`api_plugin_*`, `db_*`, `cacti_snmp_*`)
- `lib/`, `Net/` supporting libraries; `includes/` shared functions

## Project Structure

```
mactrack/                     # Repository root (install to plugins/mactrack/ in Cacti)
├── docs/                        # Documentation (CactiCheatSheet.md)
├── includes/                       # Shared functions
├── lib/ Net/                          # Supporting libraries
├── tests/                                # Pest/PHPUnit-style test suite (Security, Unit)
├── themes/                                  # CSS theme overlays
├── mactrack_devices.php / mactrack_device_types.php   # Device / device-type administration
├── mactrack_sites.php                                   # Site administration
├── mactrack_scanner.php / mactrack_resolver.php           # Discovery scanning / hostname resolution
├── mactrack_view_*.php                                      # Various table views (arp, devices, dot1x, graphs, interfaces, ips, macs, sites)
├── mactrack_macauth.php / mactrack_macwatch.php               # MAC authorization / watch-list administration
├── mactrack_snmp.php / mactrack_snmp.js                          # SNMP collection routines + client-side helper
├── mactrack_utilities.php / mactrack_convert.php                    # Maintenance utilities / data conversion
├── mactrack_vendormacs.php / mactrack_import_ouidb.php                # Vendor OUI database import/lookup
├── mactrack_ajax.php / mactrack_ajax_admin.php                          # AJAX endpoints (viewer / admin)
├── poller_mactrack.php                                                     # Background poller entry point (CLI)
├── mactrack.sql                                                              # Baseline schema
├── INFO                                                                        # Plugin metadata (name, version, compat)
├── README.md
└── setup.php                                                                      # Plugin install/uninstall/upgrade hooks
```

## Naming Conventions

### Function Names
- **Plugin lifecycle/hook-registration functions** MUST be prefixed `plugin_mactrack_` where used for lifecycle hooks; most hook callbacks and helpers in this codebase use the `mactrack_` prefix directly: `mactrack_show_tab()`, `mactrack_config_arrays()`, `mactrack_poller_bottom()`, `sync_cacti_to_mactrack()`.
- Match the existing prefix used by the function you are editing; do not introduce a new naming scheme.

### Database Tables
Plugin tables are prefixed `mactrack_`/`plugin_mactrack_`-style names as already defined in `mactrack.sql`; do not introduce a differently-prefixed parallel schema.

## Code Style

### Indentation and Formatting
- **Tabs**: Use tabs (not spaces) for indentation throughout all PHP files.
- **Braces**: Opening brace on the same line for functions and control structures.
- **Spacing**: Space after control structure keywords (`if`, `foreach`, `while`).

### File Headers
ALL PHP files MUST include the standard GPL v2 license header used throughout this repository (see `setup.php`), crediting "The Cacti Group".

## Security Standards

### SQL Query Security
Use prepared statements (`db_execute_prepared()`, `db_fetch_row_prepared()`, etc.) for ALL queries with variables:

```php
// CORRECT
db_fetch_row_prepared('SELECT * FROM mactrack_devices WHERE id = ?', array($id));

// WRONG
db_fetch_row("SELECT * FROM mactrack_devices WHERE id = $id");
```

### Input Validation
Use `get_request_var()` / `get_filter_request_var()` for ALL user input, never raw `$_REQUEST`/`$_GET`/`$_POST`.

`get_filter_request_var()` (and its `gfrv()` shorthand, where available) called with only the
`$name` argument (no regex/filter as the 2nd/3rd argument) already validates the value as numeric
and returns it as a **string** -- it does not return an int, and it halts execution if the request
value is not numeric. Because of this, do NOT cast its output to `(int)` when the result is only
used for string output (e.g. `print`/`echo`, string concatenation, embedding in HTML/JS); the cast
is redundant. Only cast when the value is genuinely used in an integer/numeric context (e.g.
arithmetic, strict `===` comparisons).

### Output Escaping
Use `html_escape()` / `htmlspecialchars()` for ALL output of DB/user values in HTML context.

### Shell Arguments
Use `cacti_escapeshellarg()` for ALL shell command arguments.

### PHP Version Floor
No PHP 8.0+ features (`str_contains()`, `match`, union types, named args) — target PHP 7.4. Use `??`/`??=` operators (PHP 7.4) instead of `isset()` ternary patterns.

### Deserialization Safety
Metadata-only `unserialize()` calls must use `allowed_classes => false`; object payloads must use a minimal explicit class allowlist and validate the resulting type.

## Database Operations

Use Cacti's `db_*`/`db_*_prepared()` functions; keep schema creation/upgrades in `setup.php`'s install/upgrade lifecycle.

## Internationalization

Wrap user-facing strings in `__('text', 'mactrack')` where the surrounding code already does so.

## Plugin Architecture

### Plugin Hooks
Register hooks in `plugin_mactrack_install()` (`setup.php`), including `top_header_tabs`, `top_graph_header_tabs`, `config_arrays`, `draw_navigation_text`, `config_form`, `config_settings`, `poller_bottom`, `page_head`, `api_device_save` (routed to `mactrack_actions.php`), `device_action_array/prepare/execute`.

### Poller Integration
`poller_mactrack.php` is the CLI entry point invoked from `poller_bottom`; SNMP discovery/collection logic lives in `mactrack_snmp.php`.

## Testing

Put executable standalone tests in `tests/Unit/` or `tests/Integration/`; the code-quality workflow runs every PHP file in those directories. Keep tests dependency-free so they run without Composer. Run `php -l` before committing.

## Best Practices

1. Never introduce PHP 8.0+-only syntax; the 7.4 floor is enforced by a dedicated regression test.
2. Always use the `_prepared` DB helper variants for any query with variable input.
3. Validate/allowlist classes for any `unserialize()` call.
4. Wrap all user-facing strings with `__('text', 'mactrack')`.

## Common Pitfalls to Avoid

```php
// WRONG - PHP 8.0+ only syntax
if (str_contains($haystack, $needle)) { }

// CORRECT - PHP 7.4-compatible
if (strpos($haystack, $needle) !== false) { }

// WRONG - unsafe unserialize
$data = unserialize($raw);

// CORRECT
$data = unserialize($raw, array('allowed_classes' => false));
```

## Version Control

Document all changes in `CHANGELOG.md`; use descriptive commit messages referencing issue/PR numbers when applicable.

## References

- [Cacti main repo](https://github.com/Cacti/cacti/tree/1.2.x)
- [Cacti Documentation](https://www.github.com/Cacti/documentation)
- `CactiCheatSheet.md` for quick developer reference
- `README.md` for feature descriptions
- `CHANGELOG.md` for version history
