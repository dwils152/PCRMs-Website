<?php

// Streams the rows of a browse or TF search as CSV.
// export.php?type=browse|tf&query=<token>[&search=<filter text>]

require_once dirname(__DIR__) . '/includes/queries.php';
pcrms_session_start();

try {
    $type = isset($_GET['type']) && in_array($_GET['type'], ['browse', 'tf'], true) ? $_GET['type'] : null;
    if ($type === null || !isset($_GET['query'])) {
        throw new InvalidArgumentException('That download link is incomplete.');
    }
    $query = $type === 'browse' ? pcrms_load_browse_query($_GET['query']) : pcrms_load_tf_query($_GET['query']);
    $search = isset($_GET['search']) && is_string($_GET['search']) ? pcrms_search_value($_GET['search']) : '';
} catch (InvalidArgumentException $exception) {
    pcrms_invalid_request($exception->getMessage());
}
session_write_close();

try {
    require_once dirname(__DIR__) . '/includes/connection.php';
    // Streaming a large file takes longer than a page of results.
    pcrms_statement_timeout($mysqli, 120);

    if ($type === 'browse') {
        list($where, $types, $values) = pcrms_browse_where($query, $search);
        $countSql = "SELECT COUNT(*) FROM crms_genes WHERE {$where}";
        $dataSql = "SELECT crmID, Chromosome, Start_Pos, End_Pos, Genes_Symbol, Score, P_value
                    FROM crms_genes WHERE {$where} ORDER BY " . pcrms_browse_order(1, 'ASC');
        $header = ['crm_id', 'chromosome', 'start', 'end', 'closest_genes', 'score', 'p_value'];
        $textColumns = [0, 1, 4];
    } else {
        list($from, $types, $values) = pcrms_tf_from($query, $search);
        $countSql = "SELECT COUNT(*) {$from}";
        $dataSql = "SELECT c.crmID, c.Chromosome, t.TFBS_Start, t.TFBS_End, t.umID, u.tfID, t.Binding_Score
                    {$from} ORDER BY " . pcrms_tf_order(1, 'ASC');
        $header = ['crm_id', 'chromosome', 'tfbs_start', 'tfbs_end', 'motif_id', 'tf', 'binding_score'];
        $textColumns = [0, 1, 4, 5];
    }

    $count = (int) pcrms_execute_statement($mysqli, $countSql, $types, $values)->get_result()->fetch_row()[0];
    if ($count > PCRMS_EXPORT_LIMIT) {
        pcrms_message_page(413, 'Download too large', 'That result set is too large to download here',
            'This search matches ' . number_format($count) . ' rows. Narrow it to fewer chromosomes or a stricter cutoff, or download the genome-wide files instead.', [
                ['Go to downloads', 'download_data.php'],
            ]);
    }

    $statement = pcrms_execute_statement($mysqli, $dataSql, $types, $values);
    $row = array_fill(0, count($header), null);
    $refs = [];
    foreach ($row as $index => $unused) {
        $refs[$index] = &$row[$index];
    }
    call_user_func_array([$statement, 'bind_result'], $refs);
} catch (Throwable $exception) {
    pcrms_service_unavailable($exception);
}

$genome = pcrms_genome_key($query['genome_id']);
$label = $type === 'browse' ? 'crms' : 'tfbs_' . preg_replace('/[^A-Za-z0-9_.-]/', '', $query['tf_id']);
$filename = 'pcrms_' . $genome . '_' . $label . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

echo implode(',', $header) . "\n";
while ($statement->fetch()) {
    $cells = [];
    foreach ($row as $index => $value) {
        $cells[] = pcrms_csv_cell($value, in_array($index, $textColumns, true));
    }
    echo implode(',', $cells) . "\n";
}
$statement->close();
