<?php
require_once dirname(__DIR__) . '/includes/forms.php';

$tools = [
    'closest' => [
        'title' => 'Closest CRMs to a gene',
        'blurb' => 'CRMs whose closest gene is the one you enter.',
        'icon' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    ],
    'range' => [
        'title' => 'CRMs around a gene',
        'blurb' => 'All CRMs upstream, downstream or on both sides of a gene.',
        'icon' => '<path d="M3 12h18M7 8l-4 4 4 4M17 8l4 4-4 4"/>',
    ],
    'tf' => [
        'title' => 'TF binding sites',
        'blurb' => 'Predicted binding sites of a transcription factor.',
        'icon' => '<path d="M4 7h16M4 12h10M4 17h6"/>',
    ],
];
$tool = isset($_GET['tool']) && is_string($_GET['tool']) && isset($tools[$_GET['tool']]) ? $_GET['tool'] : 'closest';

// Defaults, then any prefill from an "Edit search" link for the current tool.
$state = [];
foreach (array_keys($tools) as $key) {
    $state[$key] = ['genome_id' => 1, 'gene_id' => '', 'tf_id' => '', 'p_value' => '0.05',
        'location' => 'both', 'distance' => '100', 'chr' => ['chr1']];
}
try {
    $prefill = &$state[$tool];
    if (isset($_GET['genome_id'])) {
        $prefill['genome_id'] = pcrms_validate_genome($_GET['genome_id']);
        $prefill['chr'] = [$PCRMS_GENOMES[$prefill['genome_id']]['chromosomes'][0]];
    }
    if (isset($_GET['gene_id']) && $_GET['gene_id'] !== '') {
        $prefill['gene_id'] = pcrms_validate_identifier($_GET['gene_id'], 'gene');
    }
    if (isset($_GET['tf_id']) && $_GET['tf_id'] !== '') {
        $prefill['tf_id'] = pcrms_validate_identifier($_GET['tf_id'], 'TF');
    }
    if (isset($_GET['p_value'])) {
        $prefill['p_value'] = pcrms_validate_p_value($_GET['p_value']);
    }
    if (isset($_GET['location'])) {
        $prefill['location'] = pcrms_validate_enum($_GET['location'], $PCRMS_LOCATIONS, 'location');
    }
    if (isset($_GET['distance'])) {
        $prefill['distance'] = pcrms_validate_enum($_GET['distance'], $PCRMS_DISTANCES, 'distance');
    }
    if (isset($_GET['chr']) && is_string($_GET['chr'])) {
        $prefill['chr'] = pcrms_validate_chromosomes($prefill['genome_id'], explode(',', $_GET['chr']));
    }
} catch (InvalidArgumentException $exception) {
    // A stale or edited link keeps whatever was valid.
}
unset($prefill);

// The search-window diagram: 105 px per Mb either side of a gene at x 270–332.
function pcrms_range_figure($location, $distance)
{
    global $PCRMS_DISTANCE_LABELS;
    $label = $PCRMS_DISTANCE_LABELS[$distance];
    $width = ((int) $distance / 100) * 105;
    $up = $location !== 'downstream' ? '1' : '0';
    $down = $location !== 'upstream' ? '1' : '0';
    $where = ['upstream' => 'upstream of', 'downstream' => 'downstream of', 'both' => 'on either side of'][$location];
    ?>
    <figure class="window-figure">
        <svg viewBox="0 0 600 96" role="img" aria-label="Search window: <?php echo pcrms_h($label . ' ' . $where); ?> the gene" data-range-figure>
            <path d="M10 52H590" stroke="#B9C7BF" stroke-width="2"/>
            <rect data-part="up" x="<?php echo 270 - $width; ?>" y="34" width="<?php echo $width; ?>" height="36" rx="5" fill="#B6DB4C" opacity="<?php echo $up; ?>"/>
            <rect data-part="down" x="332" y="34" width="<?php echo $width; ?>" height="36" rx="5" fill="#B6DB4C" opacity="<?php echo $down; ?>"/>
            <path d="M272 42h46l12 10-12 10h-46z" fill="#17694B"/>
            <text data-part="gene" x="295" y="88" font-size="13" font-weight="700" text-anchor="middle" fill="#17221D">your gene</text>
            <text data-part="up-label" x="<?php echo 270 - $width / 2; ?>" y="24" font-size="13" font-weight="700" text-anchor="middle" fill="#46534C" opacity="<?php echo $up; ?>"><?php echo pcrms_h($label); ?> upstream</text>
            <text data-part="down-label" x="<?php echo 332 + $width / 2; ?>" y="24" font-size="13" font-weight="700" text-anchor="middle" fill="#46534C" opacity="<?php echo $down; ?>"><?php echo pcrms_h($label); ?> downstream</text>
        </svg>
        <figcaption>Upstream and downstream follow the gene’s strand; shown here for a gene on the + strand.</figcaption>
    </figure>
    <?php
}

function pcrms_identifier_field($id, $name, $label, $value, $placeholder, $hint)
{
    ?>
    <label class="field-label" for="<?php echo $id; ?>"><?php echo pcrms_h($label); ?></label>
    <input class="text-input" id="<?php echo $id; ?>" name="<?php echo $name; ?>" type="text" value="<?php echo pcrms_h($value); ?>"
        placeholder="<?php echo pcrms_h($placeholder); ?>" required maxlength="100" pattern="[A-Za-z0-9_.:\-]+"
        autocomplete="off" autocapitalize="off" spellcheck="false" aria-describedby="<?php echo $id; ?>-hint" data-identifier>
    <p class="field-hint" id="<?php echo $id; ?>-hint" data-hint="<?php echo pcrms_h($hint); ?>"><?php echo pcrms_h($hint); ?></p>
    <?php
}

pcrms_page_start([
    'title' => 'Functional analysis',
    'description' => 'Find the predicted CRMs near a gene, or the predicted binding sites of a transcription factor.',
    'active' => 'analysis',
]);
?>
<main id="main-content">
    <section class="band" aria-labelledby="page-title">
        <div class="band__inner shell">
            <h1 id="page-title">Functional analysis</h1>
            <p class="band__lede">Start from a gene or a transcription factor to find the predicted cis-regulatory modules that may regulate it, or the sites where it binds.</p>
        </div>
    </section>

    <div class="page-main shell stack">
        <nav class="tool-tabs" aria-label="Choose a search" data-tool-tabs>
            <?php foreach ($tools as $key => $info): ?>
                <a class="tool-tab" id="tab-<?php echo $key; ?>" href="detailed_search.php?tool=<?php echo $key; ?>" aria-controls="panel-<?php echo $key; ?>" data-tool="<?php echo $key; ?>"<?php echo $key === $tool ? ' aria-current="true"' : ''; ?>>
                    <span class="tool-tab__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><?php echo $info['icon']; ?></svg></span>
                    <span class="tool-tab__text"><strong><?php echo pcrms_h($info['title']); ?></strong><span><?php echo pcrms_h($info['blurb']); ?></span></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php $s = $state['closest']; ?>
        <section class="tool-panel<?php echo $tool === 'closest' ? ' is-current' : ''; ?>" id="panel-closest" data-tool="closest" aria-labelledby="closest-title">
            <h2 class="tool-panel__title" id="closest-title"><?php echo pcrms_h($tools['closest']['title']); ?></h2>
            <form class="stack" action="get_genes_closest_crm.php" method="post" data-search-form="closest" aria-labelledby="closest-title">
                <fieldset class="card step">
                    <?php pcrms_step_legend(1, 'Choose a genome', 3); ?>
                    <?php pcrms_genome_options($s['genome_id']); ?>
                </fieldset>
                <fieldset class="card step">
                    <?php pcrms_step_legend(2, 'Enter a gene', 3); ?>
                    <?php pcrms_identifier_field('closest-gene', 'gene_id', 'Gene symbol or stable ID', $s['gene_id'],
                        $PCRMS_GENOMES[$s['genome_id']]['gene_example'], 'Use one gene symbol or Ensembl ID.'); ?>
                </fieldset>
                <fieldset class="card step">
                    <?php pcrms_step_legend(3, 'Set the p-value cutoff', 3); ?>
                    <?php pcrms_choice_options('p_value', $PCRMS_P_VALUE_LABELS, $s['p_value']); ?>
                    <p class="note">Lower cutoffs return fewer, higher-confidence CRMs.</p>
                </fieldset>
                <?php pcrms_search_button('Find closest CRMs'); ?>
            </form>
        </section>

        <?php $s = $state['range']; ?>
        <section class="tool-panel<?php echo $tool === 'range' ? ' is-current' : ''; ?>" id="panel-range" data-tool="range" aria-labelledby="range-title">
            <h2 class="tool-panel__title" id="range-title"><?php echo pcrms_h($tools['range']['title']); ?></h2>
            <form class="stack" action="get_crm_in_genes_range.php" method="post" data-search-form="range" aria-labelledby="range-title">
                <fieldset class="card step">
                    <?php pcrms_step_legend(1, 'Choose a genome', 3); ?>
                    <?php pcrms_genome_options($s['genome_id']); ?>
                </fieldset>
                <fieldset class="card step">
                    <?php pcrms_step_legend(2, 'Enter a gene', 3); ?>
                    <?php pcrms_identifier_field('range-gene', 'gene_id', 'Gene symbol or stable ID', $s['gene_id'],
                        $PCRMS_GENOMES[$s['genome_id']]['gene_example'], 'Use one gene symbol or Ensembl ID.'); ?>
                </fieldset>
                <fieldset class="card step">
                    <?php pcrms_step_legend(3, 'Choose the window', 3); ?>
                    <div class="choice-groups">
                        <fieldset class="choice-group">
                            <legend>Where</legend>
                            <?php pcrms_choice_options('location', $PCRMS_LOCATION_LABELS, $s['location']); ?>
                        </fieldset>
                        <fieldset class="choice-group">
                            <legend>How far</legend>
                            <?php pcrms_choice_options('distance', $PCRMS_DISTANCE_LABELS, $s['distance']); ?>
                        </fieldset>
                    </div>
                    <?php pcrms_range_figure($s['location'], $s['distance']); ?>
                </fieldset>
                <fieldset class="card step">
                    <?php pcrms_step_legend(4, 'Set the p-value cutoff', 3); ?>
                    <?php pcrms_choice_options('p_value', $PCRMS_P_VALUE_LABELS, $s['p_value']); ?>
                    <p class="note">Lower cutoffs return fewer, higher-confidence CRMs.</p>
                </fieldset>
                <?php pcrms_search_button('Find CRMs around gene'); ?>
            </form>
        </section>

        <?php $s = $state['tf']; ?>
        <section class="tool-panel<?php echo $tool === 'tf' ? ' is-current' : ''; ?>" id="panel-tf" data-tool="tf" aria-labelledby="tf-title">
            <h2 class="tool-panel__title" id="tf-title"><?php echo pcrms_h($tools['tf']['title']); ?></h2>
            <form class="stack" action="get_tf.php" method="post" data-search-form="tf" aria-labelledby="tf-title">
                <fieldset class="card step">
                    <?php pcrms_step_legend(1, 'Choose a genome', 3); ?>
                    <?php pcrms_genome_options($s['genome_id']); ?>
                </fieldset>
                <fieldset class="card step">
                    <?php pcrms_step_legend(2, 'Enter a transcription factor', 3); ?>
                    <?php pcrms_identifier_field('tf-tf', 'tf_id', 'TF symbol', $s['tf_id'],
                        $PCRMS_GENOMES[$s['genome_id']]['tf_example'], 'Use one TF symbol, as it appears in the motif annotations.'); ?>
                </fieldset>
                <fieldset class="card step">
                    <?php pcrms_step_legend(3, 'Pick chromosomes', 3); ?>
                    <?php pcrms_chromosome_options($s['genome_id'], $s['chr']); ?>
                </fieldset>
                <?php pcrms_search_button('Find binding sites'); ?>
            </form>
        </section>
    </div>
</main>
<?php pcrms_page_end(['js/forms.js']); ?>
