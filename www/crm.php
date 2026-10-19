<?php
require_once __DIR__ . '/includes/results.php';

try {
    if (!isset($_GET['crmId'], $_GET['genome_id'])) {
        throw new InvalidArgumentException('Open a CRM from a results table.');
    }
    $crmId = pcrms_validate_identifier($_GET['crmId'], 'CRM ID');
    $genomeId = pcrms_validate_genome($_GET['genome_id']);
    $state = pcrms_results_state($PCRMS_RESULT_COLUMNS['crm-sites'], $PCRMS_RESULT_DEFAULTS['crm-sites']);
} catch (InvalidArgumentException $exception) {
    pcrms_invalid_request($exception->getMessage());
}

try {
    require_once __DIR__ . '/connection.php';
    $crm = pcrms_execute_statement(
        $mysqli,
        'SELECT c.crmID, c.Chromosome, c.Start_Pos, c.End_Pos, c.Score, c.P_value, g.Genes_Symbol
         FROM crms c
         LEFT JOIN crms_genes g ON g.crmID = c.crmID AND g.Genome = c.Genome
         WHERE c.crmID = ? AND c.Genome = ?
         LIMIT 1',
        'si',
        [$crmId, $genomeId]
    )->get_result()->fetch_assoc();

    $rows = [];
    if ($crm) {
        $sites = pcrms_execute_statement(
            $mysqli,
            'SELECT t.TFBS_Start, t.TFBS_End, t.umID, t.Binding_Score, u.tfNames
             FROM tfbss t
             LEFT JOIN umotif_tfs_association_multiple u ON u.umID = t.umID AND u.Genome = ?
             WHERE t.crmID = ?',
            'is',
            [$genomeId, $crmId]
        )->get_result();
        while ($row = $sites->fetch_assoc()) {
            $rows[] = pcrms_site_row($row, $genomeId, $crm['Chromosome']);
        }
    }
} catch (Throwable $exception) {
    pcrms_service_unavailable($exception);
}

if (!$crm) {
    pcrms_message_page(404, 'CRM not found', 'CRM not found',
        'There is no CRM called ' . $crmId . ' in the ' . $PCRMS_GENOMES[$genomeId]['name'] . ' map.', [
            ['Browse the CRM maps', 'browse_dataset.php'],
        ]);
}

$record = pcrms_crm_row($crm, $genomeId);
$result = pcrms_results_local($rows, $PCRMS_RESULT_COLUMNS['crm-sites'], $state);
$recordUrl = pcrms_crm_url($crmId, $genomeId);
if (isset($_GET['format']) && $_GET['format'] === 'csv') {
    pcrms_results_csv('pcrms_' . pcrms_genome_key($genomeId) . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '', $crmId) . '_tfbs.csv', [
        'chromosome' => ['chrom', true], 'tfbs_start' => ['start', false], 'tfbs_end' => ['end', false],
        'motif_id' => ['motif', true], 'matched_tfs' => ['tf', true], 'binding_score' => ['score', false],
    ], $result['all']);
}

$location = $record['chrom'] . ':' . number_format($record['start']) . '–' . number_format($record['end']);
pcrms_page_start([
    'title' => 'CRM ' . $crmId,
    'description' => 'Predicted cis-regulatory module ' . $crmId . ' and its transcription factor binding sites.',
    'active' => 'browse',
]);
?>
<main id="main-content">
    <section class="band" aria-labelledby="page-title">
        <div class="band__inner shell band__row">
            <div>
                <p class="breadcrumb"><a href="browse_dataset.php">Browse</a> <span aria-hidden="true">/</span> CRM</p>
                <h1 id="page-title"><?php echo pcrms_h($crmId); ?></h1>
                <ul class="tags" aria-label="CRM">
                    <li><?php echo pcrms_genome_tag($genomeId); ?></li>
                    <li><?php echo pcrms_h($location); ?></li>
                </ul>
            </div>
            <a class="btn btn-on-pine" href="<?php echo pcrms_h($record['ucsc']); ?>" target="_blank" rel="noopener">Open in UCSC Genome Browser
                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M9 3h4v4M13 3 7 9M11 9.5V13H3V5h3.5"/></svg><span class="sr-only"> (opens in a new tab)</span></a>
        </div>
    </section>

    <div class="page-main shell stack">
        <section class="card" aria-labelledby="facts-title">
            <h2 class="sr-only" id="facts-title">Summary</h2>
            <dl class="detail-facts">
                <div><dt>Location (<?php echo pcrms_h($PCRMS_GENOMES[$genomeId]['assembly_short']); ?>)</dt><dd><?php echo pcrms_h($location); ?></dd></div>
                <div><dt>Length</dt><dd><?php echo number_format($record['end'] - $record['start'] + 1); ?> bp</dd></div>
                <div><dt>Score</dt><dd><?php echo number_format($record['score'], 2); ?></dd></div>
                <div><dt>p-value</dt><dd><?php echo pcrms_h(pcrms_format_p_value($record['p'])); ?></dd></div>
                <div><dt>Closest gene(s)</dt><dd><?php echo $record['genes'] !== '' ? pcrms_h($record['genes']) : '—'; ?></dd></div>
                <div><dt>Binding sites</dt><dd><?php echo number_format($result['total']); ?></dd></div>
            </dl>
        </section>

        <h2 class="section-title">Binding sites in this CRM</h2>
        <?php pcrms_results_card([
            'columns' => $PCRMS_RESULT_COLUMNS['crm-sites'],
            'defaults' => $PCRMS_RESULT_DEFAULTS['crm-sites'],
            'state' => $state,
            'result' => $result,
            'all_rows' => $rows,
            'url' => $recordUrl,
            'csv' => $recordUrl . '&format=csv',
            'caption' => 'Predicted transcription factor binding sites in ' . $crmId . '. Binding sites open the UCSC Genome Browser in a new tab.',
            'filter_label' => 'Filter by motif or TF',
            'noun' => ['binding site', 'binding sites'],
            'empty' => ['No binding sites', 'No binding sites are recorded for this CRM.'],
        ]); ?>
    </div>
</main>
<?php pcrms_page_end(['js/results.js']); ?>
