# GitHub Copilot Instructions for MacTrack plugin

Priority: produce code that strictly follows patterns and versions already present in this repository. Do not introduce newer language features, new frameworks, or different architectural styles.

---

## Quick summary of repository facts (observed)

- Primary language: PHP (procedural plugin code). See [setup.php](setup.php#L1-L20) and many `*.php` files in repository.
- CI indicates supported PHP versions: `8.1`, `8.2`, `8.3`, `8.4`. See [.github/workflows/plugin-ci-workflow.yml](.github/workflows/plugin-ci-workflow.yml#L41-L81).
- This project is a Cacti plugin that requires Cacti >= 1.2.14. See [README.md](README.md#L1-L20).
- JavaScript is used for UI behavior (example files: `mactrack.js`, `mactrack_snmp.js`) and CSS themes are present. See [mactrack.js](mactrack.js) and [mactrack_snmp.js](mactrack_snmp.js).

All guidance below is derived from these observed patterns. If a Copilot suggestion would conflict with any item below, prefer repository consistency.

---

## Priority Guidelines for generated code

1. Version compatibility: Do not use PHP language features beyond PHP 8.1–8.4 compatibility unless you detect additional project files that indicate support for newer versions.
2. Project style: Match the repository's procedural PHP style (functions, global usage, `include`/`include_once`) rather than introducing classes, namespaces, or frameworks unless the existing code already uses them.
3. Localization and escaping: Use the repository's localization and escaping patterns (`__()`, `__esc()` and `htmlspecialchars`) when producing UI text and output.
4. Database access: Use the project DB helper functions and patterns (e.g., `db_execute`, `db_fetch_assoc`, `db_fetch_cell`) rather than inventing new DB layers. Keep SQL usage consistent with existing code.
5. Output: Follow existing HTML output patterns (inline printing with `print`/`echo`) and the same markup structure and CSS classes (e.g., `ui-button`, Font Awesome usage).

---

## Technology / Version detection steps Copilot should follow before suggesting code

1. Inspect `.github/workflows/plugin-ci-workflow.yml` for explicit supported PHP versions and CI checks. Use those versions as the target language compatibility. See [.github/workflows/plugin-ci-workflow.yml](.github/workflows/plugin-ci-workflow.yml#L41-L81).
2. Inspect `README.md` (project requirements) for Cacti version constraints. See [README.md](README.md#L1-L20).
3. Look for `composer.json`, `composer.lock`, or other metadata; this repository conditionally checks for `composer.json` in CI but does not require it. If present, respect its constraints.

Never propose code that requires newer PHP features, build tooling, or frameworks than what the repository explicitly supports.

---

## Coding patterns and conventions observed (and required)

- Procedural style: functions are top-level (for example `plugin_mactrack_install()` in [setup.php](setup.php#L1-L40)). Favor adding functions and using existing global variables (e.g., `$config`, `$tabs`) when extending behavior.
- Includes: code uses `include` / `include_once` with relative plugin paths. Follow the same pattern for loading helpers and libraries.
- Globals: code frequently accesses globals such as `$config` and `$tabs`. Use the same approach where needed to match surrounding code.
- Arrays: uses PHP array syntax `array(...)` across the codebase. Maintain the same array style unless files in the same area already use short array syntax `[...]`.
- DB helpers: use `db_fetch_assoc`, `db_fetch_row`, `db_fetch_cell`, `db_execute`, and other project wrappers rather than raw PDO/mysqli usage.
- Output escaping: use `__esc()` or `htmlspecialchars()` when printing user-supplied content. See examples in [mactrack_view_sites.php](mactrack_view_sites.php#L290-L306).
- Translation: use `__('text', 'mactrack')` for strings that appear in UI; follow existing message domain usage.
- JS and CSS: include JavaScript and CSS the same way the repo does (see [setup.php](setup.php#L1-L40) where scripts/styles are added in `mactrack_page_head()`). Keep class names and UI patterns (e.g., `ui-button`, `pic`, Font Awesome `fas` classes).

---

## Security and input handling (per existing patterns)

- Follow the repository's established sanitization and escaping patterns: use the `__esc()` wrapper and `htmlspecialchars()` when emitting values into HTML. Mirror existing checks rather than arbitrarily switching to new escaping utilities.
- For database interactions, follow the existing project wrapper functions and patterns. If you must add SQL, match the style used in nearby code (string concatenation or helper function usage as present).

---

## Testing and CI expectations

- Ensure any PHP created parses cleanly with `php -l` (CI runs `php -l` checks across `*.php`). See [.github/workflows/plugin-ci-workflow.yml](.github/workflows/plugin-ci-workflow.yml#L170-L180).
- If adding PHP code that uses dependencies managed by Composer, only do so if `composer.json` exists and CI would install dependencies; the CI workflow checks for `composer.json` before running Composer.

---

## Documentation and headers

- Follow the existing file header pattern (GPL notice and author lines) when creating new top-level files. See [setup.php](setup.php#L1-L20) for the header example.
- Use the same inline documentation style (brief blocks and inline comments) matching nearby files.

---

## Examples (copy/adapt these repository-consistent snippets)

- Function signature and register-hook pattern (from [setup.php](setup.php#L1-L40)):

```php
function plugin_mactrack_example() {
    api_plugin_register_hook('mactrack', 'page_head', 'mactrack_page_head', 'setup.php');
}
```

- Output with escaping (pattern from [mactrack_view_sites.php](mactrack_view_sites.php#L290-L306)):

```php
$actions = "<a class='pic' href='" . htmlspecialchars($webroot . 'mactrack_sites.php?action=edit&site_id=' . $site['site_id']) . "' title='" . __esc('Edit Site', 'mactrack') . "'>...</a>";
```

- Adding scripts/styles to page head (pattern from [setup.php](setup.php#L1-L40)):

```php
print "<script type='text/javascript' src='" . $config['url_path'] . "plugins/mactrack/mactrack.js'></script>\n";
```

---

## When patterns conflict

1. Prefer the style in the most recently modified files in the same directory.
2. Prefer patterns present in core integration points such as `setup.php`, view pages (`mactrack_view_*.php`), and the CI workflow.

---

## Where to place guidance files for Copilot

This file is intentionally placed at repository root `.github/copilot-instructions.md` so it is easy to find for maintainers and Copilot consumers. If additional local rules are required for specific directories, add small, targeted instruction files next to those directories and reference this canonical guidance.

---

## Next steps (optional suggestions)

- Run `php -l` across `*.php` files to ensure new code parses.
- Add small linter or style config only if it mirrors CI setup.

End of file.
