<?php
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

require_once __DIR__ . '/includes/releases.php';
$releaseCards = $PCRMS_RELEASES;
$missingArchiveCount = 0;

foreach ($releaseCards as $release) {
    foreach ($release['files'] as $file) {
        if (!is_file(__DIR__ . '/data/' . $file['name'])) {
            $missingArchiveCount++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Download genome-wide PCRMs release files for human and model organisms.">
    <title>Download datasets — PCRMs</title>
    <link rel="icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/modern.css">
</head>
<body class="d-flex flex-column min-vh-100">
    <?php include __DIR__ . '/download_header.php'; ?>

    <main class="site-main" id="main-content">
        <header class="page-hero">
            <div class="page-hero__inner shell">
                <p class="eyebrow">Genome-wide resources</p>
                <h1 class="page-title">Download datasets</h1>
                <p class="lede">Access packaged CRM and TFBS coordinate files for downstream analysis. Every available archive is linked directly from this page.</p>
                <div class="page-hero__meta">
                    <span class="meta-pill">BED-format coordinates</span>
                    <span class="meta-pill">Scores &amp; P values</span>
                    <span class="meta-pill">Versioned releases</span>
                </div>
            </div>
        </header>

        <section class="section--tight">
            <div class="shell">
                <?php if ($missingArchiveCount > 0): ?>
                    <div class="data-note" role="note">
                        <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M10 9v5m0-8v.2" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                        <div><strong>Archive availability:</strong> <?php echo number_format($missingArchiveCount); ?> referenced archive<?php echo $missingArchiveCount === 1 ? ' is' : 's are'; ?> not present in this deployment. Missing files are shown as unavailable instead of leading to broken downloads.</div>
                    </div>
                <?php endif; ?>

                <div class="release-grid">
                    <?php foreach ($releaseCards as $index => $release): ?>
                        <article class="release-card<?php echo $index === count($releaseCards) - 1 ? ' release-card--wide' : ''; ?>">
                            <div class="release-card__top">
                                <div class="release-card__organism">
                                    <strong><?php echo htmlspecialchars($release['common'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <em><?php echo htmlspecialchars($release['scientific'], ENT_QUOTES, 'UTF-8'); ?></em>
                                </div>
                                <span class="release-card__assembly"><?php echo htmlspecialchars($release['assembly'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>

                            <h3><?php echo htmlspecialchars($release['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <ul class="release-card__facts">
                                <?php foreach ($release['facts'] as $fact): ?>
                                    <li><?php echo htmlspecialchars($fact, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>

                            <ul class="download-list">
                                <?php foreach ($release['files'] as $file): ?>
                                    <?php
                                    $relativePath = 'data/' . $file['name'];
                                    $absolutePath = __DIR__ . '/' . $relativePath;
                                    $isAvailable = is_file($absolutePath);
                                    $fileMeta = $isAvailable
                                        ? pcrms_file_size(filesize($absolutePath)) . ' · TAR.GZ archive'
                                        : (isset($file['expected']) ? $file['expected'] . ' · file needed' : 'File needed');
                                    ?>
                                    <li>
                                        <?php if ($isAvailable): ?>
                                            <a class="download-link" href="<?php echo htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8'); ?>" download>
                                                <span class="download-link__copy">
                                                    <strong><?php echo htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                    <span><?php echo htmlspecialchars($file['label'] . ' · ' . $fileMeta, ENT_QUOTES, 'UTF-8'); ?></span>
                                                </span>
                                                <span class="download-link__icon" aria-hidden="true">
                                                    <svg viewBox="0 0 20 20"><path d="M10 3v9m-3-3 3 3 3-3M4 15h12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                </span>
                                            </a>
                                        <?php else: ?>
                                            <span class="download-link download-link--unavailable">
                                                <span class="download-link__copy">
                                                    <strong><?php echo htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                    <span><?php echo htmlspecialchars($file['label'] . ' · ' . $fileMeta, ENT_QUOTES, 'UTF-8'); ?></span>
                                                </span>
                                                <span class="download-link__icon" aria-hidden="true">—</span>
                                            </span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="section--tight section--surface">
            <div class="shell content-grid">
                <div>
                    <p class="eyebrow">Using the data</p>
                    <h2 class="section-title">Built for reproducible analysis</h2>
                    <p class="lede">Release archives contain genomic coordinates plus the prediction metadata used to filter and interpret CRM and TFBS records.</p>
                </div>
                <aside class="panel panel--tinted">
                    <h3>Cite PCRMs</h3>
                    <p>Ni P, Su Z. PCRMS: a database of predicted cis-regulatory modules and constituent transcription factor binding sites in genomes. <em>Database</em> (2022).</p>
                    <a class="text-link" href="https://doi.org/10.1093/database/baac024" target="_blank" rel="noopener noreferrer">Open publication <span aria-hidden="true">↗</span></a>
                </aside>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/partials/site_footer.php'; ?>
</body>
</html>
