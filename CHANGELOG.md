# Changelog

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
