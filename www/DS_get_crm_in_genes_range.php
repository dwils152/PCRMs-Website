<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Find predicted cis-regulatory modules within a selected range around a gene.">
    <title>CRMs around a gene — PCRMs</title>
    <link rel="icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/modern.css">
</head>
<body class="d-flex flex-column min-vh-100">
    <?php include __DIR__ . '/functional_header.php'; ?>

    <main class="site-main" id="main-content">
        <header class="page-hero">
            <div class="page-hero__inner shell">
                <p class="eyebrow">Gene-neighborhood analysis</p>
                <h1 class="page-title">Find CRMs around a gene</h1>
                <p class="lede">Search upstream, downstream, or across both sides of a gene within a strand-aware genomic range.</p>
            </div>
        </header>

        <section class="section--tight">
            <div class="shell search-layout">
                <section class="search-card" aria-labelledby="range-search-title">
                    <div class="search-card__heading">
                        <h2 id="range-search-title">Define the gene neighborhood</h2>
                        <p>All fields are required.</p>
                    </div>

                    <form action="get_crm_in_genes_range.php" method="post">
                        <div class="form-grid">
                            <div class="form-field">
                                <label for="genome_id">Organism</label>
                                <select name="genome_id" id="genome_id" required>
                                    <option value="" disabled selected>Select an organism</option>
                                    <option value="1">Homo sapiens</option>
                                    <option value="2">Mus musculus</option>
                                    <option value="3">Caenorhabditis elegans</option>
                                    <option value="4">Arabidopsis thaliana</option>
                                    <option value="5">Drosophila melanogaster</option>
                                </select>
                            </div>

                            <div class="form-field">
                                <label for="p_value">CRM P-value threshold</label>
                                <select name="p_value" id="p_value" required>
                                    <option value="" disabled selected>Select a threshold</option>
                                    <option value="0.05">0.05</option>
                                    <option value="0.01">0.01</option>
                                    <option value="0.000005">5 × 10⁻⁶</option>
                                    <option value="0.000001">1 × 10⁻⁶</option>
                                </select>
                            </div>

                            <div class="form-field form-field--full">
                                <label for="gene_id">Gene symbol or stable ID</label>
                                <input type="text" name="gene_id" id="gene_id" placeholder="For example: RUNX1 or ENSG00000159216" autocomplete="off" required>
                            </div>

                            <fieldset class="fieldset form-field--full">
                                <legend>Location relative to the gene</legend>
                                <div class="choice-row">
                                    <label class="choice"><input type="radio" name="location" value="upstream" required><span>Upstream</span></label>
                                    <label class="choice"><input type="radio" name="location" value="downstream"><span>Downstream</span></label>
                                    <label class="choice"><input type="radio" name="location" value="both" checked><span>Both sides</span></label>
                                </div>
                            </fieldset>

                            <fieldset class="fieldset form-field--full">
                                <legend>Maximum distance from the gene</legend>
                                <div class="choice-row">
                                    <label class="choice"><input type="radio" name="distance" value="50" required><span>0.5 Mb</span></label>
                                    <label class="choice"><input type="radio" name="distance" value="100" checked><span>1 Mb</span></label>
                                    <label class="choice"><input type="radio" name="distance" value="200"><span>2 Mb</span></label>
                                </div>
                            </fieldset>
                        </div>

                        <div class="form-actions">
                            <a class="text-link" href="detailed_search.php">← All analysis tools</a>
                            <button class="button button--primary" type="submit">Search gene range</button>
                        </div>
                    </form>
                </section>

                <aside class="panel panel--tinted" aria-labelledby="range-guide-title">
                    <h2 id="range-guide-title">How range works</h2>
                    <ol class="step-list">
                        <li>The gene’s annotated strand determines upstream and downstream.</li>
                        <li>The selected distance limits the genomic neighborhood.</li>
                        <li>Only CRMs passing the selected P-value threshold are returned.</li>
                    </ol>
                    <div class="info-note">Large ranges and permissive thresholds may return many records and take longer to load.</div>
                </aside>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/partials/site_footer.php'; ?>
</body>
</html>
