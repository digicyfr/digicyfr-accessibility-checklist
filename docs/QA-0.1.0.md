# QA — 0.1.0

## Candidate

- ZIP: `dist/digicyfr-accessibility-checklist-0.1.0.zip`, byte-identical to the public GitHub `v0.1.0` prerelease asset verified with its downloaded `.sha256` sidecar on 2026-11-05.
- SHA-256: `06ce9939ca9eb5e553ad47b2fd3384c8ff75ddc26ea0f9f2bb5873e7fd2fddf1`
- `tools/verify_zip.py --against-source`: all 12 allowlisted release files match the source byte for byte. Tests, release manifest, and development docs are excluded.
- Tested stack: WordPress 7.1, PHP 8.3.33, MariaDB 11.4.13. These are tested versions, not a claim about every newer version.

## Automated gates

- PHP lint and `node --check` on the admin script: passed.
- WordPress Coding Standards on runtime PHP: passed with no findings.
- Disposable exact-ZIP install, activation, deactivation, and reactivation: passed.
- Real WordPress exact-ZIP integration: passed. It checks private CPT flags and mapped administrator capabilities; bundled Polish translations; all six guided prompts; same-site scope and safe evidence URLs; invalid, future, and impossible dates; required notes; page permalink snapshots including a rejected external filtered permalink; current tester attribution and preservation on later note correction; repeat observations; literal backslash storage; pagination and combined filters; filtered 19-column CSV, Polish/multiline content and formula protection, and explicit rejection over 20,000 records; editor/anonymous read and edit denial, direct admin-post capability and nonce checks; field error associations on all outcome radios; removed-source label; site-local created timestamp in Europe/Warsaw; safe external evidence link; deleted assignee; permanent delete; retention on deactivation; and uninstall erasure including trashed observations and protected notes.
- Plugin Check on the runtime-identical earlier ZIP: one `plugin_updater_detected` ERROR because the deliberate Digicyfr `Update URI` is incompatible with WordPress.org hosting. This ZIP is for manual self-hosted distribution. One `load_plugin_textdomainFound` WARNING reflects the bundled Polish catalog outside WordPress.org. No other findings remained.

## Browser and accessibility

- Root's Chromium check on the runtime-identical earlier ZIP passed: keyboard New and Save, required date/note validation with field errors, creation of a Needs work observation, CSV download with a guarded `=SUM` note, delete confirmation, and no page overflow at 390px. The disposable site was removed.
- The form has visible instructions and labels, a linked error summary with field-specific errors, error associations on radio inputs, and focus handling after save. The source and rendered invalid-form structure were checked; a screen-reader session has not been run.

## Post-tag browser check on the exact public ZIP

On 2026-11-05, the published ZIP and SHA sidecar were downloaded from the public GitHub prerelease and verified (`06ce9939ca9eb5e553ad47b2fd3384c8ff75ddc26ea0f9f2bb5873e7fd2fddf1`). A fresh disposable WordPress 7.1 / PHP 8.3.33 / MariaDB 11.4.13 site installed, activated, deactivated, and reactivated that exact ZIP. Chromium then exercised the administrator interface with synthetic data:

- A new Journey observation with `Needs work` and missing date/note returned linked validation errors. The entered values were retained, the invalid fields had `aria-invalid`, and the error summary received focus. [Invalid form](evidence/0.1.0/invalid-form.png).
- Supplying a site-local date and note saved the observation. The success notice received focus and the private list displayed the record. [Saved observation](evidence/0.1.0/saved-observation.png).
- Combined scope-name and outcome filters returned the one matching record. The filtered CSV download contained it and guarded a note beginning `=SUM(1,2)` with a leading apostrophe. [Filtered list](evidence/0.1.0/filtered-list.png).
- At a 390px viewport, the filtered list and new form fit the document width. The table scrolled inside its container (768px table inside a 368px container); the document width remained 390px. [Narrow list](evidence/0.1.0/filtered-list-390px.png) · [Narrow form](evidence/0.1.0/new-form-390px.png).
- An anonymous request to the admin page redirected to login; an Editor account received WordPress's access-denied page on the direct admin URL. Chromium reported no page errors in the administrator flow.

The browser check used scripted interaction and focus assertions. A human screen-reader session and long-term operational use remain open. All browser fixture data was synthetic; the disposable site and its database were removed.

## Current artifact recheck and release limits

On 2026-11-05, the rebuilt ZIP and SHA-256 sidecar matched the standalone and workspace packagers. This exact rebuilt ZIP passed install/activate/deactivate/reactivate and the complete WordPress integration fixture on the tested stack. The post-tag browser check above used the exact public GitHub ZIP; the earlier Plugin Check result is from a byte-different ZIP with identical runtime files.

- The plugin records human observations only. An unrecorded check is unknown; it does not scan, alter, score, certify, or claim WCAG conformance for a site.
- Private records can contain sensitive notes or URLs. Only administrators can view, change, or export them. Deactivation retains records; uninstall deletes them. Version 0.1.0 has no immutable history of edits.
- A public GitHub prerelease exists. No digicyfr.com production publication, WordPress.org submission, or live customer-site installation was performed.
