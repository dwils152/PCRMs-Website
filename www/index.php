<?php
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/releases.php';
require_once dirname(__DIR__) . '/includes/publications.php';

$steps = [
    ['A', 'Extend binding peaks', 'Each TF ChIP-seq binding peak in every dataset is extended at both ends to 1,000 bp, so nearby sites of cooperating TFs are included.'],
    ['B', 'Find motifs', 'ProSampler finds the motifs in each dataset; each shape is a different motif.'],
    ['C', 'Find co-occurring pairs', 'Pairs of motifs that repeatedly occur together in the same peaks are identified in each dataset.'],
    ['D', 'Merge into unique motifs', 'Similar motifs from different datasets are clustered into unique motifs (UMs), each likely bound by one TF or TF family.'],
    ['E', 'Build interaction networks', 'Unique motifs that tend to co-occur are connected, giving a network of cooperating TFs.'],
    ['F', 'Link adjacent binding sites', 'Binding sites are mapped back to the genome and neighbouring sites are linked into CRM candidates (CRMCs).'],
    ['G', 'Score and filter', 'Each candidate gets an S<sub>CRM</sub> score and p-value. Candidates that pass the p-value cutoff are the predicted CRMs in PCRMs.'],
];
// Steps whose card is followed by an arrow to the next card in the same row.
$stepsWithArrow = ['A', 'B', 'C', 'E', 'F', 'G'];

pcrms_page_start([
    'title' => null,
    'description' => 'PCRMs is a database of cis-regulatory modules and transcription factor binding sites predicted from TF ChIP-seq data for human and mouse, with fruit fly, worm and thale cress coming soon.',
    'active' => 'home',
]);
?>
<main id="main-content">
    <section class="band home-band" aria-labelledby="page-title">
        <div class="band__inner shell">
            <h1 id="page-title">Predicted cis-regulatory modules and their binding sites</h1>
            <p class="band__lede">CRMs and transcription factor binding sites predicted from public TF ChIP-seq data for human and mouse. Fruit fly, worm and thale cress are coming soon.</p>
            <ul class="genome-stats" aria-label="Predicted CRMs per genome">
                <?php foreach ($PCRMS_RELEASES as $release): ?>
                    <?php $genome = $PCRMS_GENOMES[$release['genome_id']]; ?>
                    <li class="genome-stat">
                        <span class="genome-stat__icon"><?php echo pcrms_svg('organisms/' . $genome['key']); ?></span>
                        <span>
                            <span class="genome-stat__label"><?php echo $genome['version'] === 2 ? pcrms_h($genome['common']) : '<em>' . pcrms_h($genome['short']) . '</em>'; ?> CRMs (v<?php echo (int) $release['version']; ?>)</span>
                            <?php if (!$genome['searchable']): ?>
                                <span class="genome-stat__value genome-stat__value--soft">Coming soon</span>
                            <?php elseif ($release['crm_count'] !== null): ?>
                                <span class="genome-stat__value"><?php echo number_format($release['crm_count']); ?></span>
                            <?php else: ?>
                                <span class="genome-stat__value genome-stat__value--soft"><?php echo pcrms_h($genome['assembly_short']); ?> map</span>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <section class="how" aria-labelledby="how-title">
        <div class="how__inner shell">
            <div class="how__head">
                <div class="how__title-row">
                    <h2 id="how-title">How the predictions are made</h2>
                    <a class="pill-link" href="https://github.com/zhengchangsulab/pcrm2">
                        <svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M8 0c4.42 0 8 3.58 8 8a8.013 8.013 0 0 1-5.45 7.59c-.4.08-.55-.17-.55-.38 0-.27.01-1.13.01-2.2 0-.75-.25-1.23-.54-1.48 1.78-.2 3.65-.88 3.65-3.95 0-.88-.31-1.59-.82-2.15.08-.2.36-1.02-.08-2.12 0 0-.67-.22-2.2.82-.64-.18-1.32-.27-2-.27-.68 0-1.36.09-2 .27-1.53-1.03-2.2-.82-2.2-.82-.44 1.1-.16 1.92-.08 2.12-.51.56-.82 1.28-.82 2.15 0 3.06 1.86 3.75 3.64 3.95-.23.2-.44.55-.51 1.07-.46.21-1.61.55-2.33-.66-.15-.24-.6-.83-1.23-.82-.67.01-.27.38.01.53.34.19.73.9.82 1.13.16.45.68 1.31 2.69.94 0 .67.01 1.3.01 1.49 0 .21-.15.45-.55.38A7.995 7.995 0 0 1 0 8c0-4.42 3.58-8 8-8Z"/></svg>
                        dePCRM2 on GitHub
                    </a>
                </div>
                <p>PCRMs uses dePCRM2, which predicts CRMs from transcription factor (TF) ChIP-seq data alone, with no histone marks or other epigenetic data. It finds TF motifs that repeatedly occur together across thousands of TF ChIP-seq datasets and links their binding sites into cis-regulatory modules.</p>
            </div>

            <ol class="flow">
                <?php foreach ($steps as $step): ?>
                    <li class="flow-step" id="step-<?php echo strtolower($step[0]); ?>">
                        <div class="flow-step__head">
                            <span class="flow-step__letter" aria-hidden="true"><?php echo $step[0]; ?></span>
                            <h3><span class="sr-only">Step <?php echo $step[0]; ?>: </span><?php echo pcrms_h($step[1]); ?></h3>
                        </div>
                        <div class="flow-step__art"><?php echo pcrms_svg('pipeline/' . strtolower($step[0])); ?></div>
                        <p><?php echo $step[2]; ?></p>
                        <?php if (in_array($step[0], $stepsWithArrow, true)): ?>
                            <span class="flow-step__arrow" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="M7 4l6 6-6 6"/></svg></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <li class="flow-result">
                    <div>
                        <h3><span class="flow-result__check" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="M4.5 10.5l3.6 3.6 7.4-8"/></svg></span>Result: predicted CRMs</h3>
                        <p>The CRMs that pass the cutoff, with their binding sites, make up the maps in PCRMs.</p>
                    </div>
                    <a class="btn" href="browse_dataset.php">Browse the CRM maps
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4"/></svg></a>
                </li>
            </ol>
        </div>
    </section>

    <div class="home-refs shell">
        <section class="cite-box" aria-labelledby="cite-title">
            <h2 id="cite-title">Cite PCRMs</h2>
            <p><?php echo pcrms_h($PCRMS_CITATION['text']); ?> <em><?php echo pcrms_h($PCRMS_CITATION['venue']); ?></em> <?php echo pcrms_h($PCRMS_CITATION['details']); ?>
                <a href="https://doi.org/<?php echo pcrms_h($PCRMS_CITATION['doi']); ?>">doi:<?php echo pcrms_h($PCRMS_CITATION['doi']); ?></a></p>
        </section>

        <section class="related" aria-labelledby="related-title">
            <h2 id="related-title">Check out our related work</h2>
            <ul class="related-list" id="related-list" data-visible="<?php echo PCRMS_RELATED_VISIBLE; ?>">
                <?php foreach ($PCRMS_RELATED_WORK as $paper): ?>
                    <li>
                        <a href="https://doi.org/<?php echo pcrms_h($paper['doi']); ?>"><?php echo pcrms_h($paper['title']); ?></a>
                        <span class="related-list__meta"><?php echo pcrms_h($paper['authors']); ?> ·
                            <?php echo $paper['preprint'] ? pcrms_h($paper['venue']) : '<em>' . pcrms_h($paper['venue']) . '</em>'; ?> ·
                            <?php echo (int) $paper['year']; ?><?php if ($paper['preprint']): ?><span class="badge">Preprint</span><?php endif; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <button class="related-toggle js-only" type="button" aria-controls="related-list" aria-expanded="false" hidden>
                <span>Show more publications</span>
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 7.5l5 5 5-5"/></svg>
            </button>
        </section>
    </div>
</main>
<?php pcrms_page_end(); ?>
