# Synthetic PCRMs database

`synthetic_fixture.sql` creates a dedicated MySQL/MariaDB database named
`pcrms_synthetic`, defines the schema used by the current PHP queries, and
loads deterministic synthetic records. It never reads from or drops the
production-named database in `config/database.example.php`.

All fixture identifiers begin with `SYNTH_`, contain `_SYN_`, or use the
explicit test TF names `TF_ALPHA`, `TF_BETA`, and `TF_SHARED`. Coordinates and
scores are fabricated and are not suitable for scientific analysis.

## Load or reset the fixture

Run from the repository root with a MySQL/MariaDB account that can create a
database:

```bash
mariadb -u YOUR_USER -p < database/synthetic_fixture.sql
```

The script is repeatable. Re-running it drops and rebuilds only the objects
inside `pcrms_synthetic`.

Point the application at it with environment variables:

```bash
export PCRMS_DB_HOST=127.0.0.1
export PCRMS_DB_PORT=3306
export PCRMS_DB_NAME=pcrms_synthetic
export PCRMS_DB_USER=YOUR_USER
export PCRMS_DB_PASSWORD='YOUR_PASSWORD'
php -S 127.0.0.1:8000 -t www
```

You can instead copy `config/database.example.php` to
`config/database.php` and set the same values there. That local file is
ignored by version control.

## Useful test cases

| Workflow | Inputs | Expected fixture behavior |
| --- | --- | --- |
| Browse CRMs | Human, `chr1`, P ≤ `0.05` | 79 rows and two result pages |
| Strict P-value | Human, `chr1`, P ≤ `0.000001` | Records remain at the strictest threshold |
| Closest CRM | Human, `SYNTH_HS_PLUS`, P ≤ `0.05` | Matches by gene symbol |
| Closest CRM | Human, `HS_SYN_GENE_PLUS`, P ≤ `0.05` | Matches the same gene by ID |
| Gene range (+) | Human, `SYNTH_HS_PLUS`, upstream/downstream/both | Exercises all positive-strand branches |
| Gene range (-) | Human, `SYNTH_HS_MINUS`, upstream/downstream/both | Exercises all negative-strand branches |
| TF search | Human, `TF_ALPHA`, `chr1` | 79 rows and two result pages |
| TF search | Human, `TF_BETA`, `chr1` | A small secondary-motif result set |
| Motif logo present | Open a human, mouse, or worm TFBS result | Uses an existing repository image |
| Motif logo absent | Open a plant or fly TFBS result | Shows “Logo unavailable” |
| Multi-gene CRM | Open `HS_SYN_CRM_OVERLAP` | Shows two associated gene symbols |

The fixture contains 5 genomes, 11 genes, 105 CRMs, 115 TFBSs, and 3
documentation-only visitor records.
