<?php
session_start();
require_once __DIR__ . '/includes/forms.php';
require_once __DIR__ . '/includes/results.php';

try {
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['genome_id'], $_POST['tf_id'], $_POST['chr_id'])) {
            throw new InvalidArgumentException('Choose a genome, enter a TF symbol and pick at least one chromosome.');
        }
        $genomeId = pcrms_validate_genome($_POST['genome_id']);
        $token = pcrms_store_query_state('pcrms_tf_queries', [
            'genome_id' => $genomeId,
            'tf_id' => pcrms_validate_identifier($_POST['tf_id'], 'transcription factor symbol'),
            'chr_id' => pcrms_selected_chromosomes($genomeId, $_POST['chr_id']),
        ]);
        header('Location: get_tf.php?query=' . rawurlencode($token), true, 303);
        exit;
    }
    if (!isset($_GET['query'])) {
        throw new InvalidArgumentException('Start a search from the functional analysis page.');
    }
    $query = pcrms_load_tf_query($_GET['query']);
    $state = pcrms_results_state($PCRMS_RESULT_COLUMNS['tf-sites'], $PCRMS_RESULT_DEFAULTS['tf-sites']);
} catch (InvalidArgumentException $exception) {
    pcrms_invalid_request($exception->getMessage());
}
session_write_close();

try {
    require_once __DIR__ . '/connection.php';
    $result = pcrms_fetch_tf($mysqli, $query, $state);
} catch (Throwable $exception) {
    pcrms_service_unavailable($exception);
}

$genomeId = $query['genome_id'];
$chromosomes = pcrms_chromosome_label($genomeId, $query['chromosomes']);
$editUrl = 'detailed_search.php?' . http_build_query([
    'tool' => 'tf',
    'genome_id' => $genomeId,
    'tf_id' => $query['tf_id'],
    'chr' => implode(',', $query['chromosomes']),
], '', '&', PHP_QUERY_RFC3986);
$headline = number_format($result['total']) . ($result['total'] === 1 ? ' binding site found' : ' binding sites found');

pcrms_page_start([
    'title' => $query['tf_id'] . ' binding sites, ' . $PCRMS_GENOMES[$genomeId]['short'],
    'active' => 'analysis',
]);
?>
<main id="main-content">
    <?php pcrms_results_band(['Functional analysis', 'detailed_search.php?tool=tf'], $headline, [
        pcrms_genome_tag($genomeId),
        'TF ' . pcrms_h($query['tf_id']),
        pcrms_h($chromosomes),
    ], $editUrl); ?>

    <div class="page-main shell">
        <?php pcrms_results_card([
            'columns' => $PCRMS_RESULT_COLUMNS['tf-sites'],
            'defaults' => $PCRMS_RESULT_DEFAULTS['tf-sites'],
            'state' => $state,
            'result' => $result,
            'url' => 'get_tf.php?query=' . $query['token'],
            'endpoint' => 'server_side_tf.php?query=' . $query['token'],
            'csv' => 'export.php?type=tf&query=' . $query['token'],
            'limit' => PCRMS_EXPORT_LIMIT,
            'caption' => 'Predicted binding sites of ' . $query['tf_id'] . '. Binding sites open the UCSC Genome Browser in a new tab.',
            'filter_label' => 'Filter by CRM ID or motif',
            'noun' => ['binding site', 'binding sites'],
            'empty' => ['No binding sites found', 'No predicted binding sites of ' . $query['tf_id'] . ' on ' . $chromosomes . '. Check the TF symbol, or add more chromosomes.'],
        ]); ?>
    </div>
</main>
<?php pcrms_page_end(['js/results.js']); ?>
