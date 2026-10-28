# Updating the live site

How to change the site after it is online. [`DEPLOYMENT.md`](DEPLOYMENT.md)
covers the first setup; [`database/README.md`](database/README.md) covers
building the data files.

## The server

The commands below use placeholders for the server's details. The real
values are kept in a private note outside this repository, not in git.

| Placeholder | Meaning |
|---|---|
| `SERVER` | the ssh host name |
| `SITE_DIR` | the folder on the server holding `www/`, `includes/` and `config/` beside each other |
| `DATA_DIR` | the folder holding the download files; `www/data` is a link to it |
| `LOAD_DIR` | a staging folder for database load files |
| `LIVE_DB` | the database the site reads now (the account is read-only; see [`database/grants.sql`](database/grants.sql)) |
| `NEW_DB` | the next database, while it is being loaded |
| `APACHE_LOG` | the Apache error log |
| `SSL_CONF` | the Apache HTTPS virtual-host file |

The database password lives only in `SITE_DIR/config/database.php` on the
server (see [`DEPLOYMENT.md`](DEPLOYMENT.md)).

## Rules for every update

- **Changes go live as soon as they are copied.** Always do the dry run
  (`-n`) first and read the list of files it would change or delete.
- **On the Mac, use `/opt/homebrew/bin/rsync`.** Plain `rsync` in your shell
  is Apple's openrsync, which lacks some of the options used here.
- **Run the upload commands from the repository root.**
- **Never upload `config/`.** The server's copy holds the database password,
  and the Mac's copy points at the local test database. Never upload
  `database/`, `docs/`, `data-incoming/` or `.mysql-test/` either.
- **Keep `-K` on the `www/` upload.** It keeps the server's `www/data` link.
  Without it, rsync would replace the link with an empty folder and every
  download would break.
- **Match the server's PHP version.** Check it with `php -v` on the server
  and run the same major version locally, so a feature that works on the Mac
  cannot break the live site. The syntax check in step 5 below catches most
  version problems.
- **Files made by hand on the server may be private.** If Apache can't read
  a file you created there, run `chmod 644` on the file (or `chmod 755` on a
  folder). Files uploaded with `rsync -a` keep the Mac's permissions and are
  fine.
- **Long commands break when copied from a narrow terminal.** Check that each
  pasted command arrived on one line, or as lines ending in `\`.

## 1. Changing pages, styles or scripts

For anything in `www/` or `includes/`.

1. Make the change and check it locally (see "Run locally" in
   [`README.md`](README.md)).
2. Commit it, so there is a version to go back to:
   ```bash
   git add -A && git commit -m "Describe the change"
   ```
3. Dry run. This lists what would change and changes nothing:
   ```bash
   /opt/homebrew/bin/rsync -avnK --delete \
       --exclude 'data/*.gz' --exclude '.well-known/' \
       www/ SERVER:SITE_DIR/www/
   /opt/homebrew/bin/rsync -avn --delete \
       includes/ SERVER:SITE_DIR/includes/
   ```
   Lines starting with `deleting` are files the upload would remove from the
   server. Make sure each one is meant to go.
4. Upload. These are the same commands without the `n`:
   ```bash
   /opt/homebrew/bin/rsync -avK --delete \
       --exclude 'data/*.gz' --exclude '.well-known/' \
       www/ SERVER:SITE_DIR/www/
   /opt/homebrew/bin/rsync -av --delete \
       includes/ SERVER:SITE_DIR/includes/
   ```
   The excludes leave the server's download files and certificate checks
   alone.
5. Check on the server:
   ```bash
   cd SITE_DIR
   find . -name '*.php' -exec php -l {} \; | grep -v "^No syntax"
   curl -sI https://SITE_ADDRESS/ | head -1
   sudo tail -5 APACHE_LOG
   ```
   The syntax check should print nothing, the page should give `200`, and the
   log should show no new errors. Then open the pages you changed in a
   browser. Browsers keep old styles and scripts for a while, so use a hard
   refresh (Cmd-Shift-R). [`scripts/smoke_test.sh`](scripts/smoke_test.sh)
   opens every page and runs one search of each kind.

**To undo**, reverse the last commit on the Mac and upload again:

```bash
git revert --no-edit HEAD
```

Then run the upload commands from step 4. `git revert` adds a commit that
undoes the previous one, so files the change added or removed are also put
back the way they were.

## 2. Showing the under-construction page

`www/under-construction.html` can stand in for every page, for example during
a database switch.

1. Apache needs `mod_rewrite` for this. Check once:
   ```bash
   sudo apache2ctl -M | grep rewrite
   ```
   If that prints nothing, run `sudo a2enmod rewrite`, then
   `sudo systemctl restart apache2`.
2. On the Mac, remove the `#` from the four lines under "Under construction"
   at the end of `www/.htaccess`, then run the upload from section 1. Every
   address now shows the page with status 503, which tells search engines to
   come back later.
3. To reopen the site, put the four `#` back and upload again.

## 3. Replacing download files

1. Make the files (see `make_downloads.py` in
   [`database/README.md`](database/README.md)) and put them in `www/data/`
   on the Mac.
2. Upload them. `-P` shows progress and resumes if the connection drops:
   ```bash
   /opt/homebrew/bin/rsync -aP www/data/*.bed.gz SERVER:DATA_DIR/
   ```
   rsync writes each file under a temporary name and renames it when it is
   complete, so nobody downloads half a file.
3. Check the files on the server:
   ```bash
   cd DATA_DIR && gzip -t *.bed.gz && echo intact
   ```
4. If the file names or sizes changed, update `includes/releases.php`
   (`name`, `label`, `expected`) and upload it as in section 1.
5. Delete files the download page no longer lists, on the server and in
   `www/data/` on the Mac.

## 4. Loading new data into the database

For a new CRM release, new genomes or the Nucleotide Transformer maps. Load
the new data into a **new** database beside the live one, check it, and then
point the site at it. The site keeps working throughout, and going back means
changing one word.

Each database copy takes about 16 GB; check the free space first.

1. **Build the load files** on the Mac, as in
   [`database/README.md`](database/README.md). Put them in one folder with
   `schema.sql`, `load.sql` and `grants.sql` from `database/`. Then, in that
   folder, write a checksum list:
   ```bash
   shasum -a 256 *.sql *.tsv.gz > SHA256SUMS
   ```
2. **Upload and check them**, still from that folder:
   ```bash
   /opt/homebrew/bin/rsync -aP ./ SERVER:LOAD_DIR/
   ```
   On the server:
   ```bash
   cd LOAD_DIR && sha256sum -c SHA256SUMS && gunzip *.tsv.gz
   ```
3. **Load**, inside `tmux` so a dropped connection doesn't stop it. This takes
   30–90 minutes:
   ```bash
   sudo mysql -e "SET GLOBAL innodb_buffer_pool_size = 4294967296"
   sudo mysql -e "CREATE DATABASE NEW_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_bin"
   sudo mysql -e "SET GLOBAL local_infile = 1"
   sudo mysql NEW_DB < schema.sql
   sudo mysql --local-infile=1 --show-warnings --table \
       --init-command="SET sql_log_bin = 0" NEW_DB < load.sql | tee load.log
   sudo mysql -e "SET GLOBAL local_infile = 0"
   ```
   Keep `SET sql_log_bin = 0`. Without it, MySQL first writes the
   binding-site load to its temporary folder as one file of several GB, which
   fails with "No space left on device" on a small `/tmp`.
4. **Read `load.log`.** There must be no warnings, every `problem` count must
   be 0, and the CRM, motif, gene and binding-site counts must match what the
   split script printed.
5. **Give the website's account access** to the new database:
   ```bash
   sed 's/PCRMSV7/NEW_DB/g' grants.sql | sudo mysql --table
   ```
   It should list seven `GRANT SELECT … ON NEW_DB…` lines.
6. **Switch the site.** This takes effect within a few seconds, with no
   Apache reload:
   ```bash
   cd SITE_DIR/config
   sudo sed -i 's/LIVE_DB/NEW_DB/' database.php
   sudo grep -c NEW_DB database.php
   ```
   The last line should print `1`. Then open the site and run a search of
   each kind. **To go back**, run the same `sed` with the two names swapped.
7. **Tidy up** after a week or so without problems:
   ```bash
   sudo mysql -e "DROP DATABASE LIVE_DB"
   rm -rf LOAD_DIR
   ```
   Update the database name in your private server note.

To replace only the gene list or the TF names in the live database, use
`load_genes.sql` or `load_motif_tfs.sql` as described in
[`database/README.md`](database/README.md).

## 5. When something goes wrong

Start with the error log, which names the file and line:

```bash
sudo tail -20 APACHE_LOG
```

| Symptom | Likely cause |
|---|---|
| Every page gives 500, and `curl -sI` shows `charset=iso-8859-1` | Apache rejected the config or `.htaccess`; the log names the line. Undo the last change. |
| One page gives 500 or is blank, with `charset=UTF-8` | A PHP error; the log line names the file. |
| `Permission denied` in the log | A file or folder made by hand on the server is private: `chmod 644` (file) or `chmod 755` (folder). |
| Results pages say the database is temporarily unavailable | MySQL is down, or the name or password in `config/database.php` is wrong. |
| Downloads give 403 or 404 | `www/data` is no longer a link to `DATA_DIR`, usually after an upload without `-K`. See below. |

To repair the download link, look at it first:

```bash
ls -ld SITE_DIR/www/data
```

It should end in `data -> DATA_DIR`. If it shows a plain folder instead,
replace the folder with the link (`rmdir` only removes an empty folder, so
nothing else can be lost):

```bash
rmdir SITE_DIR/www/data
ln -s DATA_DIR SITE_DIR/www/data
```

Before editing the Apache config, back it up:

```bash
sudo cp SSL_CONF SSL_CONF.bak
```

To undo the edit, copy the backup back and reload:

```bash
sudo cp SSL_CONF.bak SSL_CONF
sudo apache2ctl configtest && sudo systemctl reload apache2
```
