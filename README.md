# Digicyfr Accessibility Checklist

Free GPL WordPress plugin for private, dated **human** accessibility observations on a chosen page or key journey. Administrators choose one of six guided checks, record what they tested, assign an informational owner, and export a filtered work log. It does not scan, alter the public site, make a WCAG conformance claim, or infer that missing checks passed.

## Install and use

Upload the 0.1.0 ZIP at **Plugins → Add New → Upload Plugin**. Tested locally with WordPress 7.1, PHP 8.3.33 and MariaDB 11.4.13. The plugin declares WordPress 7.1+ and PHP 8.3+.

1. An administrator opens **Accessibility Checks** and selects **New observation**. Choose Page or Journey, name the specific page/task, and choose a guided check. For a Page, enter a published post/page ID or a complete same-site URL. For a Journey, enter a complete same-site start URL.
2. Follow the visible human-testing prompt and its W3C reference. Choose **Not tested**, **Observed pass**, **Needs work**, or **Not applicable**. The latter two require a plain-text note explaining the problem or reason. A completed outcome requires a real site-local test date no later than today. The tester is set from the signed-in administrator on completion; a later correction to only the note/assignee preserves the original tester. A new attempt should be a new observation.
3. Filter the 50-row-per-page private list by scope name/type, check, outcome, assignee ID, or test date. An assignee does **not** gain access: viewing, saving, deleting and exporting require `manage_options`. Deleting has a separate confirmation page.
4. Export exactly the current filter as UTF-8 CSV. The fixed columns are `record_id,scope_kind,scope_label,scope_post_id,scope_url,test_key,test_label,outcome_key,outcome_label,assignee_user_id,assignee_name,tester_user_id,tester_name,tested_on,note,evidence_url,created_at_utc,updated_at_utc,coverage_note`. Every user-influenced cell is protected against spreadsheet formulas. Exports with more than 20,000 filtered records are rejected with a clear error; the plugin never sends a silently shortened CSV.

## Scope and privacy

The six prompts are keyboard access, visible focus, zoom/reflow, forms/errors, image meaning, and a key journey. [W3C Easy Checks](https://www.w3.org/WAI/test-evaluate/preliminary/) is preliminary guidance. A full accessibility evaluation needs more scope and methods; see [W3C evaluation resources](https://www.w3.org/WAI/test-evaluate/). An observation records what a person saw in one scope. **Unrecorded checks are unknown.** There is no score, badge, certificate, legal verdict, automated checker, overlay, accessibility statement, or fix switch.

Records use a private, nonexportable WordPress custom post type with registered protected metadata. There is no public route, REST route, shortcode, block, feed, sitemap or front-end output. The URL of a page ID is captured at observation time; the list resolves the current permalink and labels a changed or removed source. Repeating a check creates another record. Editing corrects the existing record; v0.1 has no immutable audit trail or revisions.

Notes and evidence URLs can contain personal or private information and appear in CSV. Do not paste tokenized or private URLs unless that disclosure is appropriate. External HTTP(S) evidence links are permitted but are **never fetched** by the plugin. No telemetry, email, cron, screenshot storage, or outbound request is made. Deactivation retains observations. **Uninstall permanently deletes all checklist posts and their metadata, including notes and URLs**; export needed records first.

Version 0.1.0 uses manual ZIP updates from Digicyfr. Its unique `Update URI` prevents an unrelated directory update; no automatic update endpoint exists.

Source: [github.com/digicyfr/digicyfr-accessibility-checklist](https://github.com/digicyfr/digicyfr-accessibility-checklist). For ordinary support use [Issues](https://github.com/digicyfr/digicyfr-accessibility-checklist/issues); for vulnerabilities use [private security reporting](SECURITY.md).

## Development

```bash
python3 scripts/release.py
python3 scripts/release.py --verify dist/digicyfr-accessibility-checklist-0.1.0.zip
(cd dist && sha256sum -c digicyfr-accessibility-checklist-0.1.0.zip.sha256)
bash tests/run-wordpress-integration.sh dist/digicyfr-accessibility-checklist-0.1.0.zip
```

License: GPL-2.0-or-later; see [LICENSE](LICENSE).
