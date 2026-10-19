<?php
$visitorCount = null;
require_once __DIR__ . '/includes/releases.php';

try {
    require_once __DIR__ . '/connection.php';
    $trackingEnabled = filter_var(getenv('PCRMS_ENABLE_VISITOR_TRACKING'), FILTER_VALIDATE_BOOLEAN);
    if ($trackingEnabled) {
        require_once __DIR__ . '/tracker.php';
    }
    $visitorResult = mysqli_query($connection, 'SELECT COUNT(*) FROM visitors');
    if ($visitorResult) {
        $visitorRow = mysqli_fetch_row($visitorResult);
        $visitorCount = isset($visitorRow[0]) ? (int) $visitorRow[0] : null;
    }
} catch (Throwable $exception) {
    error_log('PCRMs visitor tracking unavailable: ' . $exception->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="PCRMs is a database of predicted cis-regulatory modules and transcription factor binding sites across human and model-organism genomes.">
    <title>PCRMs — Predicted cis-Regulatory Modules</title>
    <link rel="icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/modern.css">
</head>
<body class="d-flex flex-column min-vh-100">
    <?php include __DIR__ . '/home_header.php'; ?>

    <main class="site-main" id="main-content">
        <section class="home-hero">
            <div class="home-hero__inner shell">
                <div class="home-hero__copy">
                    <p class="release-pill">Human and mouse maps · Version 2</p>
                    <p class="eyebrow">Open regulatory genomics resource</p>
                    <h1 class="display-title">Mapping the genome’s regulatory landscape.</h1>
                    <p class="lede">Explore de novo predictions of cis-regulatory modules (CRMs) and their constituent transcription factor binding sites across human and model-organism genomes.</p>
                    <div class="button-row">
                        <a class="button button--accent" href="browse_dataset.php">
                            Browse the database
                            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                        <a class="button button--secondary" href="detailed_search.php">Open analysis tools</a>
                    </div>
                </div>

                <figure class="genome-map" aria-labelledby="genome-map-caption">
                    <figcaption class="sr-only" id="genome-map-caption">Abstract view of predicted regulatory regions distributed across five chromosomes.</figcaption>
                    <div class="genome-map__top">
                        <span>Genome overview</span>
                        <span class="genome-map__status">Prediction map</span>
                    </div>
                    <div class="genome-track">
                        <span class="genome-track__label">chr01</span>
                        <span class="genome-track__line">
                            <i class="genome-track__segment" style="left: 4%; width: 18%"></i>
                            <i class="genome-track__segment genome-track__segment--muted" style="left: 30%; width: 10%"></i>
                            <i class="genome-track__segment" style="left: 48%; width: 26%"></i>
                            <i class="genome-track__segment genome-track__segment--muted" style="left: 80%; width: 14%"></i>
                        </span>
                    </div>
                    <div class="genome-track">
                        <span class="genome-track__label">chr02</span>
                        <span class="genome-track__line">
                            <i class="genome-track__segment genome-track__segment--muted" style="left: 2%; width: 12%"></i>
                            <i class="genome-track__segment" style="left: 22%; width: 28%"></i>
                            <i class="genome-track__segment genome-track__segment--muted" style="left: 59%; width: 17%"></i>
                            <i class="genome-track__segment" style="left: 85%; width: 10%"></i>
                        </span>
                    </div>
                    <div class="genome-track">
                        <span class="genome-track__label">chr03</span>
                        <span class="genome-track__line">
                            <i class="genome-track__segment" style="left: 8%; width: 24%"></i>
                            <i class="genome-track__segment genome-track__segment--muted" style="left: 39%; width: 8%"></i>
                            <i class="genome-track__segment" style="left: 55%; width: 35%"></i>
                        </span>
                    </div>
                    <div class="genome-track">
                        <span class="genome-track__label">chr04</span>
                        <span class="genome-track__line">
                            <i class="genome-track__segment genome-track__segment--muted" style="left: 3%; width: 21%"></i>
                            <i class="genome-track__segment" style="left: 34%; width: 14%"></i>
                            <i class="genome-track__segment" style="left: 63%; width: 29%"></i>
                        </span>
                    </div>
                    <div class="genome-track">
                        <span class="genome-track__label">chr05</span>
                        <span class="genome-track__line">
                            <i class="genome-track__segment" style="left: 1%; width: 10%"></i>
                            <i class="genome-track__segment genome-track__segment--muted" style="left: 19%; width: 26%"></i>
                            <i class="genome-track__segment" style="left: 52%; width: 15%"></i>
                            <i class="genome-track__segment genome-track__segment--muted" style="left: 74%; width: 20%"></i>
                        </span>
                    </div>
                    <div class="genome-map__legend" aria-hidden="true">
                        <span><i></i> CRM region</span>
                        <span><i></i> TFBS cluster</span>
                    </div>
                </figure>
            </div>
        </section>

        <div class="stats-strip shell" role="list" aria-label="Database highlights">
            <div class="stat" role="listitem"><strong>5</strong><span>Reference genomes</span></div>
            <div class="stat" role="listitem"><strong><?php echo number_format($PCRMS_FEATURED_TOTALS['crm_count'] / 1000000, 1); ?>M+</strong><span>Human + mouse CRMs</span></div>
            <div class="stat" role="listitem"><strong><?php echo floor($PCRMS_FEATURED_TOTALS['tfbs_count'] / 1000000); ?>M+</strong><span>TFBS predictions</span></div>
            <div class="stat" role="listitem"><strong><?php echo number_format($PCRMS_FEATURED_TOTALS['chip_seq_count']); ?></strong><span>ChIP-seq datasets</span></div>
        </div>

        <section class="section" aria-labelledby="explore-title">
            <div class="shell">
                <div class="section-heading">
                    <div class="section-heading__copy">
                        <p class="eyebrow">Start exploring</p>
                        <h2 class="section-title" id="explore-title">From sequence to regulatory context</h2>
                        <p class="lede">Move from broad genome maps to the regulatory evidence around a gene or transcription factor.</p>
                    </div>
                </div>

                <div class="task-grid">
                    <a class="task-card" href="browse_dataset.php">
                        <span class="task-card__number">01</span>
                        <h3>Browse CRM maps</h3>
                        <p>Filter predicted modules by organism, chromosome, and significance threshold.</p>
                        <span class="task-card__arrow" aria-hidden="true">→</span>
                    </a>
                    <a class="task-card" href="DS_get_genes_closest_crm.php">
                        <span class="task-card__number">02</span>
                        <h3>Search by gene</h3>
                        <p>Find the predicted CRM closest to a gene symbol or stable identifier.</p>
                        <span class="task-card__arrow" aria-hidden="true">→</span>
                    </a>
                    <a class="task-card" href="DS_get_tf.php">
                        <span class="task-card__number">03</span>
                        <h3>Search by TF</h3>
                        <p>Locate predicted binding sites associated with a transcription factor.</p>
                        <span class="task-card__arrow" aria-hidden="true">→</span>
                    </a>
                    <a class="task-card" href="download_data.php">
                        <span class="task-card__number">04</span>
                        <h3>Download releases</h3>
                        <p>Access genome-wide CRM and TFBS coordinate files for downstream analysis.</p>
                        <span class="task-card__arrow" aria-hidden="true">→</span>
                    </a>
                </div>
            </div>
        </section>

        <section class="section section--surface" aria-labelledby="updates-title">
            <div class="shell updates-layout">
                <div>
                    <p class="eyebrow">Release history</p>
                    <h2 class="section-title" id="updates-title">What’s in the current collection</h2>
                    <p class="lede">Version 2 expanded the human and mouse maps with substantially more public TF ChIP-seq evidence.</p>
                    <a class="text-link" href="download_data.php">View all downloadable releases <span aria-hidden="true">→</span></a>
                </div>

                <ol class="update-list">
                    <li class="update-item">
                        <time datetime="2022-06-03">03 Jun 2022</time>
                        <div>
                            <h3>Version 2 human and mouse regulatory maps released</h3>
                            <p>The updated collection includes <?php echo number_format($PCRMS_RELEASES[0]['crm_count']); ?> human CRMs and <?php echo number_format($PCRMS_RELEASES[1]['crm_count']); ?> mouse CRMs, with mouse coordinates also lifted to GRCm39/mm39.</p>
                        </div>
                    </li>
                    <li class="update-item">
                        <time datetime="2022-05-30">30 May 2022</time>
                        <div>
                            <h3>Expanded mouse CRM map submitted</h3>
                            <p>The version 2 mouse resource describes predicted regulatory modules across 80% of the genome.</p>
                        </div>
                    </li>
                    <li class="update-item">
                        <time datetime="2022-04-22">22 Apr 2022</time>
                        <div>
                            <h3>PCRMs database paper published</h3>
                            <p>The database and its genome-scale query workflows were published in <em>Database</em>, Oxford Academic.</p>
                        </div>
                    </li>
                </ol>
            </div>
        </section>

        <section class="section">
            <div class="shell">
                <article class="publication-card">
                    <p class="eyebrow">Methods &amp; citation</p>
                    <h2>Predictions powered by dePCRM2</h2>
                    <p>PCRMs integrates available TF ChIP-seq datasets across cell and tissue types to predict cis-regulatory modules and their constituent binding sites de novo.</p>
                    <div class="button-row">
                        <a class="button button--accent" href="https://doi.org/10.1093/database/baac024" target="_blank" rel="noopener noreferrer">Read the database paper</a>
                        <a class="button button--secondary" href="https://doi.org/10.1093/nargab/lqab052" target="_blank" rel="noopener noreferrer">Explore the method</a>
                    </div>
                </article>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/partials/site_footer.php'; ?>
</body>
</html>
