# Putting PCRMs online

A checklist for whoever deploys the site. The code already sends security
headers (a strict Content-Security-Policy, `X-Frame-Options`, `nosniff`,
`Referrer-Policy`), hides PHP errors from visitors, hardens the session
cookie, limits each database query to 15 seconds and keeps every included
PHP file outside the web root.

## 1. Files

- The web server's document root must be `www/`.
- Deploy these folders side by side: `www/`, `includes/` and `config/`.
- Never deploy `.git/`, `database/`, `docs/`, `data-incoming/` or
  `.mysql-test/`.
- Sync with deletion (for example `rsync -a --delete`) so files removed in the
  redesign also disappear from the server. Before the first deploy, check the
  live site for leftovers of the old version: `*_bak.php`, `exclusive-templates/`,
  `www/tracker/` (including the GeoLite databases), old copies of
  `connection.php` that contain passwords, and the Bootstrap/MDB folders.
- Copy the download files (`*.bed.gz`, about 6.4 GB) into `www/data/`; they
  are not in git. Remove the old `*.tar.gz` archives from the server.

## 2. Database

- Rotate the passwords of both database accounts that appeared in earlier
  versions of the source code, even if they are no longer used.
- Create a separate account for the website and give it only the grants in
  [`database/grants.sql`](database/grants.sql) (read-only on six tables and one
  view). The view must stay `SQL SECURITY DEFINER` with an administrator as
  definer.
- Store the credentials in `config/database.php` (copied from
  `config/database.example.php`, readable only by the web server user, for
  example mode 640) or in the `PCRMS_DB_*` environment variables.
- Create the tables with [`database/schema.sql`](database/schema.sql) and load
  the data as described in [`database/README.md`](database/README.md). The
  binding-site table holds about 290 million rows for human and mouse: the
  database takes about 16 GB, plus 11 GB for the split files during the load,
  which took about 30 minutes on a laptop.
- Give MySQL enough memory for the indexes the searches use, ideally
  `innodb_buffer_pool_size = 8G` or more (the binding-site index alone is about
  5 GB). With less, searches that read millions of binding sites slow down.
  For the one-time load, a larger `innodb_redo_log_capacity` (for example 8G)
  also helps; set it back afterwards if needed.

## 3. PHP (php.ini)

```
display_errors = Off
log_errors = On
expose_php = Off
max_execution_time = 30
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = Lax
session.use_strict_mode = 1
session.gc_maxlifetime = 21600
```

The code sets the error-display and session values itself, but setting them
server-wide also covers errors that happen before the code runs.

## 4. Web server

- Serve the site over HTTPS only, redirect HTTP to HTTPS, and once that works
  add `Strict-Transport-Security: max-age=31536000`.
- Apache: allow `www/.htaccess` (`AllowOverride FileInfo Options AuthConfig`), or
  copy its rules (404 page, no directory listings, no dotfiles) into the server
  configuration. If the site lives in a subfolder rather than at the domain
  root, change `ErrorDocument 404 /404.php` to include the folder.
- Rate-limit the search and download endpoints (`get_*.php`,
  `server_side_*.php`, `export.php`), for example with `mod_evasive`,
  `mod_qos` or the campus proxy, so one client cannot run expensive searches
  in a loop.
- The site sends `frame-ancestors 'none'`, so other sites cannot embed it in a
  frame. If the lab site needs to embed it, allow that origin in
  `includes/bootstrap.php`.

## 5. After deploying

- Open each page and run one search of each kind.
- Check the response headers include `Content-Security-Policy` and that
  `https://<site>/includes/catalog.php`, `/.htaccess` and `/data/` return
  404 or 403.
- Check the PHP error log stays empty.
