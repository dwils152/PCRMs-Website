# Inputs needed to complete PCRMs

## Required for database validation

- The production MySQL schema, including views, indexes, and table definitions,
  is still needed to confirm that it matches the query-compatible development
  schema in `database/synthetic_fixture.sql`.
- A deterministic local fixture is now available in
  `database/synthetic_fixture.sql`. A small anonymized production fixture or
  database access path is still needed for production-data validation.
- Confirmation of the supported PHP/MySQL versions in production.
- Confirmation that the server-side result queries have supporting indexes, especially `Genome`/`Chromosome`/`P_value`, CRM IDs, motif IDs, and TF IDs.

Do not place credentials in chat or under `www/`. Use deployment environment variables or `config/database.php`.

## Missing release archives

The original download page references these files, but they are not present in `www/data/`:

- `Human_CRMCs_TFBSs_v2_hg38.bed.tar.gz`
- `Human_CRMCs_TFBSs_v1_hg38.bed.tar.gz`
- `Mouse_CRMCs_TFBSs_v2_mm10.bed.tar.gz`
- `Mouse_CRMCs_TFBSs_v1_mm10.bed.tar.gz`

Large archives can instead be supplied as durable object-storage or institutional-repository URLs.

## Metadata that needs confirmation

- Canonical CRM counts: the live database counts differ from the version 2 counts shown in the original homepage and download copy.
- Mouse version 2 assembly policy: the source describes both mm10 and a CRM-only mm39 liftover.
- Release dates and any updates after June 2022.
- Data license/usage terms and a concise BED-column data dictionary.
- Preferred support/contact address and the current Su Lab URL.
- Whether Arabidopsis and Drosophila motif logos should exist. Motif images are currently available only for genome IDs 1–3.
- Current funding acknowledgements and preferred citation text.
- CRM counts for the version 1 genomes (fly, worm, thale cress). The home page shows the assembly until `crm_count` is set in `www/includes/releases.php`.
- Worm chromosome names: the database uses `chr1`–`chr5` and has no X. The UCSC links assume these are `chrI`–`chrV` in ce10.
- Fly `chrY`: dm3 at UCSC has no plain `chrY`, so links for CRMs on it will not resolve.
- Whether CRM and TFBS coordinates are 0- or 1-based (this shifts UCSC highlights by one base).

Until these are confirmed, the interface labels the collection from the existing source material and marks absent archives as unavailable instead of exposing broken links.
