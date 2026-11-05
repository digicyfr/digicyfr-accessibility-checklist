# digicyfr-accessibility-checklist 0.1.1 release notes

Free GPL-2.0-or-later release for manual WordPress ZIP installation. Source: https://github.com/digicyfr/digicyfr-accessibility-checklist. The protected `release/0.1.1` branch and `v0.1.1` tag record the reviewed source. Install the attached ZIP, not GitHub's source archive, and check its SHA-256 sidecar.

## Fix since 0.1.0

The new-observation form's guided instructions now use a named `group` role. The v0.1.0 ZIP had an `aria-label` on a generic `div`; axe-core flagged that label as unreliable for assistive technology. A focused integration assertion guards the repaired markup. The v0.1.0 tag, branch, and ZIP have not been changed.

## Included and tested scope

The plugin records private human accessibility observations for a specific page or journey. Six guided checks, date, assignee, note, evidence URL, filters, and guarded CSV are included. It does not scan a site or issue a WCAG conformance verdict.

WordPress 7.1, PHP 8.3.33 and MariaDB 11.4.13 are the tested stack. The exact ZIP passes syntax, WordPress integration, permissions and uninstall checks. Browser and axe-core evidence is in [docs/QA-0.1.1.md](docs/QA-0.1.1.md).

## Limits

Manual screen-reader use, multisite behavior, and long-term operational use have not been verified. Some native-control color-contrast checks are inconclusive in automated tooling. Records and notes are deleted on uninstall. The unique `Update URI` prevents a directory slug collision but does not provide automatic updates. WordPress.org distribution would require separate review.
