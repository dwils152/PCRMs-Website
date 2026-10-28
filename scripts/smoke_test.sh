#!/usr/bin/env bash
# Opens every page and runs one search of each kind against a PCRMs site, and
# reports any that fail or show a PHP error.
#
# Usage: scripts/smoke_test.sh BASE_URL
#   for example https://cci-bioinfo.charlotte.edu or http://localhost:8099
#
# On the live site PHP errors only reach the error log, so this mainly catches
# failed pages. To catch every PHP error and deprecation notice, run it against
# PHP's built-in server with errors shown (see UPDATING.md):
#   PCRMS_DEBUG=1 php -d display_errors=1 -d html_errors=0 -d error_reporting=-1 \
#       -S localhost:8099 -t www

set -u
if [ $# -ne 1 ]; then
    echo "Usage: $0 BASE_URL" >&2
    exit 2
fi
base=${1%/}
jar=$(mktemp)
body=$(mktemp)
trap 'rm -f "$jar" "$body"' EXIT
failures=0
last_url=

# check NAME EXPECTED_STATUS [curl options...] PATH
check() {
    local name=$1 want=$2
    shift 2
    local args=("$@")
    local path=${args[${#args[@]}-1]}
    unset 'args[${#args[@]}-1]'
    local result status
    result=$(curl -s -L -b "$jar" -c "$jar" -o "$body" --max-time 120 \
        -w '%{http_code} %{url_effective}' ${args[@]+"${args[@]}"} "$base$path")
    status=${result%% *}
    last_url=${result#* }
    if [ "$status" != "$want" ]; then
        echo "FAIL  $name: status $status, expected $want"
        failures=$((failures + 1))
    elif grep -q -E '(Fatal error|Warning|Deprecated|Notice|Parse error): .* on line [0-9]+' "$body"; then
        echo "FAIL  $name: PHP error in the page:"
        grep -o -E '(Fatal error|Warning|Deprecated|Notice|Parse error): .* on line [0-9]+' "$body" | head -3 | sed 's/^/      /'
        failures=$((failures + 1))
    else
        echo "ok    $name"
    fi
}

# check_json NAME PATH: like check, and the reply must be a JSON object.
check_json() {
    check "$1" 200 "$2"
    if [ "$(head -c 1 "$body")" != "{" ]; then
        echo "FAIL  $1: not JSON: $(head -c 80 "$body")"
        failures=$((failures + 1))
    fi
}

token() {
    printf '%s' "${last_url##*query=}"
}

echo "Pages"
check "home" 200 /
check "browse form" 200 /browse_dataset.php
check "closest-gene form" 200 /detailed_search.php
check "range form" 200 '/detailed_search.php?tool=range'
check "TF form" 200 '/detailed_search.php?tool=tf'
check "downloads" 200 /download_data.php
check "tools" 200 /tools.php
check "not-found page" 404 /404.php
case $base in
    # PHP's test server ignores .htaccess and shows the home page instead.
    *://localhost*|*://127.0.0.1*) ;;
    *) check "missing address" 404 /no-such-page.php ;;
esac
check "human CRM record" 200 '/crm.php?crmId=1&genome_id=1'
check_json "human CRM sites (JSON)" '/crm.php?crmId=1&genome_id=1&format=json'
check "human CRM sites (CSV)" 200 '/crm.php?crmId=1&genome_id=1&format=csv'
check "mouse CRM record" 200 '/crm.php?crmId=1500000&genome_id=2'

echo "Browse"
check "human chr21, p < 1e-4" 200 -d genome_id=1 -d 'chr_id[1][]=chr21' -d p_value=0.0001 /get_crms.php
t=$(token)
check_json "  table data" "/server_side_process.php?query=$t&page=2&size=25&sort=score&dir=desc"
check "  CSV export" 200 "/export.php?type=browse&query=$t"
check "mouse chr19, p < 0.05" 200 -d genome_id=2 -d 'chr_id[2][]=chr19' -d p_value=0.05 /get_crms.php

echo "Closest gene"
check "human TP53" 200 -d genome_id=1 -d gene_id=TP53 -d p_value=0.05 /get_genes_closest_crm.php
check "  CSV export" 200 "/get_genes_closest_crm.php?query=$(token)&format=csv"
check "mouse Trp53" 200 -d genome_id=2 -d gene_id=Trp53 -d p_value=0.05 /get_genes_closest_crm.php

echo "Gene range"
check "human TP53, both sides, 0.5 Mb" 200 -d genome_id=1 -d gene_id=TP53 -d p_value=0.05 \
    -d location=both -d distance=50 /get_crm_in_genes_range.php
check "  CSV export" 200 "/get_crm_in_genes_range.php?query=$(token)&format=csv"

echo "TF binding sites"
check "human CTCF on chr21" 200 -d genome_id=1 -d tf_id=CTCF -d 'chr_id[1][]=chr21' /get_tf.php
t=$(token)
check_json "  table data" "/server_side_tf.php?query=$t&page=2&size=25"
check "  CSV export" 200 "/export.php?type=tf&query=$t"

echo "Bad input is refused"
check "unknown genome" 400 -d genome_id=99 -d 'chr_id[99][]=chr1' -d p_value=0.05 /get_crms.php

echo
if [ "$failures" -eq 0 ]; then
    echo "All checks passed."
else
    echo "$failures check(s) failed."
    exit 1
fi
