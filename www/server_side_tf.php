<?php

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$draw = 0;

try {
    require_once __DIR__ . '/includes/catalog.php';

    if (!isset($_GET['query'])) {
        throw new InvalidArgumentException('The TF result-set link is incomplete.');
    }

    $queryToken = pcrms_validate_query_token($_GET['query']);
    if (!isset(
        $_SESSION['pcrms_tf_queries'][$queryToken]['genome_id'],
        $_SESSION['pcrms_tf_queries'][$queryToken]['tf_id'],
        $_SESSION['pcrms_tf_queries'][$queryToken]['chr_id']
    )) {
        throw new InvalidArgumentException('The TF query has expired. Run the search again.');
    }

    $queryState = $_SESSION['pcrms_tf_queries'][$queryToken];
    $genomeId = pcrms_validate_genome($queryState['genome_id']);
    $tfId = pcrms_validate_identifier($queryState['tf_id'], 'transcription factor symbol');
    $chromosomes = pcrms_validate_chromosomes($genomeId, $queryState['chr_id']);
    session_write_close();
    require_once __DIR__ . '/connection.php';

    $drawInput = isset($_GET['draw']) ? filter_var($_GET['draw'], FILTER_VALIDATE_INT) : 0;
    $startInput = isset($_GET['start']) ? filter_var($_GET['start'], FILTER_VALIDATE_INT) : 0;
    $lengthInput = isset($_GET['length']) ? filter_var($_GET['length'], FILTER_VALIDATE_INT) : 50;
    $draw = $drawInput !== false ? max(0, (int) $drawInput) : 0;
    $start = $startInput !== false ? max(0, (int) $startInput) : 0;
    $allowedLengths = [25, 50, 100, 250];
    $length = in_array((int) $lengthInput, $allowedLengths, true) ? (int) $lengthInput : 50;

    $orderColumns = [
        0 => 'c.crmID',
        1 => 'c.Chromosome',
        2 => 't.TFBS_Start',
        3 => 't.TFBS_End',
        4 => 't.umID',
        5 => 'u.tfID',
        6 => 't.Binding_Score',
        7 => 't.umID',
    ];
    $orderRequest = isset($_GET['order']) && is_array($_GET['order']) ? $_GET['order'] : [];
    $orderIndex = isset($orderRequest[0]) && is_array($orderRequest[0]) && isset($orderRequest[0]['column'])
        ? filter_var($orderRequest[0]['column'], FILTER_VALIDATE_INT)
        : 0;
    $orderColumn = isset($orderColumns[$orderIndex]) ? $orderColumns[$orderIndex] : 'c.crmID';
    $orderDirection = isset($orderRequest[0]['dir'])
        && is_string($orderRequest[0]['dir'])
        && strtolower($orderRequest[0]['dir']) === 'desc'
        ? 'DESC'
        : 'ASC';

    $searchRequest = isset($_GET['search']) && is_array($_GET['search']) ? $_GET['search'] : [];
    $searchValue = isset($searchRequest['value']) && is_string($searchRequest['value'])
        ? pcrms_search_value($searchRequest['value'])
        : '';

    $chromosomePlaceholders = implode(', ', array_fill(0, count($chromosomes), '?'));
    $fromAndWhere = "FROM tfbss t
                     INNER JOIN umotif_tfs_association u ON t.umID = u.umID
                     INNER JOIN crms c ON c.crmID = t.crmID AND c.Genome = ?
                     WHERE u.Genome = ?
                     AND c.Chromosome IN ({$chromosomePlaceholders})
                     AND u.tfID = ?";
    $baseValues = array_merge([$genomeId, $genomeId], $chromosomes, [$tfId]);
    $baseTypes = 'ii' . str_repeat('s', count($chromosomes)) . 's';

    $totalStatement = pcrms_execute_statement(
        $mysqli,
        "SELECT COUNT(*) AS record_count {$fromAndWhere}",
        $baseTypes,
        $baseValues
    );
    $totalRow = $totalStatement->get_result()->fetch_assoc();
    $recordsTotal = (int) $totalRow['record_count'];

    $filteredFromAndWhere = $fromAndWhere;
    $filteredValues = $baseValues;
    $filteredTypes = $baseTypes;
    if ($searchValue !== '') {
        $filteredFromAndWhere .= " AND (c.crmID LIKE ? ESCAPE '!' OR c.Chromosome LIKE ? ESCAPE '!' OR t.umID LIKE ? ESCAPE '!' OR u.tfID LIKE ? ESCAPE '!')";
        $escapedSearch = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $searchValue);
        $searchPattern = '%' . $escapedSearch . '%';
        for ($index = 0; $index < 4; $index++) {
            $filteredValues[] = $searchPattern;
        }
        $filteredTypes .= 'ssss';
    }

    if ($searchValue !== '') {
        $filteredCountStatement = pcrms_execute_statement(
            $mysqli,
            "SELECT COUNT(*) AS record_count {$filteredFromAndWhere}",
            $filteredTypes,
            $filteredValues
        );
        $filteredCountRow = $filteredCountStatement->get_result()->fetch_assoc();
        $recordsFiltered = (int) $filteredCountRow['record_count'];
    } else {
        $recordsFiltered = $recordsTotal;
    }

    $rows = [];
    if ($start < $recordsFiltered) {
        $dataStatement = pcrms_execute_statement(
            $mysqli,
            "SELECT c.crmID, c.Chromosome, t.TFBS_Start, t.TFBS_End, t.umID,
                    t.Binding_Score, u.tfID, u.Genome
             {$filteredFromAndWhere}
             ORDER BY {$orderColumn} {$orderDirection}, c.crmID ASC, t.TFBS_Start ASC, t.TFBS_End ASC, t.umID ASC
             LIMIT ? OFFSET ?",
            $filteredTypes . 'ii',
            array_merge($filteredValues, [$length, $start])
        );
        $dataResult = $dataStatement->get_result();

        while ($row = $dataResult->fetch_assoc()) {
            $row['motifPath'] = pcrms_motif_path($row['Genome'], $row['umID']);
            $rows[] = $row;
        }
    }

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $rows,
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => $exception->getMessage(),
    ]);
} catch (Throwable $exception) {
    error_log('PCRMs TF table failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'The TF results table is temporarily unavailable.',
    ]);
}
