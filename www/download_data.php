<?php
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/releases.php';

if (!function_exists('pcrms_file_size')) {
    function pcrms_file_size($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;
        $size = (float) $bytes;
        while ($size >= 1000 && $index < count($units) - 1) {
            $size /= 1000;
            $index++;
        }
        return number_format($size, $index > 1 ? 1 : 0) . ' ' . $units[$index];
    }
}

// Only releases with files are listed.
$PCRMS_RELEASES = array_values(array_filter($PCRMS_RELEASES, function ($release) {
    return (bool) $release['files'];
}));

// Files live in www/data; anything missing is listed as not available yet.
// The example command uses the first binding-site file on the server.
$missing = 0;
$example = 'Human_TFBSs_v2_hg38.bed.gz';
$exampleFound = false;
foreach ($PCRMS_RELEASES as $r => $release) {
    foreach ($release['files'] as $f => $file) {
        $path = __DIR__ . '/data/' . $file['name'];
        $available = is_file($path);
        $PCRMS_RELEASES[$r]['files'][$f]['size'] = $available ? pcrms_file_size(filesize($path)) : null;
        if (!$available) {
            $missing++;
        } elseif (!$exampleFound && $file['kind'] === 'sites') {
            $example = $file['name'];
            $exampleFound = true;
        }
    }
}

pcrms_page_start([
    'title' => 'Download the data',
    'description' => 'Download genome-wide predicted CRMs and transcription factor binding sites as BED files.',
    'active' => 'downloads',
]);
?>
<main id="main-content">
    <section class="band" aria-labelledby="page-title">
        <div class="band__inner shell">
            <h1 id="page-title">Download the data</h1>
            <p class="band__lede">Genome-wide predicted CRMs and their transcription factor binding sites for human and mouse, as gzip-compressed BED files: CRMs with their binding sites, and the binding sites and CRMs on their own. Fruit fly, worm and thale cress are coming soon.</p>
        </div>
    </section>

    <div class="page-main shell stack">
        <nav aria-label="Jump to a genome">
            <ul class="jump-list">
                <?php foreach ($PCRMS_RELEASES as $release): ?>
                    <?php $genome = $PCRMS_GENOMES[$release['genome_id']]; ?>
                    <li><a href="#<?php echo pcrms_h($genome['key']); ?>"><?php echo pcrms_svg('organisms/' . $genome['key']); ?><?php echo pcrms_h($genome['common']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <?php if ($missing > 0): ?>
            <div class="alert" role="note">
                <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="8"/><path d="M10 6v5M10 14h.01"/></svg>
                <p><?php echo $missing === 1 ? '1 of the listed archives is' : $missing . ' of the listed archives are'; ?> not on this server yet; <?php echo $missing === 1 ? 'it is' : 'they are'; ?> marked “Not available yet”.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($PCRMS_RELEASES as $release): ?>
            <?php $genome = $PCRMS_GENOMES[$release['genome_id']]; ?>
            <section class="genome-section" id="<?php echo pcrms_h($genome['key']); ?>" aria-labelledby="<?php echo pcrms_h($genome['key']); ?>-title">
                <div class="genome-section__head">
                    <div class="genome-section__title">
                        <span class="genome-section__icon"><?php echo pcrms_svg('organisms/' . $genome['key']); ?></span>
                        <div>
                            <h2 id="<?php echo pcrms_h($genome['key']); ?>-title"><?php echo pcrms_h($genome['common']); ?> <em><?php echo pcrms_h($genome['name']); ?></em></h2>
                            <p class="genome-section__sub"><?php echo pcrms_h($genome['assembly']); ?> · Version <?php echo (int) $release['version']; ?><?php if (!$genome['searchable']): ?><span class="badge">Search coming soon</span><?php endif; ?></p>
                        </div>
                    </div>
                    <?php if ($release['facts']): ?>
                        <dl class="facts">
                            <?php foreach ($release['facts'] as $fact): ?>
                                <div>
                                    <dt><?php echo pcrms_h($fact[1]); ?><?php if (isset($fact[2])): ?><a class="fact-note" href="<?php echo pcrms_h($fact[2]); ?>" title="How the predictions are made">*<span class="sr-only"> (how the predictions are made)</span></a><?php endif; ?></dt>
                                    <dd><?php echo pcrms_h($fact[0]); ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>
                </div>
                <ul class="file-list" aria-label="<?php echo pcrms_h($genome['common']); ?> files">
                    <?php foreach ($release['files'] as $file): ?>
                        <li class="file-row">
                            <div>
                                <span class="file-row__name"><?php echo str_replace('_', '_<wbr>', pcrms_h($file['name'])); ?></span>
                                <span class="file-row__desc"><?php echo pcrms_h($file['label']); ?></span>
                            </div>
                            <span class="file-row__size">
                                <?php if ($file['size'] !== null): ?>
                                    <?php echo pcrms_h($file['size']); ?> · .bed.gz
                                <?php elseif (isset($file['expected'])): ?>
                                    About <?php echo pcrms_h($file['expected']); ?> · .bed.gz
                                <?php endif; ?>
                            </span>
                            <span class="file-row__action">
                                <?php if ($file['size'] !== null): ?>
                                    <a class="btn btn-primary" href="data/<?php echo rawurlencode($file['name']); ?>" download>
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m-5-5 5 5 5-5M4 21h16"/></svg>Download<span class="sr-only"> <?php echo pcrms_h($file['name']); ?>, <?php echo pcrms_h($file['size']); ?></span></a>
                                <?php else: ?>
                                    <span class="file-row__unavailable">Not available yet</span>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>

        <section class="using card" aria-labelledby="using-title">
            <div>
                <h2 id="using-title">Using the files</h2>
                <p>Each file is tab-separated text compressed with gzip. bedtools and most genome tools read it as it is; to look inside:</p>
                <pre><code>gzip -dc <?php echo pcrms_h($example); ?> | head</code></pre>
                <dl class="format-list">
                    <div><dt>CRMs and their binding sites</dt><dd>One CRM per line: chromosome, start, end, its binding sites, CRM score and P-value. The binding sites are comma-separated, each written <code>motif|start:end+binding score+motif score</code>.</dd></div>
                    <div><dt>Binding sites</dt><dd>One binding site per line, in genomic order: <code>chrom start end motif binding_score crm_id</code>. <code>motif</code> is the motif number shown with its logo on CRM pages; <code>crm_id</code> is the CRM’s ID on this site.</dd></div>
                    <div><dt>CRMs only</dt><dd>One CRM per line, in genomic order: <code>chrom start end crm_id score p_value</code>.</dd></div>
                </dl>
            </div>
        </section>
    </div>
</main>
<?php pcrms_page_end(); ?>
