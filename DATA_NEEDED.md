# Inputs needed to complete PCRMs

## Required for database validation

- The schema is `database/schema.sql`; the human and mouse CRM files load
  into it with `database/split_crm_files.py` and `database/load.sql`.
- The gene annotation and each CRM's closest gene come from GENCODE (v50 for
  hg38, vM25 for mm10) through `database/build_genes.py`, and the motif–TF
  names from `database/match_motifs.py`.
- Confirmation of the supported PHP/MySQL versions in production.

Do not place credentials under `www/`. Use deployment environment variables or `config/database.php`.

## Download files

The download page offers gzip-compressed BED files for human and mouse
version 2 instead of tar archives (`includes/releases.php`). They are built
locally in `www/data/` (about 6.4 GB) and must be copied to the server.
