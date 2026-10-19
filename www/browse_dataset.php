<?php
require_once dirname(__DIR__) . '/includes/forms.php';

// Two tabs: the dePCRM2 maps (the search form) and the comparative maps
// (?maps=comparative), which are not loaded yet.
$maps = isset($_GET['maps']) && $_GET['maps'] === 'comparative' ? 'comparative' : 'depcrm2';
$mapTabs = [
    'depcrm2' => ['dePCRM2 maps', 'CRMs predicted from TF ChIP-seq data.', 'browse_dataset.php',
        '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>'],
    'comparative' => ['Comparative maps', 'Primate and rodent CRMs predicted with Nucleotide Transformer. Coming soon.', 'browse_dataset.php?maps=comparative',
        '<path d="M7 7h12M15 3l4 4-4 4M17 17H5M9 13l-4 4 4 4"/>'],
];

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

    <div class="page-main shell stack">
        <nav class="tool-tabs tool-tabs--two" aria-label="Choose a set of maps">
            <?php foreach ($mapTabs as $key => $tab): ?>
                <a class="tool-tab" href="<?php echo pcrms_h($tab[2]); ?>"<?php echo $key === $maps ? ' aria-current="true"' : ''; ?>>
                    <span class="tool-tab__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><?php echo $tab[3]; ?></svg></span>
                    <span class="tool-tab__text"><strong><?php echo pcrms_h($tab[0]); ?></strong><span><?php echo pcrms_h($tab[1]); ?></span></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($maps === 'comparative'): ?>
        <section class="card" aria-labelledby="comparative-title">
            <h2 class="card__title" id="comparative-title">Comparative maps</h2>
            <p>CRMs predicted from DNA sequence with Nucleotide Transformer, a genomic language model, for primate and rodent genomes. The maps will appear here once they are released.</p>
            <div class="genome-grid">
                <?php foreach ([['hs', 'Primates'], ['mm', 'Rodents']] as $group): ?>
                    <span class="genome-card genome-card--soon">
                        <span class="genome-card__icon"><?php echo pcrms_svg('organisms/' . $group[0]); ?></span>
                        <span class="genome-card__name"><?php echo pcrms_h($group[1]); ?></span>
                        <span class="genome-card__meta">Coming soon</span>
                    </span>
                <?php endforeach; ?>
            </div>
            <p class="note">Until then, <a href="browse_dataset.php">browse the dePCRM2 maps</a> of the human and mouse genomes.</p>
        </section>
        <?php else: ?>
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
        <?php endif; ?>
    </div>
</main>
<?php pcrms_page_end(['js/forms.js']); ?>
