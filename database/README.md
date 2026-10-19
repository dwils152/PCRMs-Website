# PCRMs database

`schema.sql` defines the tables. `synthetic_fixture.sql` fills them with a
small synthetic data set for development; the real data is loaded as
described under [Real data](#real-data).

## Synthetic data for development

`synthetic_fixture.sql` loads deterministic synthetic records into a database
created with `schema.sql`. All fixture identifiers begin with `SYNTH_`,
contain `_SYN_`, or use the explicit test TF names `TF_ALPHA`, `TF_BETA`, and
`TF_SHARED`. Coordinates and scores are fabricated and are not suitable for
scientific analysis.

Run from the repository root with a MySQL/MariaDB account that can create a
database:

```bash
mysql -u YOUR_USER -p -e 'CREATE DATABASE pcrms_synthetic CHARACTER SET utf8mb4 COLLATE utf8mb4_bin'
mysql -u YOUR_USER -p pcrms_synthetic < database/schema.sql
mysql -u YOUR_USER -p pcrms_synthetic < database/synthetic_fixture.sql
```

Re-running `synthetic_fixture.sql` replaces the data in that database only.

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

### Useful test cases

| Workflow | Inputs | Expected fixture behavior |
| --- | --- | --- |
| Browse CRMs | Human, `chr1`, P ≤ `0.05` | 79 rows on four pages of 25 |
| Strict P-value | Human, `chr1`, P ≤ `0.000001` | Records remain at the strictest threshold |
| Closest CRM | Human, `SYNTH_HS_PLUS`, P ≤ `0.05` | Matches by gene symbol |
| Closest CRM | Human, `HS_SYN_GENE_PLUS`, P ≤ `0.05` | Matches the same gene by ID |
| Gene range (+) | Human, `SYNTH_HS_PLUS`, upstream/downstream/both | 41, 1 and 43 CRMs |
| Gene range (-) | Human, `SYNTH_HS_MINUS`, upstream/downstream/both | 1, 1 and 2 CRMs |
| TF search | Human, `TF_ALPHA`, `chr1` | 79 rows on four pages of 25 |
| TF search | Human, `TF_BETA`, `chr1` | 2 rows from the secondary motif |
| Motif logo present | Open a human or mouse TFBS result | Uses an existing repository image |
| Multi-gene CRM | Open CRM `78` (`crm.php?crmId=78&genome_id=1`) | Shows two associated gene symbols |

The fixture contains 5 genomes, 11 genes, 105 CRMs, 115 TFBSs, and 3
documentation-only visitor records. Importing it prints the numeric IDs of
the labelled human CRMs.

## Real data

`schema.sql` is in third normal form: each fact is stored once, in the table
whose key it depends on, and the lists the pages show (gene symbols per CRM,
TF names per motif) are built when a page asks for them. CRM IDs are numbers
in genomic order, motifs have their own `umotifs` table, and CRMs and motifs
belong to a dataset, one CRM map of a genome (`datasets`: genome, dataset
number, method, version), so a genome can have both a dePCRM2 map and a
Nucleotide Transformer map.

Build the database from one tab-separated file per table:

1. Split the CRM files (one per dataset, plain or gzipped) into
   `chromosomes.tsv`, `crms.tsv`, `umotifs.tsv` and `tfbss.tsv`. Name each
   file `GENOME:DATASET=FILE`:

   ```bash
   python3 -I database/split_crm_files.py split/ \
       1:1=Human_CRMCs_TFBSs_hg38.bed 2:1=Mouse_CRMCs_TFBSs_mm10.bed
   ```

   Genome IDs are those in `includes/catalog.php` (1 human, 2 mouse,
   3 worm, 4 thale cress, 5 fruit fly); dataset 1 is each genome's dePCRM2
   map. For a new genome or map, such as the Nucleotide Transformer
   predictions, first add its rows to `genomes` and `datasets` in `load.sql`. Fly, worm and thale cress are shown as
   coming soon until their data are loaded and `searchable` is set to `true`
   for them in `includes/catalog.php`.

   Each line must have six columns: chromosome, start, end, binding sites,
   CRM score and P-value. The script stops on anything that breaks the
   schema: a missing P-value, a duplicate CRM or site, a site outside its CRM,
   or a motif with two widths or motif scores.

2. Add three files that are not in the CRM files, tab-separated, no header.
   Two scripts write them (sections below); join the species' files with
   `cat`.

   | File | Columns | Written by |
   | --- | --- | --- |
   | `genes.tsv` | geneID, genome ID, symbol, chromosome, start, end, strand (`+`/`-`) | `build_genes.py` |
   | `crm_genes.tsv` | genome ID, dataset, chromosome, CRM start, CRM end, geneID | `build_genes.py` |
   | `motif_tfs.tsv` | genome ID, dataset, motif number, TF name (one row per motif–TF pair) | `match_motifs.py` |

   They name CRMs by location and motifs by their number, because the numeric
   IDs only exist after step 1.

3. Create the tables in an empty database and load the files:

   ```bash
   mysql PCRMSV7 < database/schema.sql
   cd split/
   mysql --local-infile=1 --show-warnings PCRMSV7 < ../database/load.sql
   ```

   `LOAD DATA LOCAL` skips bad rows with a warning instead of stopping, so any
   warning means rows are missing. `load.sql` ends with integrity checks, which
   must all be 0, and row counts per genome.

## Motif files and logos

ProSampler writes one motif per `cluster_<number>.….meme.bio` file.
`clean_meme.py` rewrites a folder of them in the standard MEME motif format
that MEME-suite tools such as Tomtom and FIMO read. It names each motif by its
number (its `umID` in the database), drops ProSampler's run log, and writes
one `<number>.meme` per motif plus `all_motifs.meme` with all of them:

```bash
python3 -I database/clean_meme.py Human-v2/umotif Human-v2/umotif_clean
```

`plot_logos.py` draws each motif as an SVG logo with
[logomaker](https://logomaker.readthedocs.io/), in bits against the motif's
background, with MEME's letter colours. The site shows
`www/motifs_version/<genome ID>/<number>.svg`, or an older `.png` where no SVG
exists. logomaker is not needed on the web server; install it wherever you
draw the logos:

```bash
python3 -m venv .venv && .venv/bin/pip install logomaker
.venv/bin/python database/plot_logos.py www/motifs_version/1 Human-v2/umotif_clean/[0-9]*.meme
.venv/bin/python database/plot_logos.py www/motifs_version/2 Mouse-v2/umotif_clean/[0-9]*.meme
```

## Naming the TFs each motif matches

`match_motifs.py` compares the cleaned motifs with four databases of known
TF motifs using Tomtom (MEME suite) and writes `motif_tfs.tsv`, the motif–TF
file the loader needs. All four are searched in one run, with the Tomtom web
server's settings (Pearson correlation, overlap of at least 5, no small-sample
correction), and pairs with q ≤ 0.1 are kept. To try another cutoff without
running Tomtom again, rerun with `--q <value> --reuse-tomtom`.

| Database | File used | From |
| --- | --- | --- |
| JASPAR 2026 | CORE vertebrates non-redundant, MEME format | jaspar.elixir.no/downloads |
| HOCOMOCO v14 | `H14CORE_meme_format.meme` and `H14CORE_annotation.jsonl` (gene symbols) | hocomoco14.autosome.org/downloads_v14 |
| CIS-BP 3.10 | `PWMs.zip` and `TF_Information_all_motifs.txt` (from `TF_Information.zip`); directly determined motifs only | cisbp.ccbr.utoronto.ca/entireDownload.php |
| UniPROBE (PBM) | `MOUSE/uniprobe_mouse.meme` from the MEME Suite motif databases 12.28 | meme-suite.org |

```bash
conda create -n meme -c conda-forge -c bioconda meme
python3 -I database/match_motifs.py --species human --genome 1 --dataset 1 \
    --umotifs Human-v2/umotif_clean/all_motifs.meme --out matches/human \
    --jaspar JASPAR2026_CORE_vertebrates_non-redundant_pfms_meme.txt \
    --hocomoco H14CORE_meme_format.meme --hocomoco-annotation H14CORE_annotation.jsonl \
    --cisbp-pwms PWMs.zip --cisbp-info TF_Information_all_motifs.txt \
    --uniprobe uniprobe_mouse.meme --tomtom "$(conda run -n meme which tomtom)"
```

Each run writes `motif_matches.tsv` (every match, with database, TF names,
p-, E- and q-values, for review) and `motif_tfs.tsv`. TF names follow the
species' gene-symbol style (CTCF, Ctcf); JASPAR dimers such as FOS::JUN
count for both TFs. To replace the names in a database that is already
loaded, without reloading anything else:

```bash
cat matches/human/motif_tfs.tsv matches/mouse/motif_tfs.tsv > motif_tfs.tsv
mysql --local-infile=1 --show-warnings PCRMSV7 < database/load_motif_tfs.sql
```

## Genes and each CRM's closest gene

`build_genes.py` reads a GENCODE annotation and the `crms.tsv` written by
`split_crm_files.py`, and writes `genes.tsv` and `crm_genes.tsv`. A CRM's
closest gene is the protein-coding gene whose transcription start site is
nearest to it (distance 0 when the start site lies inside the CRM); genes
tied for nearest are all kept. `--all-genes` keeps every gene type, such as
lncRNAs. Coordinates are written BED style like the CRM files, and gene IDs
are Ensembl IDs without their version.

The site uses GENCODE v50 for hg38 and vM25, the last release on mm10, for
mouse (ftp.ebi.ac.uk/pub/databases/gencode/):

```bash
python3 -I database/build_genes.py --gtf gencode.v50.annotation.gtf.gz \
    --crms split/crms.tsv --genome 1 --dataset 1 --out genes/human
python3 -I database/build_genes.py --gtf gencode.vM25.annotation.gtf.gz \
    --crms split/crms.tsv --genome 2 --dataset 1 --out genes/mouse
```

To replace the genes and links in a database that is already loaded:

```bash
cat genes/human/genes.tsv genes/mouse/genes.tsv > genes.tsv
cat genes/human/crm_genes.tsv genes/mouse/crm_genes.tsv > crm_genes.tsv
mysql --local-infile=1 --show-warnings PCRMSV7 < database/load_genes.sql
```
