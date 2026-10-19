<?php
require_once __DIR__ . '/includes/catalog.php';

$genomeRows = [];
$databaseNotice = null;

try {
    require_once __DIR__ . '/connection.php';
    $genomeQuery = 'SELECT g.genomeID, g.genomeName, COUNT(c.crmID) AS crmCount
                    FROM genomes g
                    LEFT JOIN crms c ON c.Genome = g.genomeID
                    GROUP BY g.genomeID, g.genomeName
                    ORDER BY g.genomeID';
    $genomeResult = mysqli_query($connection, $genomeQuery);

    while ($genomeRow = mysqli_fetch_assoc($genomeResult)) {
        $genomeRows[] = $genomeRow;
    }
} catch (Throwable $exception) {
    $databaseNotice = 'Live database counts are temporarily unavailable.';
    error_log('PCRMs browse summary unavailable: ' . $exception->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Browse predicted cis-regulatory modules by organism, chromosome, and significance threshold.">
    <title>Browse CRM datasets — PCRMs</title>
    <link rel="icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/modern.css">
</head>
<body class="d-flex flex-column min-vh-100">
    <?php include __DIR__ . '/browse_header.php'; ?>

    <main class="site-main" id="main-content">
        <header class="page-hero">
            <div class="page-hero__inner shell">
                <p class="eyebrow">Explore the collection</p>
                <h1 class="page-title">Browse CRM datasets</h1>
                <p class="lede">Choose a reference genome, one or more chromosomes, and a significance threshold to inspect predicted cis-regulatory modules.</p>
                <div class="page-hero__meta" aria-label="Available filters">
                    <span class="meta-pill">5 organisms</span>
                    <span class="meta-pill">Chromosome-level filtering</span>
                    <span class="meta-pill">4 significance thresholds</span>
                </div>
            </div>
        </header>

        <section class="section--tight">
            <div class="shell">
                <article class="panel">
                    <div class="panel__header">
                        <div>
                            <h2>Database coverage</h2>
                            <p>Counts below are read directly from the connected PCRMs database.</p>
                        </div>
                        <span class="badge"><?php echo $databaseNotice ? 'Database unavailable' : 'Live database'; ?></span>
                    </div>

                    <div class="data-table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th scope="col">Organism</th>
                                    <th scope="col">Reference assembly</th>
                                    <th scope="col" class="data-table__number">Predicted CRMs</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($databaseNotice): ?>
                                    <tr><td colspan="3"><?php echo pcrms_h($databaseNotice); ?></td></tr>
                                <?php endif; ?>
                                <?php foreach ($genomeRows as $row): ?>
                                    <?php
                                    $genomeId = (int) $row['genomeID'];
                                    $genomeName = str_replace('_', ' ', $row['genomeName']);
                                    ?>
                                    <tr>
                                        <th scope="row">
                                            <span class="organism-name">
                                                <span class="organism-name__dot" aria-hidden="true"></span>
                                                <em><?php echo htmlspecialchars($genomeName, ENT_QUOTES, 'UTF-8'); ?></em>
                                            </span>
                                        </th>
                                        <td><?php echo pcrms_h(isset($PCRMS_GENOMES[$genomeId]) ? $PCRMS_GENOMES[$genomeId]['assembly'] : '—'); ?></td>
                                        <td class="data-table__number"><?php echo number_format((int) $row['crmCount']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>

                <div class="content-grid" style="margin-top: 1.4rem">
                    <section class="panel" aria-labelledby="quick-search-title">
                        <div class="panel__header">
                            <div>
                                <h2 id="quick-search-title">Filter the CRM map</h2>
                                <p>All three selections are required.</p>
                            </div>
                        </div>

                        <form action="get_crms.php" method="post">
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
                                    <label for="p_value">P-value threshold</label>
                                    <select name="p_value" id="p_value" required>
                                        <option value="" disabled selected>Select a threshold</option>
                                        <option value="0.05">0.05</option>
                                        <option value="0.01">0.01</option>
                                        <option value="0.000005">5 × 10⁻⁶</option>
                                        <option value="0.000001">1 × 10⁻⁶</option>
                                    </select>
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
                                <p>Results open as an interactive, database-paginated table.</p>
                                <button class="button button--primary" type="submit">
                                    Search CRMs
                                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                            </div>
                        </form>
                    </section>

                    <aside class="panel panel--tinted" aria-labelledby="search-guide-title">
                        <h2 id="search-guide-title">How to search</h2>
                        <ol class="step-list">
                            <li>Choose the organism and reference genome you want to inspect.</li>
                            <li>Select one or more chromosomes from the populated list.</li>
                            <li>Set the maximum P value for the CRM predictions.</li>
                        </ol>
                        <div class="info-note"><strong>Looking for a specific gene?</strong> The analysis tools can search by gene symbol, stable gene ID, or transcription factor.</div>
                        <a class="text-link" href="detailed_search.php" style="margin-top: 1rem">Open analysis tools <span aria-hidden="true">→</span></a>
                    </aside>
                </div>
            </div>
        </section>
    </main>

    <script>window.PCRMS_CATALOG = <?php echo json_encode($PCRMS_GENOMES, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;</script>
    <script src="dynamics.js"></script>
    <?php include __DIR__ . '/partials/site_footer.php'; ?>
</body>
</html>
