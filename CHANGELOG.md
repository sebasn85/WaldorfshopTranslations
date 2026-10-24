# Changelog

## 0.5.0 — 2026-10-24 (local preparation, not deployed)

- Explicit, context-bound approval to keep existing translated fields when German is empty. No automatic acceptance or deletion.
- Read-only previews use an overlay; approval choices and original baselines are part of the review token.
- Clear stored conflicts only after a fresh successful review; clear historical validation errors after the recovered job completes.
- Added synthetic numeric spelling examples that verify reviewed corrections without relaxing number validation.
- 144 local tests pass. Plenty runtime verification and live approval remain outstanding.

## 0.4.0 — 2026-10-24 (recovery and budget safeguards)

- Add operator-only recovery previews and token-bound resumes, including validated, explicitly reviewed field corrections. Existing paid cache entries remain reusable. No bulk unblock, automatic deletion or build-time recovery.
- Revalidate prepared fields before applying them. Accept only equivalent HTML quoting, attribute order and void-tag notation while preserving tags, attributes, links and numeric checks.
- Retain structured error codes with phase/language/field, and log bounded nightly outcomes without raw SDK exception contents.
- Request provider-reported billed characters, record per-field request states, and hold ambiguous paid requests for review instead of repeating them blindly.
- Expose account billing-period usage separately from the local calendar-month reservation. Check the account's current usage before new paid requests.
- Add a pure SDK smoke check and test-only, read-only review/in-memory replay diagnostics. Production plugin metadata excludes the data-reading diagnostic migration.

## 0.3.0 — 2026-10-22

### Added
- Plenty SDK compatibility: explicit closure method calls and native date factories; a pure build-time runtime smoke check makes SDK failures visible before production deployment.
- Daily durable translation queue during 02:00–08:00 Europe/Berlin, driven by the existing 15-minute cron. Snapshot once per local day, persist pages of 100 candidates and resume up to 25 articles / five minutes per invocation.
- Skip existing blocked/review jobs without unblocking them. Isolate article validation failures, recheck triggers, and resume after temporary time, API and budget pauses using existing paid caches.
- Bound outbound requests by the package deadline and end of the night window. Keep actual-source, number, HTML, URL and readback safeguards.
- Expose nightly progress and setup failures through the existing protected status route. Report API release 0.3.0 consistently.
- 46 added tests for 111 total, covering queue/package boundaries, 120 articles, DST, lock release, cancelled triggers, shared outages, budget stops and crash recovery with real worker logic.

### Deployment notes
- Prepared in an isolated local feature branch from the available 0.2.3 source snapshot (upstream base b91023cd1a8b04177df73fabcf81f9b3d4622973).
- External repository publication and Plenty test build are pending because connected browser access is currently unavailable. No live set, configuration, tag, budget or article has been changed by this preparation.
- Apply these changes to the full existing repository; this local checkout is a source snapshot and does not include all SDK/provider/library files from the full plugin.
- A separate concrete approval remains required immediately before any live deployment.

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
