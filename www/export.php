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
        list($from, $types, $values) = pcrms_browse_from($query, $search);
        $countSql = "SELECT COUNT(*) FROM {$from}";
        $dataSql = "SELECT c.crmID, c.Chromosome, c.Start_Pos, c.End_Pos, " . sprintf(PCRMS_GENES_SQL, 'c.crmID') . ", c.Score, c.P_value
                    FROM {$from} ORDER BY " . pcrms_browse_order('location', 'ASC');
        $header = ['crm_id', 'chromosome', 'start', 'end', 'closest_genes', 'score', 'p_value'];
        $textColumns = [0, 1, 4];
    } else {
        $ranges = pcrms_crm_ranges($mysqli, $query);
        list($motifs, $query['tf_id']) = pcrms_tf_motifs($mysqli, $query);
        list($where, $types, $values) = pcrms_tf_where($query, $motifs, $ranges, $search);
        $countSql = "SELECT COUNT(*) FROM tfbss t WHERE {$where}";
        $dataSql = "SELECT t.crmID, t.TFBS_Start, t.TFBS_End, t.motifID, t.Binding_Score
                    FROM tfbss t WHERE {$where} ORDER BY t.crmID, t.TFBS_Start, t.motifID";
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
    $row = array_fill(0, $type === 'browse' ? count($header) : 5, null);
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
    $line = $row;
    if ($type === 'tf') {
        // Binding-site rows carry only IDs; add the chromosome, motif number and TF.
        $site = pcrms_tf_site(['crmID' => $row[0], 'TFBS_Start' => $row[1], 'TFBS_End' => $row[2],
            'motifID' => $row[3], 'Binding_Score' => $row[4]], $query, $motifs, $ranges);
        $line = [$site['crmID'], $site['Chromosome'], $site['TFBS_Start'], $site['TFBS_End'],
            $site['umID'], $site['tfID'], $site['Binding_Score']];
    }
    $cells = [];
    foreach ($line as $index => $value) {
        $cells[] = pcrms_csv_cell($value, in_array($index, $textColumns, true));
    }
    echo implode(',', $cells) . "\n";
}
$statement->close();
