<?php require_once __DIR__ . '/includes/catalog.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Search predicted transcription factor binding sites by TF symbol and chromosome.">
    <title>TF binding-site search — PCRMs</title>
    <link rel="icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/modern.css">
</head>
<body class="d-flex flex-column min-vh-100">
    <?php include __DIR__ . '/functional_header.php'; ?>

    <main class="site-main" id="main-content">
        <header class="page-hero">
            <div class="page-hero__inner shell">
                <p class="eyebrow">Transcription-factor analysis</p>
                <h1 class="page-title">Search TF binding sites</h1>
                <p class="lede">Find predicted binding sites associated with a transcription factor across one or more chromosomes.</p>
            </div>
        </header>

        <section class="section--tight">
            <div class="shell search-layout">
                <section class="search-card" aria-labelledby="tf-search-title">
                    <div class="search-card__heading">
                        <h2 id="tf-search-title">Define your TF query</h2>
                        <p>All fields are required.</p>
                    </div>

                    <form action="get_tf.php" method="post">
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
                                <label for="tf_id">Transcription factor symbol</label>
                                <input type="text" name="tf_id" id="tf_id" placeholder="For example: RUNX1 or AP1" autocomplete="off" required>
                            </div>

                            <div class="form-field form-field--full">
                                <label for="chr_id">Chromosomes</label>
                                <select multiple name="chr_id[]" id="chr_id" aria-describedby="chromosome-help" required>
                                    <option value="" disabled>Select an organism to narrow the list</option>
                                    <?php
                                    $allChromosomes = [];
                                    foreach ($PCRMS_GENOMES as $catalogGenome) {
                                        $allChromosomes = array_merge($allChromosomes, $catalogGenome['chromosomes']);
                                    }
                                    $allChromosomes = array_values(array_unique($allChromosomes));
                                    ?>
                                    <?php foreach ($allChromosomes as $chromosome): ?>
                                        <option value="<?php echo pcrms_h($chromosome); ?>"><?php echo pcrms_h($chromosome); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small id="chromosome-help">Use Ctrl (Windows/Linux) or Command (macOS) to select multiple chromosomes.</small>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a class="text-link" href="detailed_search.php">← All analysis tools</a>
                            <button class="button button--primary" type="submit">Search TF binding sites</button>
                        </div>
                    </form>
                </section>

                <aside class="panel panel--tinted" aria-labelledby="tf-guide-title">
                    <h2 id="tf-guide-title">Result fields</h2>
                    <ol class="step-list">
                        <li>CRM and chromosome location for each predicted site.</li>
                        <li>TFBS coordinates and binding score.</li>
                        <li>Associated motif identifier and sequence logo, where available.</li>
                    </ol>
                    <div class="info-note">Motif logos are currently available for the human, mouse, and <em>C. elegans</em> collections.</div>
                </aside>
            </div>
        </section>
    </main>

    <script>window.PCRMS_CATALOG = <?php echo json_encode($PCRMS_GENOMES, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;</script>
    <script src="dynamics.js"></script>
    <?php include __DIR__ . '/partials/site_footer.php'; ?>
</body>
</html>
