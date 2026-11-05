# digicyfr-accessibility-checklist 0.1.0 release notes

This is the first free GPL-2.0-or-later release for manual ZIP installation. Source: https://github.com/digicyfr/digicyfr-accessibility-checklist. The `release/0.1.0` branch records the reviewed version. The branch alone is source code; use the attached `digicyfr-accessibility-checklist-0.1.0.zip` and verify its `.sha256` sidecar before installation.

## Included

Private human accessibility observations with six guided checks, explicit page/journey scope, date, assignee, note, evidence URL, filters, and guarded CSV.

## Verification and compatibility

WordPress 7.1, PHP 8.3.33 and MariaDB 11.4.13. The exact ZIP passed integration fixtures including permissions and uninstall, browser keyboard and 390px checks, PHP/JS syntax, WPCS, and Plugin Check with only self-host distribution findings.

## Limits and follow-up checks

A manual screen-reader pass, multisite behavior, and long-term operational use remain untested. This plugin does not scan, fix, score or certify WCAG conformance. Uninstall permanently deletes observations, including private notes.

The `Update URI` protects this self-hosted plugin from an unrelated WordPress.org plugin with the same slug. It does not provide automatic updates. WordPress.org submission would need a separate review and distribution-specific changes. See [QA record](docs/QA-0.1.0.md) for the detailed local evidence.
