<?php

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$draw = 0;

try {
    require_once __DIR__ . '/includes/catalog.php';

    if (!isset($_GET['query'])) {
        throw new InvalidArgumentException('The browse result-set link is incomplete.');
    }

    $queryToken = pcrms_validate_query_token($_GET['query']);
    if (!isset(
        $_SESSION['pcrms_browse_queries'][$queryToken]['genome_id'],
        $_SESSION['pcrms_browse_queries'][$queryToken]['chr_id'],
        $_SESSION['pcrms_browse_queries'][$queryToken]['p_value']
    )) {
        throw new InvalidArgumentException('The browse query has expired. Run the search again.');
    }

    $queryState = $_SESSION['pcrms_browse_queries'][$queryToken];
    $genomeId = pcrms_validate_genome($queryState['genome_id']);
    $chromosomes = pcrms_validate_chromosomes($genomeId, $queryState['chr_id']);
    $pValue = pcrms_validate_p_value($queryState['p_value']);
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
        0 => 'crmID',
        1 => 'Chromosome',
        2 => 'Start_Pos',
        3 => 'End_Pos',
        4 => 'Genes_Symbol',
        5 => 'Score',
        6 => 'P_value',
    ];
    $orderRequest = isset($_GET['order']) && is_array($_GET['order']) ? $_GET['order'] : [];
    $orderIndex = isset($orderRequest[0]) && is_array($orderRequest[0]) && isset($orderRequest[0]['column'])
        ? filter_var($orderRequest[0]['column'], FILTER_VALIDATE_INT)
        : 0;
    $orderColumn = isset($orderColumns[$orderIndex]) ? $orderColumns[$orderIndex] : 'crmID';
    $orderDirection = isset($orderRequest[0]['dir'])
        && is_string($orderRequest[0]['dir'])
        && strtolower($orderRequest[0]['dir']) === 'desc'
        ? 'DESC'
        : 'ASC';

    $searchValue = '';
    $searchRequest = isset($_GET['search']) && is_array($_GET['search']) ? $_GET['search'] : [];
    if (isset($searchRequest['value']) && is_string($searchRequest['value'])) {
        $searchValue = pcrms_search_value($searchRequest['value']);
    }

    $chromosomePlaceholders = implode(', ', array_fill(0, count($chromosomes), '?'));
    $baseWhere = "Genome = ? AND Chromosome IN ({$chromosomePlaceholders}) AND P_value <= ?";
    $baseValues = array_merge([$genomeId], $chromosomes, [(float) $pValue]);
    $baseTypes = 'i' . str_repeat('s', count($chromosomes)) . 'd';

    $totalStatement = pcrms_execute_statement(
        $mysqli,
        "SELECT COUNT(*) AS record_count FROM crms_genes WHERE {$baseWhere}",
        $baseTypes,
        $baseValues
    );
    $totalRow = $totalStatement->get_result()->fetch_assoc();
    $recordsTotal = (int) $totalRow['record_count'];

    $filteredWhere = $baseWhere;
    $filteredValues = $baseValues;
    $filteredTypes = $baseTypes;

    if ($searchValue !== '') {
        $filteredWhere .= " AND (crmID LIKE ? ESCAPE '!' OR Chromosome LIKE ? ESCAPE '!' OR Genes_Symbol LIKE ? ESCAPE '!')";
        $escapedSearch = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $searchValue);
        $searchPattern = '%' . $escapedSearch . '%';
        $filteredValues[] = $searchPattern;
        $filteredValues[] = $searchPattern;
        $filteredValues[] = $searchPattern;
        $filteredTypes .= 'sss';
    }

    if ($searchValue !== '') {
        $filteredCountStatement = pcrms_execute_statement(
            $mysqli,
            "SELECT COUNT(*) AS record_count FROM crms_genes WHERE {$filteredWhere}",
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
        $dataValues = array_merge($filteredValues, [$length, $start]);
        $dataTypes = $filteredTypes . 'ii';
        $dataStatement = pcrms_execute_statement(
            $mysqli,
            "SELECT crmID, Chromosome, Start_Pos, End_Pos, Genes_Symbol, Score, P_value
             FROM crms_genes
             WHERE {$filteredWhere}
             ORDER BY {$orderColumn} {$orderDirection}, crmID ASC, Chromosome ASC, Start_Pos ASC, End_Pos ASC
             LIMIT ? OFFSET ?",
            $dataTypes,
            $dataValues
        );

        $dataResult = $dataStatement->get_result();
        while ($row = $dataResult->fetch_assoc()) {
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
    error_log('PCRMs server-side table failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'The results table is temporarily unavailable.',
    ]);
}
