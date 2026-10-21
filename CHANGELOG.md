# Changelog

## 0.2.3 — 2026-10-21 (test release)

### Fixed
- Normalize only boundary ASCII whitespace in plain-text translation output and readback comparisons; preserve internal whitespace and exact HTML checks.
- Use the same comparison during partial-write recovery, retaining paid outputs and skipping already saved equivalent values.
- Persist actual verified backend values as the baseline and include language/field in future readback errors.
- Five regression checks reproduce Plenty trimming and existing partial jobs, while retaining protection for internal text changes, HTML whitespace and manual edits. 64 checks total.

### Update notes
- No diagnostic build hook, automatic unblocking, configuration/tag changes or production deployment. Existing blocked jobs require a separately controlled resume.

## 0.2.2 — 2026-10-21 (test release)

### Added
- Translate name 2, name 3, webshop HTML title, meta keywords and technical data in addition to the original four fields; URL paths remain excluded.
- Preserve technical-data HTML and attributes; enforce the 240-character limit for all three names and the HTML title.
- Nine regression checks cover all nine fields across five languages, existing baseline expansion, selective refresh, manual edits, empty sources, readback failures and the HTTP write allowlist. 59 checks total.

### Update notes
- Existing completed articles need the trigger tag again to fill added fields; unchanged tracked fields are retained without new paid requests.
- Keep partially written older jobs for manual review. No automatic activation, tag/configuration changes or production deployment is performed by this release.

## 0.2.1 — 2026-10-20 (test release)

### Fixed
- Replace is_scalar with supported explicit type checks for the Plenty SDK.
- Use unquoted Plenty translation property values.

### ToDo
- When migrating from the legacy configuration format, explicitly re-save API access, IDs and operating switches in the new form.

## 0.2.0 — 2026-10-20 (test release)

### Added
- Native DE/EN configuration with target-language checkboxes for the 18 supported non-German Plenty languages, and English/Portuguese/Chinese variant selectors.
- Configurable Plenty backend/API access, DeepL Free/Pro, trigger/review tag IDs, glossary ID, context and monthly budgets.
- Read-only authenticated configuration check for variation tags, DeepL languages, HTML support and required glossary dictionaries.
- Configuration and gateway regression coverage: 50 tests total.

### Changed
- Only selected target languages are processed and verified. Deselected rows and baselines are retained.
- Pending jobs and response caches include the effective target/tag/glossary/context policy.
- New shops require their own IDs and credentials and do not inherit historical pilot reservations.
- All installations remain disabled/preview by default. Existing saved switches and budgets are retained; keep the test set disabled during update.

### ToDo
- Deploy and validate this version only in the authorized test set before production release.
- Marketplace packaging, metadata, complete product documentation and Plenty review are separate from this test release.

## 0.1.2 — 2026-10-20

### Fixed
- Compare product numbers in decoded visible HTML text so encoded French apostrophes do not appear as numbers.
- Retain paid raw provider replies for safe revalidation and diagnostics.

### Changed
- 31 regression tests. Successful real single-item write/readback and tag transition in test set 434.
