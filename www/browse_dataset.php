<?php
require_once __DIR__ . '/includes/forms.php';

// Prefill from an "Edit search" link: ?genome_id=1&chr=chr1,chr2&p_value=0.05
$genomeId = 1;
$selected = ['chr1'];
$pValue = '0.05';
try {
    if (isset($_GET['genome_id'])) {
        $genomeId = pcrms_validate_genome($_GET['genome_id']);
        $selected = [$PCRMS_GENOMES[$genomeId]['chromosomes'][0]];
    }
    if (isset($_GET['chr']) && is_string($_GET['chr'])) {
        $selected = pcrms_validate_chromosomes($genomeId, explode(',', $_GET['chr']));
    }
    if (isset($_GET['p_value'])) {
        $pValue = pcrms_validate_p_value($_GET['p_value']);
    }
} catch (InvalidArgumentException $exception) {
    // A stale or edited link falls back to the defaults.
}

pcrms_page_start([
    'title' => 'Browse the CRM maps',
    'description' => 'List predicted cis-regulatory modules on chosen chromosomes of a genome, filtered by p-value.',
    'active' => 'browse',
]);
?>
<main id="main-content">
    <section class="band" aria-labelledby="page-title">
        <div class="band__inner shell">
            <h1 id="page-title">Browse the CRM maps</h1>
            <p class="band__lede">List the predicted cis-regulatory modules on chosen chromosomes of a genome, filtered by p-value. Each result links to the CRM’s record and its binding sites.</p>
        </div>
    </section>

    <div class="page-main shell">
        <form class="stack" action="get_crms.php" method="post" data-search-form="browse" aria-labelledby="page-title">
            <fieldset class="card step">
                <?php pcrms_step_legend(1, 'Choose a genome'); ?>
                <?php pcrms_genome_options($genomeId); ?>
            </fieldset>

            <fieldset class="card step">
                <?php pcrms_step_legend(2, 'Pick chromosomes'); ?>
                <?php pcrms_chromosome_options($genomeId, $selected); ?>
            </fieldset>

            <fieldset class="card step">
                <?php pcrms_step_legend(3, 'Set the p-value cutoff'); ?>
                <?php pcrms_choice_options('p_value', $PCRMS_P_VALUE_LABELS, $pValue); ?>
                <p class="note">Lower cutoffs return fewer, higher-confidence CRMs.</p>
            </fieldset>

            <?php pcrms_search_button('Search CRMs'); ?>
        </form>
    </div>
</main>
<?php pcrms_page_end(['js/forms.js']); ?>
