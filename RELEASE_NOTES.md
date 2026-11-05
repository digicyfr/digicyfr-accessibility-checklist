# digicyfr-accessibility-checklist 0.1.0 release notes

This is the first free GPL-2.0-or-later release for manual ZIP installation. Source: https://github.com/digicyfr/digicyfr-accessibility-checklist. The `release/0.1.0` branch records the reviewed version. The branch alone is source code; use the attached `digicyfr-accessibility-checklist-0.1.0.zip` and verify its `.sha256` sidecar before installation.

## Included

Private human accessibility observations with six guided checks, explicit page/journey scope, date, assignee, note, evidence URL, filters, and guarded CSV.

## Verification and compatibility

WordPress 7.1, PHP 8.3.33 and MariaDB 11.4.13. The exact public GitHub ZIP passed installation and integration fixtures including permissions and uninstall. A post-tag Chromium check on that ZIP passed admin form validation, save and focus handling, combined filters, guarded CSV export, 390px layouts, and anonymous/Editor access denial. PHP/JS syntax and WPCS passed. Plugin Check ran on an earlier byte-different ZIP with identical runtime files and reported only the documented self-host distribution findings.

## Limits and follow-up checks

A manual screen-reader pass, multisite behavior, and long-term operational use remain untested. This plugin does not scan, fix, score or certify WCAG conformance. Uninstall permanently deletes observations, including private notes.

An axe-core 4.10.3 audit of the exact public v0.1.0 ZIP flagged the new-observation guidance container: its `aria-label` was on a generic `<div>` without a role. Main now adds `role="group"`, and a targeted rerun cleared that finding. The attached v0.1.0 ZIP remains unchanged and still has the old markup; a new versioned build is required before stable promotion. Native-control color-contrast checks that axe could not determine remain for human review. See the [dated audit evidence](docs/QA-0.1.0.md).

The `Update URI` protects this self-hosted plugin from an unrelated WordPress.org plugin with the same slug. It does not provide automatic updates. WordPress.org submission would need a separate review and distribution-specific changes. See the [post-tag QA record on main](https://github.com/digicyfr/digicyfr-accessibility-checklist/blob/main/docs/QA-0.1.0.md) for the detailed evidence and screenshots.
