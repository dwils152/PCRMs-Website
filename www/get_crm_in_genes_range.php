<?php
require_once dirname(__DIR__) . '/includes/results.php';
pcrms_session_start();

try {
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['genome_id'], $_POST['gene_id'], $_POST['p_value'], $_POST['location'], $_POST['distance'])) {
            throw new InvalidArgumentException('Choose a genome, enter a gene, set the window and pick a p-value cutoff.');
        }
        $token = pcrms_store_query_state('pcrms_range_queries', [
            'genome_id' => pcrms_validate_genome($_POST['genome_id']),
            'gene_id' => pcrms_validate_identifier($_POST['gene_id'], 'gene symbol or ID'),
            'p_value' => pcrms_validate_p_value($_POST['p_value']),
            'location' => pcrms_validate_enum($_POST['location'], $PCRMS_LOCATIONS, 'Choose upstream, downstream or both sides.'),
            'distance' => pcrms_validate_enum($_POST['distance'], $PCRMS_DISTANCES, 'Choose a window of 0.5, 1 or 2 Mb.'),
        ]);
        header('Location: get_crm_in_genes_range.php?query=' . rawurlencode($token), true, 303);
        exit;
    }
    $token = pcrms_validate_query_token(isset($_GET['query']) ? $_GET['query'] : '');
    $saved = isset($_SESSION['pcrms_range_queries'][$token]) ? $_SESSION['pcrms_range_queries'][$token] : null;
    if (!isset($saved['genome_id'], $saved['gene_id'], $saved['p_value'], $saved['location'], $saved['distance'])) {
        throw new InvalidArgumentException('That search has expired. Run it again from the functional analysis page.');
    }
    $genomeId = pcrms_validate_genome($saved['genome_id']);
    $geneId = pcrms_validate_identifier($saved['gene_id'], 'gene symbol or ID');
    $pValue = pcrms_validate_p_value($saved['p_value']);
    $location = pcrms_validate_enum($saved['location'], $PCRMS_LOCATIONS, 'Choose upstream, downstream or both sides.');
    $distance = pcrms_validate_enum($saved['distance'], $PCRMS_DISTANCES, 'Choose a window of 0.5, 1 or 2 Mb.');
    $state = pcrms_results_state($PCRMS_RESULT_COLUMNS['gene-crms'], $PCRMS_RESULT_DEFAULTS['gene-crms']);
} catch (InvalidArgumentException $exception) {
    pcrms_invalid_request($exception->getMessage());
}
session_write_close();

try {
    require_once dirname(__DIR__) . '/includes/connection.php';
    // A symbol can name more than one gene; search around each of them.
    $genes = pcrms_execute_statement(
        $mysqli,
        'SELECT Chromosome, Start_Pos, End_Pos, Strand FROM genes
         WHERE Genome = ? AND (LOWER(geneID) = LOWER(?) OR LOWER(Symbol) = LOWER(?)) LIMIT 20',
        'iss',
        [$genomeId, $geneId, $geneId]
    )->get_result()->fetch_all(MYSQLI_ASSOC);
    $gene = (bool) $genes;

    // Window bounds follow the gene's strand: upstream of a + strand gene lies
    // before its start, upstream of a − strand gene lies after its end. The
    // stored distance values are in units of 10 kb.
    $span = (int) $distance * 10000;
    $rows = [];
    foreach ($genes as $g) {
        $before = max(0, $g['Start_Pos'] - $span);
        $after = $g['End_Pos'] + $span;
        $plus = $g['Strand'] === '+';
        $windows = [
            'upstream' => $plus ? ['Start_Pos', $before, $g['Start_Pos']] : ['End_Pos', $g['End_Pos'], $after],
            'downstream' => $plus ? ['Start_Pos', $g['End_Pos'], $after] : ['End_Pos', $before, $g['Start_Pos']],
        ];
        if ($location === 'both') {
            $condition = 'Start_Pos >= ? AND End_Pos <= ? AND Start_Pos <= ?';
            $bounds = [$before, $after, $after];
        } else {
            list($column, $low, $high) = $windows[$location];
            // Start_Pos <= high keeps the search on the location index.
            $condition = "{$column} BETWEEN ? AND ? AND Start_Pos <= ?";
            $bounds = [$low, $high, $high];
        }
        $records = pcrms_execute_statement(
            $mysqli,
            "SELECT crmID, Chromosome, Start_Pos, End_Pos, Score, P_value FROM crms
             WHERE Genome = ? AND Dataset = ? AND Chromosome = ? AND {$condition} AND P_value <= ?",
            'iisiiid',
            array_merge([$genomeId, PCRMS_DEPCRM2_DATASET, $g['Chromosome']], $bounds, [(float) $pValue])
        )->get_result();
        while ($row = $records->fetch_assoc()) {
            $distance = pcrms_tss_distance($g, (int) $row['Start_Pos'], (int) $row['End_Pos']);
            // A CRM in the windows of two genes with the same symbol keeps the nearer TSS.
            if (!isset($rows[$row['crmID']]) || abs($distance) < abs($rows[$row['crmID']]['distance'])) {
                $rows[$row['crmID']] = pcrms_crm_row($row, $genomeId) + ['distance' => $distance];
            }
        }
    }
    ksort($rows);
    $rows = array_values($rows);
} catch (Throwable $exception) {
    pcrms_service_unavailable($exception);
}

$result = pcrms_results_local($rows, $PCRMS_RESULT_COLUMNS['gene-crms'], $state);
if (isset($_GET['format']) && $_GET['format'] === 'csv') {
    pcrms_results_csv('pcrms_' . pcrms_genome_key($genomeId) . '_crms_around_' . $geneId . '.csv', [
        'crm_id' => ['id', true], 'chromosome' => ['chrom', true], 'start' => ['start', false], 'end' => ['end', false],
        'tss_distance_bp' => ['distance', false], 'score' => ['score', false], 'p_value' => ['p', false],
    ], $result['all']);
}

$genome = $PCRMS_GENOMES[$genomeId];
$where = ['upstream' => 'upstream of', 'downstream' => 'downstream of', 'both' => 'on either side of'][$location];
$window = $PCRMS_DISTANCE_LABELS[$distance] . ' ' . $where . ' ' . $geneId;
$editUrl = 'detailed_search.php?' . http_build_query([
    'tool' => 'range', 'genome_id' => $genomeId, 'gene_id' => $geneId, 'p_value' => $pValue,
    'location' => $location, 'distance' => $distance,
], '', '&', PHP_QUERY_RFC3986);
$headline = number_format($result['total']) . ($result['total'] === 1 ? ' CRM found' : ' CRMs found');
$empty = $gene
    ? ['No CRMs found', 'No predicted CRMs pass this cutoff within ' . $window . '. Try a wider window or a looser cutoff such as 0.05.']
    : ['Gene not found', 'There is no gene called ' . $geneId . ' in the ' . $genome['name'] . ' annotation. Check the spelling (' . $genome['gene_example'] . ').'];

pcrms_page_start([
    'title' => 'CRMs around ' . $geneId . ', ' . $genome['short'],
    'active' => 'analysis',
]);
?>
<main id="main-content">
    <?php pcrms_results_band(['Functional analysis', 'detailed_search.php?tool=range'], $headline, [
        pcrms_genome_tag($genomeId),
        'Within ' . pcrms_h($window),
        'p ≤ ' . pcrms_h($PCRMS_P_VALUE_LABELS[$pValue]),
    ], $editUrl); ?>

    <div class="page-main shell">
        <?php pcrms_results_card([
            'columns' => $PCRMS_RESULT_COLUMNS['gene-crms'],
            'defaults' => $PCRMS_RESULT_DEFAULTS['gene-crms'],
            'state' => $state,
            'result' => $result,
            'all_rows' => $rows,
            'url' => 'get_crm_in_genes_range.php?query=' . $token,
            'csv' => 'get_crm_in_genes_range.php?query=' . $token . '&format=csv',
            'caption' => 'Predicted CRMs within ' . $window . '. CRM IDs open the UCSC Genome Browser in a new tab.',
            'filter_label' => 'Filter by CRM ID',
            'noun' => ['CRM', 'CRMs'],
            'empty' => $empty,
        ]); ?>
    </div>
</main>
<?php pcrms_page_end(['js/results.js']); ?>
