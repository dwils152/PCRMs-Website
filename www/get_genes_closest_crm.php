<?php
session_start();
require_once __DIR__ . '/includes/results.php';

try {
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['genome_id'], $_POST['gene_id'], $_POST['p_value'])) {
            throw new InvalidArgumentException('Choose a genome, enter a gene and pick a p-value cutoff.');
        }
        $token = pcrms_store_query_state('pcrms_closest_queries', [
            'genome_id' => pcrms_validate_genome($_POST['genome_id']),
            'gene_id' => pcrms_validate_identifier($_POST['gene_id'], 'gene symbol or ID'),
            'p_value' => pcrms_validate_p_value($_POST['p_value']),
        ]);
        header('Location: get_genes_closest_crm.php?query=' . rawurlencode($token), true, 303);
        exit;
    }
    $token = pcrms_validate_query_token(isset($_GET['query']) ? $_GET['query'] : '');
    if (!isset($_SESSION['pcrms_closest_queries'][$token]['genome_id'], $_SESSION['pcrms_closest_queries'][$token]['gene_id'],
        $_SESSION['pcrms_closest_queries'][$token]['p_value'])) {
        throw new InvalidArgumentException('That search has expired. Run it again from the functional analysis page.');
    }
    $saved = $_SESSION['pcrms_closest_queries'][$token];
    $genomeId = pcrms_validate_genome($saved['genome_id']);
    $geneId = pcrms_validate_identifier($saved['gene_id'], 'gene symbol or ID');
    $pValue = pcrms_validate_p_value($saved['p_value']);
    $state = pcrms_results_state($PCRMS_RESULT_COLUMNS['gene-crms'], $PCRMS_RESULT_DEFAULTS['gene-crms']);
} catch (InvalidArgumentException $exception) {
    pcrms_invalid_request($exception->getMessage());
}
session_write_close();

try {
    require_once __DIR__ . '/connection.php';
    $gene = pcrms_execute_statement(
        $mysqli,
        'SELECT geneID FROM genes WHERE Genome = ? AND (geneID = ? OR Symbol = ?) LIMIT 1',
        'iss',
        [$genomeId, $geneId, $geneId]
    )->get_result()->fetch_row();

    $rows = [];
    if ($gene) {
        $records = pcrms_execute_statement(
            $mysqli,
            'SELECT DISTINCT crms.crmID, crms.Chromosome, crms.Start_Pos, crms.End_Pos, crms.Score, crms.P_value
             FROM crms
             INNER JOIN genes_crms_association ON crms.crmID = genes_crms_association.crmID
             INNER JOIN genes ON genes.geneID = genes_crms_association.geneID
             WHERE (genes.geneID = ? OR genes.Symbol = ?)
               AND genes.Genome = ? AND crms.Genome = ? AND crms.P_value <= ?',
            'ssiid',
            [$geneId, $geneId, $genomeId, $genomeId, (float) $pValue]
        )->get_result();
        while ($row = $records->fetch_assoc()) {
            $rows[] = pcrms_crm_row($row, $genomeId);
        }
    }
} catch (Throwable $exception) {
    pcrms_service_unavailable($exception);
}

$result = pcrms_results_local($rows, $PCRMS_RESULT_COLUMNS['gene-crms'], $state);
if (isset($_GET['format']) && $_GET['format'] === 'csv') {
    pcrms_results_csv('pcrms_' . pcrms_genome_key($genomeId) . '_crms_closest_to_' . $geneId . '.csv', [
        'crm_id' => ['id', true], 'chromosome' => ['chrom', true], 'start' => ['start', false], 'end' => ['end', false],
        'score' => ['score', false], 'p_value' => ['p', false],
    ], $result['all']);
}

$genome = $PCRMS_GENOMES[$genomeId];
$editUrl = 'detailed_search.php?' . http_build_query([
    'tool' => 'closest', 'genome_id' => $genomeId, 'gene_id' => $geneId, 'p_value' => $pValue,
], '', '&', PHP_QUERY_RFC3986);
$headline = number_format($result['total']) . ($result['total'] === 1 ? ' CRM found' : ' CRMs found');
$empty = $gene
    ? ['No CRMs found', 'No predicted CRMs that pass this cutoff have ' . $geneId . ' as their closest gene. Try a looser cutoff such as 0.05, or search for CRMs around the gene instead.']
    : ['Gene not found', 'There is no gene called ' . $geneId . ' in the ' . $genome['name'] . ' annotation. Check the spelling; symbols are case-sensitive (' . $genome['gene_example'] . ').'];

pcrms_page_start([
    'title' => 'CRMs closest to ' . $geneId . ', ' . $genome['short'],
    'active' => 'analysis',
]);
?>
<main id="main-content">
    <?php pcrms_results_band(['Functional analysis', 'detailed_search.php?tool=closest'], $headline, [
        pcrms_genome_tag($genomeId),
        'Closest to ' . pcrms_h($geneId),
        'p ≤ ' . pcrms_h($PCRMS_P_VALUE_LABELS[$pValue]),
    ], $editUrl); ?>

    <div class="page-main shell">
        <?php pcrms_results_card([
            'columns' => $PCRMS_RESULT_COLUMNS['gene-crms'],
            'defaults' => $PCRMS_RESULT_DEFAULTS['gene-crms'],
            'state' => $state,
            'result' => $result,
            'all_rows' => $rows,
            'url' => 'get_genes_closest_crm.php?query=' . $token,
            'csv' => 'get_genes_closest_crm.php?query=' . $token . '&format=csv',
            'caption' => 'Predicted CRMs whose closest gene is ' . $geneId . '. CRM IDs open the UCSC Genome Browser in a new tab.',
            'filter_label' => 'Filter by CRM ID',
            'noun' => ['CRM', 'CRMs'],
            'empty' => $empty,
        ]); ?>
    </div>
</main>
<?php pcrms_page_end(['js/results.js']); ?>
