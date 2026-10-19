<?php

// JSON rows for the TF binding-site results table. Takes the result-set token plus the same
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
    $query = pcrms_load_tf_query($_GET['query']);
    session_write_close();
    $state = pcrms_results_state($PCRMS_RESULT_COLUMNS['tf-sites'], $PCRMS_RESULT_DEFAULTS['tf-sites']);

    require_once dirname(__DIR__) . '/includes/connection.php';
    echo json_encode(pcrms_fetch_tf($mysqli, $query, $state), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log('PCRMs TF table failed: ' . $exception->getMessage());
    http_response_code(503);
    echo json_encode(['error' => pcrms_is_timeout($exception)
        ? 'That took too long. Try a longer filter text, or narrow the search.'
        : 'The results table is temporarily unavailable.']);
}
