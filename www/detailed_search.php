<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Search PCRMs by gene, genomic range, or transcription factor.">
    <title>Analysis tools — PCRMs</title>
    <link rel="icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/modern.css">
</head>
<body class="d-flex flex-column min-vh-100">
    <?php include __DIR__ . '/functional_header.php'; ?>

    <main class="site-main" id="main-content">
        <header class="page-hero">
            <div class="page-hero__inner shell">
                <p class="eyebrow">Targeted exploration</p>
                <h1 class="page-title">Analysis tools</h1>
                <p class="lede">Start with a gene, genomic neighborhood, or transcription factor and connect it to predicted regulatory modules and binding sites.</p>
                <div class="page-hero__meta">
                    <span class="meta-pill">Gene symbols &amp; stable IDs</span>
                    <span class="meta-pill">Configurable genomic ranges</span>
                    <span class="meta-pill">TF motif associations</span>
                </div>
            </div>
        </header>

        <section class="section" aria-labelledby="choose-analysis-title">
            <div class="shell">
                <div class="section-heading">
                    <div class="section-heading__copy">
                        <p class="eyebrow">Choose a workflow</p>
                        <h2 class="section-title" id="choose-analysis-title">What would you like to find?</h2>
                    </div>
                </div>

                <div class="analysis-grid">
                    <article class="analysis-card">
                        <span class="analysis-card__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="6" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m16 16 4 4M11 8v6M8 11h6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </span>
                        <h2>Closest CRM to a gene</h2>
                        <p>Use a gene symbol or stable identifier to retrieve its nearest predicted cis-regulatory module.</p>
                        <ul>
                            <li>Search across five organisms</li>
                            <li>Filter by CRM P-value threshold</li>
                            <li>Open detailed CRM records</li>
                        </ul>
                        <a class="button button--primary" href="DS_get_genes_closest_crm.php">Search closest CRM</a>
                    </article>

                    <article class="analysis-card">
                        <span class="analysis-card__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M4 8h16M4 16h16M7 5v6m10 2v6M11 6l2 2-2 2m2 4-2 2 2 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <h2>CRMs around a gene</h2>
                        <p>Search upstream, downstream, or across both sides of a gene within a defined genomic distance.</p>
                        <ul>
                            <li>0.5, 1, or 2 Mb search ranges</li>
                            <li>Strand-aware location filtering</li>
                            <li>Exportable tabular results</li>
                        </ul>
                        <a class="button button--primary" href="DS_get_crm_in_genes_range.php">Search a gene range</a>
                    </article>

                    <article class="analysis-card">
                        <span class="analysis-card__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M5 5c7 0 7 5 14 5M5 14c7 0 7 5 14 5M5 5c0 9 14 5 14 14M19 5c0 9-14 5-14 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </span>
                        <h2>TF binding sites</h2>
                        <p>Find predicted binding sites associated with a transcription factor across selected chromosomes.</p>
                        <ul>
                            <li>Search by TF symbol</li>
                            <li>Inspect motif identifiers and logos</li>
                            <li>Connect sites back to CRMs</li>
                        </ul>
                        <a class="button button--primary" href="DS_get_tf.php">Search TF binding sites</a>
                    </article>
                </div>
            </div>
        </section>

        <section class="section--tight section--surface">
            <div class="shell content-grid">
                <div>
                    <p class="eyebrow">Broader discovery</p>
                    <h2 class="section-title">Prefer to explore by chromosome?</h2>
                    <p class="lede">The database browser lets you filter full CRM maps by organism, chromosome, and statistical threshold.</p>
                </div>
                <div class="panel panel--tinted">
                    <h3>Browse the collection</h3>
                    <p>Start with an organism and move from a genome-wide table into individual CRM and TFBS records.</p>
                    <a class="button button--primary" href="browse_dataset.php" style="margin-top: 1rem">Browse CRM maps</a>
                </div>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/partials/site_footer.php'; ?>
</body>
</html>
