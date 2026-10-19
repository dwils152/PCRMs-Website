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

## Synthetic test database

A self-contained development schema and deterministic fixture are available at
[`database/synthetic_fixture.sql`](database/synthetic_fixture.sql). It creates
only the dedicated `pcrms_synthetic` database and includes enough records to
exercise pagination, every search workflow, detail pages, both gene strands,
all P-value thresholds, and present/missing motif-logo states.

See [`database/README.md`](database/README.md) for loading instructions and a
test-case matrix.

## Run locally

After configuring the database:

```bash
php -S 127.0.0.1:8000 -t www
```

Open `http://127.0.0.1:8000/`. The homepage and static analysis/download entry pages can render without the database, but browsing and query results require it.

## Structure

- `www/index.php` — homepage
- `www/browse_dataset.php` — database browser
- `www/detailed_search.php` — analysis hub
- `www/DS_*.php` — focused query forms
- `www/get_*.php` — database-backed result pages
- `www/crm.php`, `www/tfbs.php` — record details
- `www/download_data.php` — release manifest and downloads
- `www/partials/` — shared header and footer
- `www/includes/catalog.php` — validated organism/query metadata
- `www/includes/releases.php` — canonical release facts and file manifest
- `www/css/modern.css` — responsive visual system

## Security note

Earlier source files contained plaintext database credentials. Those values have been removed, but both exposed database accounts must be rotated in MySQL before this site is redeployed. Query handlers now validate request values and use prepared statements. The broad CRM and TF result tables use database-backed pagination so they do not materialize complete result sets in PHP or the browser.

See `DATA_NEEDED.md` for the data and content required to finish database-backed validation and release metadata.
