# 2026 WAEC PDF subject repair

The listing wraps subject labels, hyphens and grades across lines and page headers. The old line classifier dropped label-only lines, producing `(ELECTIVE)`, `ART`, `STUDIES` and `ACCOUNTING`, and losing other outcomes. Standalone labels must not be globally renamed: some ACCOUNTING fragments are Principles of Cost Accounting, not Financial Accounting.

The parser now joins only recognised complete subject names followed by an explicit grade/status, before classifying lines. Page boundaries remain in place outside joined labels to protect student names. Spaced parentheses and hyphenated subject names are handled. Explicit `*` and `P` source statuses remain source statuses, not inferred W results.

## Verified local repair

- Upload: `WAEC20261016150509_4d04d54b`
- Source: `WAEC Results Listing 2026.pdf`; the supplied and saved PDFs matched by SHA-256.
- 482 candidates, 27 subjects, 3,856 explicit outcomes (eight per candidate).
- 3,784 graded outcomes and 72 source-status outcomes.
- The old 3,282 parsed rows were replaced only for this upload. Candidate IDs, saved names/genders, source file and other batches were preserved.
- Recovery copies of the affected upload and its result/status rows were verified before replacement:
  - `tblwaecupload_subj_bak_20261016151158_a471`
  - `tblwaecresult_subj_bak_20261016151158_a471`
  - `tblwaecwithheld_subj_bak_20261016151158_a471`

The repair checks candidate identity agreement, eight explicit outcomes per source candidate, an independent comma-delimited source ledger, duplicate outcomes, transactional table engines, concurrency, backups and post-write results. It preserves saved names and genders. It defaults to dry run and will not reapply when saved outcomes already match.

## Live server

Deploy the updated `waec-analysis.php` and `waec-subject-utils.php` together. Deploying code alone does not repair previously stored live results. The local repair above does not update the live database.

For an existing live upload, back up the database and run the CLI-only `tests/repair-waec-pdf-subjects.php` against its own upload ID and matching original PDF, first without `--apply`. Review the dry-run output before explicitly applying it. This strict repair is intended for the eight-outcome listing format validated here; it stops rather than guessing if a different listing fails validation. Do not create duplicate live uploads to work around existing data without reviewing the original upload first.

## Checks

`php tests/waec-pdf-subject-regression.php --pdf="PATH/WAEC Results Listing 2026.pdf"`

Existing name, gender, analysis and comparison regression checks also passed. No existing 2024 or 2025 saved results were rewritten.
