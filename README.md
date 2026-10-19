# PCRMs website

PCRMs is a server-rendered PHP/MySQL portal for browsing predicted cis-regulatory modules (CRMs), searching by gene or transcription factor, inspecting CRM/TFBS records, and downloading genome-wide releases.

## Local requirements

- PHP 8.x with `mysqli` and `mysqlnd`
- MySQL or MariaDB containing the PCRMs schema and data
- A web server whose document root is `www/`

No JavaScript build step is required. The modern interface is implemented with PHP templates, `www/css/modern.css`, and small browser scripts.

## Database configuration

Credentials must not be stored under `www/`. Configure the deployment with either:

1. Environment variables: `PCRMS_DB_HOST`, `PCRMS_DB_PORT`, `PCRMS_DB_NAME`, `PCRMS_DB_USER`, and `PCRMS_DB_PASSWORD`; or
2. A private config file copied from `config/database.example.php` to `config/database.php`.

`config/database.php` is excluded from version control. Environment variables take precedence over the optional local config file.

Visitor-location logging is disabled by default. Set `PCRMS_ENABLE_VISITOR_TRACKING=1` only if the deployment has an appropriate privacy notice and retention policy.

## Database

The tables are defined in [`database/schema.sql`](database/schema.sql). For
development, [`database/synthetic_fixture.sql`](database/synthetic_fixture.sql)
fills them with a small synthetic data set that exercises pagination, every
search workflow, detail pages, both gene strands, all P-value thresholds, and
present/missing motif-logo states.

See [`database/README.md`](database/README.md) for loading the synthetic set
or the real data, and a test-case matrix.

## Run locally

After configuring the database:

```bash
php -S 127.0.0.1:8000 -t www
```

Open `http://127.0.0.1:8000/`. PHP errors are hidden from the page; run with `PCRMS_DEBUG=1 php -S …` to see them while developing. The home, browse, functional analysis and download pages render without the database; results pages show a "temporarily unavailable" page (HTTP 503) when it cannot be reached.

## Structure

- `www/index.php` — home page
- `www/browse_dataset.php` → `www/get_crms.php` — browse CRMs by genome, chromosome and p-value
- `www/detailed_search.php` — functional analysis, three tabs (`?tool=closest|range|tf`) that post to
  `get_genes_closest_crm.php`, `get_crm_in_genes_range.php` and `get_tf.php`
- `www/crm.php` — CRM record with its binding sites (`tfbs.php` and the old `DS_*.php` pages redirect)
- `www/download_data.php` — release archives in `www/data/`
- `www/server_side_process.php`, `www/server_side_tf.php` — JSON pages of the large result tables
- `www/export.php` — CSV export of browse and TF results (up to 250,000 rows)
- `www/404.php` — not-found page (`www/.htaccess` points Apache at it)
- `www/css/modern.css` — all styles; `www/js/` — small scripts (menu, forms, results tables)

Code that pages include lives outside the web root, so it cannot be requested directly:

- `includes/bootstrap.php` — security headers, error display and session cookie settings
- `includes/connection.php` — database connection and the 15-second statement timeout
- `includes/layout.php` — page head, header and footer; `includes/partials/` holds their markup
- `includes/catalog.php` — genomes, UCSC assemblies, validation and message pages
- `includes/forms.php`, `results.php`, `queries.php` — shared form pieces, results tables and SQL
- `includes/releases.php`, `publications.php` — download manifest, citation and related papers
- `includes/svg/` — inline organism silhouettes and pipeline figures
- `tracker/` — optional visitor logging
- `database/grants.sql` — read-only grants for the website's database account

Results tables keep their state in the address (`page`, `size`, `sort`, `dir`, `search`), so paging,
sorting and filtering work without JavaScript; the scripts redraw the table in place when it is available.
CRM IDs and binding sites link to the UCSC Genome Browser (hg38, mm10, dm3, ce10, and TAIR10 through
the GenArk assembly `GCF_000001735.3`).

See [`DEPLOYMENT.md`](DEPLOYMENT.md) before putting the site online.

## Security note

Earlier source files contained plaintext database credentials. Those values have been removed, but both exposed database accounts must be rotated in MySQL before this site is redeployed. Query handlers now validate request values and use prepared statements. The broad CRM and TF result tables use database-backed pagination so they do not materialize complete result sets in PHP or the browser.

See `DATA_NEEDED.md` for the data and content required to finish database-backed validation and release metadata.
