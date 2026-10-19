# Inputs needed to complete PCRMs

## Required for database validation

- The schema is `database/schema.sql`; the human and mouse CRM files load
  into it with `database/split_crm_files.py` and `database/load.sql`.
- The gene annotation and each CRM's closest gene come from GENCODE (v50 for
  hg38, vM25 for mm10) through `database/build_genes.py`, and the motif–TF
  names from `database/match_motifs.py`.
- Confirmation of the supported PHP/MySQL versions in production.

Do not place credentials in chat or under `www/`. Use deployment environment variables or `config/database.php`.

## Download files

The download page offers gzip-compressed BED files for human and mouse
version 2 instead of tar archives (`includes/releases.php`). They are built
locally in `www/data/` (about 6.4 GB) and must be copied to the server; they
are not in git. The version 1 files, the mouse mm39 liftover and the fly, worm
and thale cress files are held back in `data-incoming/downloads-on-hold/` until those releases are
offered again.

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
