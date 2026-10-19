<?php

// JSON rows for the browse results table. Takes the result-set token plus the same
// page, size, sort, dir and search parameters as the results page.

require_once dirname(__DIR__) . '/includes/results.php';
pcrms_session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

try {
    if (!isset($_GET['query'])) {
        throw new InvalidArgumentException('The results link is incomplete.');
    }
    $query = pcrms_load_browse_query($_GET['query']);
    session_write_close();
    $state = pcrms_results_state($PCRMS_RESULT_COLUMNS['crms'], $PCRMS_RESULT_DEFAULTS['crms']);

    require_once dirname(__DIR__) . '/includes/connection.php';
    echo json_encode(pcrms_fetch_browse($mysqli, $query, $state), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log('PCRMs browse table failed: ' . $exception->getMessage());
    http_response_code(503);
    echo json_encode(['error' => pcrms_is_timeout($exception)
        ? 'That took too long. Try a longer filter text, or narrow the search.'
        : 'The results table is temporarily unavailable.']);
}
