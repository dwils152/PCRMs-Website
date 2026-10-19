<?php
session_start();
require_once __DIR__ . '/includes/catalog.php';

try {
    $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

    if ($requestMethod === 'POST') {
        if (!isset($_POST['genome_id'], $_POST['chr_id'], $_POST['p_value'])) {
            throw new InvalidArgumentException('Complete every field in the CRM browser form.');
        }

        $genome_id = pcrms_validate_genome($_POST['genome_id']);
        $chr_id = pcrms_validate_chromosomes($genome_id, $_POST['chr_id']);
        $p_value = pcrms_validate_p_value($_POST['p_value']);

        $queryToken = pcrms_store_query_state('pcrms_browse_queries', [
            'genome_id' => $genome_id,
            'chr_id' => $chr_id,
            'p_value' => $p_value,
        ]);

        header('Location: get_crms.php?query=' . rawurlencode($queryToken), true, 303);
        exit;
    } elseif (isset($_GET['query'])) {
        $queryToken = pcrms_validate_query_token($_GET['query']);
        if (!isset($_SESSION['pcrms_browse_queries'][$queryToken])) {
            throw new InvalidArgumentException('That browse result set has expired. Run the search again.');
        }

        $queryState = $_SESSION['pcrms_browse_queries'][$queryToken];
        if (!isset($queryState['genome_id'], $queryState['chr_id'], $queryState['p_value'])) {
            throw new InvalidArgumentException('That browse result set is incomplete. Run the search again.');
        }
        $genome_id = pcrms_validate_genome($queryState['genome_id']);
        $chr_id = pcrms_validate_chromosomes($genome_id, $queryState['chr_id']);
        $p_value = pcrms_validate_p_value($queryState['p_value']);
    } else {
        throw new InvalidArgumentException('Start from the database browser and define a CRM query.');
    }
} catch (InvalidArgumentException $exception) {
    pcrms_invalid_request($exception->getMessage());
} catch (Throwable $exception) {
    error_log('PCRMs could not create a browse query: ' . $exception->getMessage());
    pcrms_invalid_request('The browse query could not be created. Please try again.');
}
session_write_close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <title>CRM browser results — PCRMs</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <script src="https://code.jquery.com/jquery-3.5.1.js"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.3/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap4.min.css">

    <link rel="stylesheet" href="css/modern.css">
</head>

<body class="d-flex flex-column min-vh-100">
   <?php include __DIR__ . '/browse_header.php'; ?>
   <main id="main-content" class="site-main">

    <div class="sticky-top">
        <div class="jumbotron py-6 bg-info mb-1 jumbotron-fluid"></div>
    </div>
    <header class="result-header shell">
        <p class="eyebrow">Browse results</p>
        <h1>Predicted CRM records</h1>
        <p><?php echo pcrms_h($PCRMS_GENOMES[$genome_id]['name']); ?> · <?php echo pcrms_h(implode(', ', $chr_id)); ?> · P ≤ <?php echo pcrms_h($p_value); ?></p>
    </header>
    <div class="container py-2 mt-2">

        <div class="row">
            <div class="col">
                <div class="card border-dark p-2">
                    <div class="row">
                        <div class="col">
                        <form action="get_crms.php" method="POST">
                            <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                        <label for="genome_id">Select an organism:</label>
                                            <select name="genome_id" class="form-control" id="genome_id" required>
                                                <option value="" disabled>Select an organism</option>
                                                <option value="1"<?php echo $genome_id === 1 ? ' selected' : ''; ?>>Homo sapiens</option>
                                                <option value="2"<?php echo $genome_id === 2 ? ' selected' : ''; ?>>Mus musculus</option>
                                                <option value="3"<?php echo $genome_id === 3 ? ' selected' : ''; ?>>Caenorhabditis elegans</option>
						<option value="4"<?php echo $genome_id === 4 ? ' selected' : ''; ?>>Arabidopsis thaliana</option>
						<option value="5"<?php echo $genome_id === 5 ? ' selected' : ''; ?>>Drosophila melanogaster</option>
                                            </select>
                                    </div>
                                </div>

                                <div class="col">
                                    <div class="form-group">
                                        <label for="chr_id">Select chromosomes:</label>
                                        <select multiple class="custom-select" name="chr_id[]" id="chr_id" required>
                                            <?php foreach ($chr_id as $selectedChromosome): ?>
                                                <option value="<?php echo pcrms_h($selectedChromosome); ?>" selected><?php echo pcrms_h($selectedChromosome); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="col">
                                    <div class="form-group">
                                        <label for="p_value">P value threshold:</label>
                                        <select class="custom-select" name="p_value" id="p_value" required>
                                            <option value="" disabled>Select a P value</option>
                                            <option value="0.05"<?php echo $p_value === '0.05' ? ' selected' : ''; ?>>0.05</option>
                                            <option value="0.01"<?php echo $p_value === '0.01' ? ' selected' : ''; ?>>0.01</option>
                                            <option value="0.000005"<?php echo $p_value === '0.000005' ? ' selected' : ''; ?>>5 × 10⁻⁶</option>
                                            <option value="0.000001"<?php echo $p_value === '0.000001' ? ' selected' : ''; ?>>1 × 10⁻⁶</option>

                                        </select>
                                    </div>
                                    <button class="btn btn-primary" type="submit">Search</button>
                                </div>
                                
                            </div>
                        </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr/>


        <div class="row">
            <div class="col">
            <noscript><div class="info-note">JavaScript is required to page through this large result set. You can refine the query above or use the genome-wide files on the <a href="download_data.php">downloads page</a>.</div></noscript>
            <div class="data-note" id="results-table-error" role="alert" hidden>The results table is temporarily unavailable. Please refine the query or try again.</div>
            <div class="table-responsive">
            <table class="table table-striped table-hover table-sm" width="100%" id="crms_table">
                <caption class="sr-only">Predicted CRM records matching the selected organism, chromosomes, and P-value threshold.</caption>
                <thead>
                    <tr>
                        <th scope="col">CRM ID</th>
                        <th scope="col">Chromosome</th>
                        <th scope="col">Start</th>
                        <th scope="col">End</th>
                        <th scope="col">Closest Gene</th>
                        <th scope="col">Score</th>
                        <th scope="col">P value</th>
                    </tr>
                </thead>
                <tbody></tbody>


                <tfoot>
                <tr>
                    <th scope="col">CRM ID</th>
                    <th scope="col">Chromosome</th>
                    <th scope="col">Start</th>
                    <th scope="col">End</th>
                    <th scope="col">Closest Gene</th>
                    <th scope="col">Score</th>
                    <th scope="col">P value</th>

                </tr>
                </tfoot>
            </table>
            </div>
            </div>
        </div>

    </div>

    <script>window.PCRMS_CATALOG = <?php echo json_encode($PCRMS_GENOMES, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;</script>
    <script src="dynamics.js"></script>
    <script>

        $(document).ready(function() {
            $.fn.dataTable.ext.errMode = 'none';
            $('#crms_table').on('error.dt', function () {
                $('#results-table-error').prop('hidden', false);
            }).on('xhr.dt', function (event, settings, json) {
                if (json && !json.error) {
                    $('#results-table-error').prop('hidden', true);
                }
            });
            $('#crms_table').DataTable( {
            "processing": true,
            "serverSide": true,
            "searchDelay": 350,
            "language": {"search": "Search IDs, chromosomes, or genes:"},
            "ajax": {
                "url": "server_side_process.php?query=<?php echo rawurlencode($queryToken); ?>",
                "type": "GET"
            },
            "columns": [
                {
                    "data": "crmID",
                    "render": function (data, type) {
                        if (type !== 'display') return data;
                        return $('<a>')
                            .attr('href', 'crm.php?crmId=' + encodeURIComponent(data) + '&genome_id=<?php echo $genome_id; ?>')
                            .text(data)
                            .prop('outerHTML');
                    }
                },
                {"data": "Chromosome", "render": $.fn.dataTable.render.text()},
                {"data": "Start_Pos", "searchable": false, "render": $.fn.dataTable.render.text()},
                {"data": "End_Pos", "searchable": false, "render": $.fn.dataTable.render.text()},
                {"data": "Genes_Symbol", "render": $.fn.dataTable.render.text()},
                {"data": "Score", "searchable": false, "render": $.fn.dataTable.render.text()},
                {"data": "P_value", "searchable": false, "render": $.fn.dataTable.render.text()}
            ],
            "dom": "<'row'<'col-6'l><'col-6'f>>" +
                "<'row'<'col-12'tr>>" + 
                "<'row'<'col-6'i><'col-6'p>>",
            "lengthMenu": [[25, 50, 100, 250], [25, 50, 100, 250]],
            "pageLength": 50,
            "scrollX": true,
            "orderMulti": false,
            "paging": true
        } );
        } );
    </script>

    </main>
    <?php include __DIR__ . '/partials/site_footer.php'; ?>



    
</body>
</html>
