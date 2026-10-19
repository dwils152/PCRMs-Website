<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Find the predicted cis-regulatory module closest to a gene.">
    <title>Closest CRM to a gene — PCRMs</title>
    <link rel="icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/modern.css">
</head>
<body class="d-flex flex-column min-vh-100">
    <?php include __DIR__ . '/functional_header.php'; ?>

    <main class="site-main" id="main-content">
        <header class="page-hero">
            <div class="page-hero__inner shell">
                <p class="eyebrow">Gene-based analysis</p>
                <h1 class="page-title">Find the closest CRM</h1>
                <p class="lede">Search by gene symbol or stable gene identifier to retrieve the nearest predicted cis-regulatory module at your chosen significance threshold.</p>
            </div>
        </header>

        <section class="section--tight">
            <div class="shell search-layout">
                <section class="search-card" aria-labelledby="closest-search-title">
                    <div class="search-card__heading">
                        <h2 id="closest-search-title">Define your query</h2>
                        <p>All fields are required.</p>
                    </div>

                    <form action="get_genes_closest_crm.php" method="post">
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
                                <small>Use one exact symbol or identifier; matching is case-sensitive in some database configurations.</small>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a class="text-link" href="detailed_search.php">← All analysis tools</a>
                            <button class="button button--primary" type="submit">Search closest CRM</button>
                        </div>
                    </form>
                </section>

                <aside class="panel panel--tinted" aria-labelledby="closest-guide-title">
                    <h2 id="closest-guide-title">What you’ll get</h2>
                    <ol class="step-list">
                        <li>CRM identifier and chromosome coordinates.</li>
                        <li>Prediction score and P value.</li>
                        <li>A link to the detailed CRM and TFBS record.</li>
                    </ol>
                    <div class="info-note">For a wider neighborhood rather than only the closest module, use the <a href="DS_get_crm_in_genes_range.php">gene-range search</a>.</div>
                </aside>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/partials/site_footer.php'; ?>
</body>
</html>
