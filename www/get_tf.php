<?php
session_start();
require_once __DIR__ . '/includes/catalog.php';

try {
    $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

    if ($requestMethod === 'POST') {
        if (!isset($_POST['genome_id'], $_POST['tf_id'], $_POST['chr_id'])) {
            throw new InvalidArgumentException('Complete every field in the TF binding-site form.');
        }

        $genome_id = pcrms_validate_genome($_POST['genome_id']);
        $tf_id = pcrms_validate_identifier($_POST['tf_id'], 'transcription factor symbol');
        $chr_id = pcrms_validate_chromosomes($genome_id, $_POST['chr_id']);

        $queryToken = pcrms_store_query_state('pcrms_tf_queries', [
            'genome_id' => $genome_id,
            'tf_id' => $tf_id,
            'chr_id' => $chr_id,
        ]);

        header('Location: get_tf.php?query=' . rawurlencode($queryToken), true, 303);
        exit;
    } elseif (isset($_GET['query'])) {
        $queryToken = pcrms_validate_query_token($_GET['query']);
        if (!isset($_SESSION['pcrms_tf_queries'][$queryToken])) {
            throw new InvalidArgumentException('That TF result set has expired. Run the search again.');
        }

        $queryState = $_SESSION['pcrms_tf_queries'][$queryToken];
        if (!isset($queryState['genome_id'], $queryState['tf_id'], $queryState['chr_id'])) {
            throw new InvalidArgumentException('That TF result set is incomplete. Run the search again.');
        }
        $genome_id = pcrms_validate_genome($queryState['genome_id']);
        $tf_id = pcrms_validate_identifier($queryState['tf_id'], 'transcription factor symbol');
        $chr_id = pcrms_validate_chromosomes($genome_id, $queryState['chr_id']);
    } else {
        throw new InvalidArgumentException('Start from the TF binding-site search and define a query.');
    }
} catch (InvalidArgumentException $exception) {
    pcrms_invalid_request($exception->getMessage());
} catch (Throwable $exception) {
    error_log('PCRMs could not create a TF query: ' . $exception->getMessage());
    pcrms_invalid_request('The TF query could not be created. Please try again.');
}
session_write_close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title><?php echo pcrms_h($tf_id); ?> binding sites — PCRMs</title>
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
   <?php include __DIR__ . '/functional_header.php'; ?>
   <main id="main-content" class="site-main">


		<div class="sticky-top">
			<div class="jumbotron py-6 bg-info mb-1 jumbotron-fluid"></div>
		</div>
		<header class="result-header shell">
			<p class="eyebrow">TF search results</p>
			<h1>Binding sites for <?php echo pcrms_h($tf_id); ?></h1>
			<p><?php echo pcrms_h($PCRMS_GENOMES[$genome_id]['name']); ?> · <?php echo pcrms_h(implode(', ', $chr_id)); ?></p>
		</header>
	
	
	<div class="container">
		<div class="row">
			<div class="col">
				<div class="card border-dark p-2">
					<div class="row">
						<div class="col">
								<form action="get_tf.php" method="post">
								<div class="form-row align-items-top">
									<div class="col-3">
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
									<div class="col-4">
										<div class="form-group">
											<label for="tf_id">Input TF:</label>
											<input type="text" class="form-control" name="tf_id" id="tf_id" value="<?php echo pcrms_h($tf_id); ?>" required>
										</div>
									</div>

									<div class="col-4">
										<div class="form-group">
											<label for="chr_id">Select chromosomes:</label>
											<select multiple class="custom-select" name="chr_id[]" id="chr_id" required>
												<?php foreach ($chr_id as $selectedChromosome): ?>
													<option value="<?php echo pcrms_h($selectedChromosome); ?>" selected><?php echo pcrms_h($selectedChromosome); ?></option>
												<?php endforeach; ?>
											</select>
										</div>
									</div>

									<div class="col-1 mt-4 pt-2">
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
				<noscript><div class="info-note">JavaScript is required to page through this large result set. Return to the <a href="DS_get_tf.php">TF search</a> to refine the query.</div></noscript>
				<div class="data-note" id="results-table-error" role="alert" hidden>The TF results table is temporarily unavailable. Please refine the query or try again.</div>
				<div class="table-responsive">
				<table class="table table-striped table-hover table-sm" width="100%" id="tfs_table">
					<caption class="sr-only">Predicted binding sites for <?php echo pcrms_h($tf_id); ?> matching the selected organism and chromosomes.</caption>
					<thead>
						<tr>
							<th scope="col">CRM ID</th>
							<th scope="col">Chromosome</th>
							<th scope="col">TFBS Start</th>
							<th scope="col">TFBS End</th>
							<th scope="col">Umotif ID</th>
							<th scope="col">Binding TF</th>
							<th scope="col">Binding Score</th>
							<th scope="col">Umotif logo</th>
						</tr>
					</thead>
					<tbody></tbody>

					<tfoot>
						<tr>
							<th scope="col">CRM ID</th>
							<th scope="col">Chromosome</th>
							<th scope="col">TFBS Start</th>
							<th scope="col">TFBS End</th>
							<th scope="col">Umotif ID</th>
							<th scope="col">Binding TF</th>
							<th scope="col">Binding Score</th>
							<th scope="col">Umotif logo</th>

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
	            $('#tfs_table').on('error.dt', function () {
	                $('#results-table-error').prop('hidden', false);
	            }).on('xhr.dt', function (event, settings, json) {
	                if (json && !json.error) {
	                    $('#results-table-error').prop('hidden', true);
	                }
	            });
	            $('#tfs_table').DataTable( {
	            "processing": true,
	            "serverSide": true,
	            "searchDelay": 350,
	            "language": {"search": "Search CRM, chromosome, motif, or TF:"},
	            "ajax": {"url": "server_side_tf.php?query=<?php echo rawurlencode($queryToken); ?>", "type": "GET"},
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
	                {"data": "TFBS_Start", "searchable": false, "render": $.fn.dataTable.render.text()},
	                {"data": "TFBS_End", "searchable": false, "render": $.fn.dataTable.render.text()},
	                {"data": "umID", "render": $.fn.dataTable.render.text()},
	                {"data": "tfID", "render": $.fn.dataTable.render.text()},
	                {"data": "Binding_Score", "searchable": false, "render": $.fn.dataTable.render.text()},
	                {
	                    "data": "motifPath",
	                    "orderable": false,
	                    "searchable": false,
	                    "render": function (data, type, row) {
	                        if (type !== 'display') return data || '';
	                        if (!data) return '<span class="motif-unavailable">Logo unavailable</span>';
	                        return $('<img>')
	                            .attr({"src": data, "alt": "Motif logo for " + row.umID, "height": 30, "width": 150})
	                            .prop('outerHTML');
	                    }
	                }
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
