<?php
require_once dirname(__DIR__) . '/includes/forms.php';
require_once dirname(__DIR__) . '/includes/results.php';
pcrms_session_start();

try {
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['genome_id'], $_POST['chr_id'], $_POST['p_value'])) {
            throw new InvalidArgumentException('Choose a genome, at least one chromosome and a p-value cutoff.');
        }
        $genomeId = pcrms_validate_genome($_POST['genome_id']);
        $token = pcrms_store_query_state('pcrms_browse_queries', [
            'genome_id' => $genomeId,
            'chr_id' => pcrms_selected_chromosomes($genomeId, $_POST['chr_id']),
            'p_value' => pcrms_validate_p_value($_POST['p_value']),
        ]);
        header('Location: get_crms.php?query=' . rawurlencode($token), true, 303);
        exit;
    }
    if (!isset($_GET['query'])) {
        throw new InvalidArgumentException('Start a search from the browse page.');
    }
    $query = pcrms_load_browse_query($_GET['query']);
    $state = pcrms_results_state($PCRMS_RESULT_COLUMNS['crms'], $PCRMS_RESULT_DEFAULTS['crms']);
} catch (InvalidArgumentException $exception) {
    pcrms_invalid_request($exception->getMessage());
}
session_write_close();

try {
    require_once dirname(__DIR__) . '/includes/connection.php';
    $result = pcrms_fetch_browse($mysqli, $query, $state);
} catch (Throwable $exception) {
    pcrms_service_unavailable($exception);
}

$genomeId = $query['genome_id'];
$editUrl = 'browse_dataset.php?' . http_build_query([
    'genome_id' => $genomeId,
    'chr' => implode(',', $query['chromosomes']),
    'p_value' => $query['p_value'],
], '', '&', PHP_QUERY_RFC3986);
$headline = number_format($result['total']) . ($result['total'] === 1 ? ' CRM found' : ' CRMs found');

pcrms_page_start([
    'title' => 'CRMs on ' . pcrms_chromosome_label($genomeId, $query['chromosomes']) . ', ' . $PCRMS_GENOMES[$genomeId]['short'],
    'active' => 'browse',
]);
?>
<main id="main-content">
    <?php pcrms_results_band(['Browse', 'browse_dataset.php'], $headline, [
        pcrms_genome_tag($genomeId),
        pcrms_h(pcrms_chromosome_label($genomeId, $query['chromosomes'])),
        'p ≤ ' . pcrms_h($PCRMS_P_VALUE_LABELS[$query['p_value']]),
    ], $editUrl); ?>

    <div class="page-main shell">
        <?php pcrms_results_card([
            'columns' => $PCRMS_RESULT_COLUMNS['crms'],
            'defaults' => $PCRMS_RESULT_DEFAULTS['crms'],
            'state' => $state,
            'result' => $result,
            'url' => 'get_crms.php?query=' . $query['token'],
            'endpoint' => 'server_side_process.php?query=' . $query['token'],
            'csv' => 'export.php?type=browse&query=' . $query['token'],
            'limit' => PCRMS_EXPORT_LIMIT,
            'caption' => 'Predicted CRMs matching the search. CRM IDs open the UCSC Genome Browser in a new tab.',
            'filter_label' => 'Filter by CRM ID or gene',
            'noun' => ['CRM', 'CRMs'],
            'empty' => ['No CRMs found', 'No predicted CRMs pass this p-value cutoff on the chosen chromosomes. Try a looser cutoff such as 0.05, or add more chromosomes.'],
        ]); ?>
    </div>
</main>
<?php pcrms_page_end(['js/results.js']); ?>
