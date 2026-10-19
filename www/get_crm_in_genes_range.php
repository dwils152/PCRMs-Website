<?php
session_start();
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/includes/catalog.php';

try {
    $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

    if ($requestMethod === 'POST') {
        if (!isset($_POST['genome_id'], $_POST['gene_id'], $_POST['p_value'], $_POST['location'], $_POST['distance'])) {
            throw new InvalidArgumentException('Complete every field in the gene-range form.');
        }

        $genome_id = pcrms_validate_genome($_POST['genome_id']);
        $gene_id = pcrms_validate_identifier($_POST['gene_id'], 'gene symbol or ID');
        $p_value = pcrms_validate_p_value($_POST['p_value']);
        $location = pcrms_validate_enum($_POST['location'], $PCRMS_LOCATIONS, 'Select a valid location.');
        $distance = pcrms_validate_enum($_POST['distance'], $PCRMS_DISTANCES, 'Select a valid gene range.');

        $queryToken = pcrms_store_query_state('pcrms_range_queries', [
            'genome_id' => $genome_id,
            'gene_id' => $gene_id,
            'p_value' => $p_value,
            'location' => $location,
            'distance' => $distance,
        ]);

        header('Location: get_crm_in_genes_range.php?query=' . rawurlencode($queryToken), true, 303);
        exit;
    } elseif (isset($_GET['query'])) {
        $queryToken = pcrms_validate_query_token($_GET['query']);
        if (!isset($_SESSION['pcrms_range_queries'][$queryToken])) {
            throw new InvalidArgumentException('That gene-range result set has expired. Run the search again.');
        }

        $queryState = $_SESSION['pcrms_range_queries'][$queryToken];
        if (!isset($queryState['genome_id'], $queryState['gene_id'], $queryState['p_value'], $queryState['location'], $queryState['distance'])) {
            throw new InvalidArgumentException('That gene-range result set is incomplete. Run the search again.');
        }
        $genome_id = pcrms_validate_genome($queryState['genome_id']);
        $gene_id = pcrms_validate_identifier($queryState['gene_id'], 'gene symbol or ID');
        $p_value = pcrms_validate_p_value($queryState['p_value']);
        $location = pcrms_validate_enum($queryState['location'], $PCRMS_LOCATIONS, 'Select a valid location.');
        $distance = pcrms_validate_enum($queryState['distance'], $PCRMS_DISTANCES, 'Select a valid gene range.');
    } else {
        throw new InvalidArgumentException('Start from the gene-range search and define a query.');
    }
} catch (InvalidArgumentException $exception) {
    pcrms_invalid_request($exception->getMessage());
} catch (Throwable $exception) {
    error_log('PCRMs could not create a gene-range query: ' . $exception->getMessage());
    pcrms_invalid_request('The gene-range query could not be created. Please try again.');
}
session_write_close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <title>CRMs around <?php echo pcrms_h($gene_id); ?> — PCRMs</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  	<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.2/js/buttons.bootstrap4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.2/js/buttons.colVis.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.3/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.6.2/css/buttons.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/select/1.3.1/css/select.dataTables.min.css">
    <script src="https://cdn.datatables.net/select/1.3.1/js/dataTables.select.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="css/modern.css">
</head>

<body class="d-flex flex-column min-vh-100">
   <?php include __DIR__ . '/functional_header.php'; ?>
   <main id="main-content" class="site-main">
	  	<div class="sticky-top">
			<div class="jumbotron py-6 bg-info mb-1 jumbotron-fluid"></div>
		</div>
		<header class="result-header shell">
			<p class="eyebrow">Gene-range results</p>
			<h1>CRMs around <?php echo pcrms_h($gene_id); ?></h1>
			<p><?php echo pcrms_h($PCRMS_GENOMES[$genome_id]['name']); ?> · <?php echo pcrms_h($location); ?> · up to <?php echo pcrms_h($distance === '50' ? '0.5 Mb' : ($distance === '100' ? '1 Mb' : '2 Mb')); ?> · P ≤ <?php echo pcrms_h($p_value); ?></p>
		</header>

		<?php
			if ($location == "upstream"){

				$crms_sql  = "SELECT crms.crmID, crms.Chromosome, crms.Start_Pos, crms.End_Pos, crms.Score, crms.P_value FROM crms, genes_ranges 
				WHERE (genes_ranges.Symbol = ? OR genes_ranges.geneID = ?) AND 
				(genes_ranges.Genome = ? AND genes_ranges.Genome = crms.Genome AND genes_ranges.Chromosome = crms.Chromosome) 
				AND (IF (genes_ranges.Strand=\"+\", crms.Start_Pos >= genes_ranges.Start_Pos_{$distance} AND 
				crms.Start_Pos <= genes_ranges.Start_Pos, crms.End_Pos >= genes_ranges.End_Pos AND 
				crms.End_Pos <= genes_ranges.End_Pos_{$distance})) AND crms.P_value <= ?";

		} elseif ($location == "downstream"){

				$crms_sql  = "SELECT crms.crmID, crms.Chromosome, crms.Start_Pos, crms.End_Pos, crms.Score,crms.P_value FROM crms, genes_ranges 
				WHERE (genes_ranges.Symbol = ? OR genes_ranges.geneID = ?) AND 
				(genes_ranges.Genome = ? AND genes_ranges.Genome = crms.Genome AND genes_ranges.Chromosome = crms.Chromosome) 
				AND (IF (genes_ranges.Strand=\"+\", crms.Start_Pos >= genes_ranges.End_Pos AND 
				crms.Start_Pos <= genes_ranges.End_Pos_{$distance}, crms.End_Pos >= genes_ranges.Start_Pos_{$distance} AND 
				crms.End_Pos <= genes_ranges.Start_Pos)) AND crms.P_value <= ?";

		}else {

				$crms_sql  = "SELECT crms.crmID, crms.Chromosome, crms.Start_Pos, crms.End_Pos, crms.Score,crms.P_value FROM crms, genes_ranges 
				WHERE (genes_ranges.Symbol = ? OR genes_ranges.geneID = ?) AND 
				(genes_ranges.Genome = ? AND genes_ranges.Genome = crms.Genome AND genes_ranges.Chromosome = crms.Chromosome) 
				AND (crms.Start_Pos >= genes_ranges.Start_Pos_{$distance} AND crms.End_Pos <= genes_ranges.End_Pos_{$distance}) 
				AND crms.P_value <= ?";


		}

			$crmsStatement = $mysqli->prepare($crms_sql);
			$pValueFloat = (float) $p_value;
			$crmsStatement->bind_param('ssid', $gene_id, $gene_id, $genome_id, $pValueFloat);
			$crmsStatement->execute();
			$rs = $crmsStatement->get_result();
	?>  


	<div class="container">
		<div class="row">
			<div class="col">
				<div class="card border-dark py-2 px-4">
					<div class="row">
						<div class="col">							
							<form action="get_crm_in_genes_range.php" method="post">
								<div class="form-row align-items-start">
									<div class="col">
										<div class="form-group">
											<label for="genome_id">Select an organism:</label>
										<select class="form-control" name="genome_id" id="genome_id" required>
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
											<label for="gene_id">Input the gene symbol/ID of a gene:</label>
										<input type="text" class="form-control" name="gene_id" id="gene_id" value="<?php echo pcrms_h($gene_id); ?>" required>
										</div>
									
									</div>

									<div class="col">
										<div class="form-group">
											<label for="p_value">P value threshold:</label>
										<select class="custom-select" name="p_value" id="p_value" required>
											<option value="0.05"<?php echo $p_value === '0.05' ? ' selected' : ''; ?>>0.05</option>
											<option value="0.01"<?php echo $p_value === '0.01' ? ' selected' : ''; ?>>0.01</option>
											<option value="0.000005"<?php echo $p_value === '0.000005' ? ' selected' : ''; ?>>5 × 10⁻⁶</option>
											<option value="0.000001"<?php echo $p_value === '0.000001' ? ' selected' : ''; ?>>1 × 10⁻⁶</option>
											</select>
										</div>
									
									</div>
								
								</div>

								<div class="form-row align-items-baseline">
									<div class="col-6">
										<fieldset class="fieldset form-group">

										<legend class="form-group-label">Select the location of CRMs:</legend>

											<div class="form-check-inline">
												<label class="form-check-label" for="radio1">
											<input type="radio" class="form-check-input" id="radio1" name="location" value="upstream" required<?php echo $location === 'upstream' ? ' checked' : ''; ?>> Upstream
												</label>
											</div>

											<div class="form-check-inline">
												<label class="form-check-label" for="radio2">
											<input type="radio" class="form-check-input" id="radio2" name="location" value="downstream"<?php echo $location === 'downstream' ? ' checked' : ''; ?>> Downstream
												</label>
											</div>

											<div class="form-check-inline">
												<label class="form-check-label" for="radio3">
											<input type="radio" class="form-check-input" id="radio3" name="location" value="both"<?php echo $location === 'both' ? ' checked' : ''; ?>> Both
												</label>
											</div>

										</fieldset>
									
									</div>
									
									<div class="col-5">
										<fieldset class="fieldset form-group">

										<legend class="form-group-label">Range around the gene:</legend>

											<div class="form-check-inline">
												<label class="form-check-label" for="radio4">
											<input type="radio" class="form-check-input" id="radio4" name="distance" value="50" required<?php echo $distance === '50' ? ' checked' : ''; ?>> 0.5M
												</label>
											</div>

											<div class="form-check-inline">
												<label class="form-check-label" for="radio5">
											<input type="radio" class="form-check-input" id="radio5" name="distance" value="100"<?php echo $distance === '100' ? ' checked' : ''; ?>> 1M
												</label>
											</div>

											<div class="form-check-inline">
												<label class="form-check-label" for="radio6">
											<input type="radio" class="form-check-input" id="radio6" name="distance" value="200"<?php echo $distance === '200' ? ' checked' : ''; ?>> 2M
												</label>
											</div>

										</fieldset>
									
									</div>
									<div class="col">
										<button type="submit" class="btn btn-primary">Search</button>                        
									</div>
								
								</div>
                
                			</form>
						</div>
					</div>
				</div>
			</div>
		</div>

		<hr/>
		

		<div class="row mt-4">
			<div class="col">
				<div class="table-responsive">
				<table class="table table-striped table-hover table-sm" width="100%" id="crms_table">
					<caption class="sr-only">Predicted CRMs within the selected range around <?php echo pcrms_h($gene_id); ?>.</caption>
					<thead>
						<tr>
							<th scope="col">CRM ID</th>
							<th scope="col">Chromosome</th>
							<th scope="col">Start</th>
							<th scope="col">End</th>
							<th scope="col">Score</th>
							<th scope="col">P value</th>

						</tr>
					</thead>
					<tbody>
						<?php
								while ($row = $rs->fetch_assoc()) {
									$crmUrl = 'crm.php?crmId=' . rawurlencode($row['crmID']) . '&amp;genome_id=' . $genome_id;
									echo '<tr>';
									echo '<td><a href="' . $crmUrl . '">' . pcrms_h($row['crmID']) . '</a></td>';
									echo '<td>' . pcrms_h($row['Chromosome']) . '</td>';
									echo '<td>' . pcrms_h($row['Start_Pos']) . '</td>';
									echo '<td>' . pcrms_h($row['End_Pos']) . '</td>';
									echo '<td>' . pcrms_h($row['Score']) . '</td>';
									echo '<td>' . pcrms_h($row['P_value']) . '</td>';
									echo '</tr>';
							}

						?>					
					</tbody>


					<tfoot>
                	<tr>
						<th scope="col">CRM ID</th>
						<th scope="col">Chromosome</th>
						<th scope="col">Start</th>
						<th scope="col">End</th>
						<th scope="col">Score</th>
						<th scope="col">P value</th>

                	</tr>
               		</tfoot>

					   
				</table>
				</div>
			</div>
		</div>
	</div>

	<script>
        $(document).ready(function() {
            var tableX = $('#crms_table').DataTable( {
            dom: "<'row'<'col-4'l><'col-5'B><'col-2'f>>" + 
                "<'row'<'col-12'tr>>" + 
                "<'row'<'col-6'i><'col-6'p>>",
	            "lengthMenu": [[25, 50, 100, 250], [25, 50, 100, 250]],
	            "pageLength": 50,
	            "scrollY": "60vh",
	            "scrollX": true,
            "scrollCollapse": true,

            buttons: [  'copy', 
                    'csv',
                    'excel', 
                    'colvis' ],
            select: true,

        } );

    // tableX.button(0).nodes().css({"background-color": "#0275d8"});
    // tableX.button(1).nodes().css({"background-color": "#0275d8"});
    // tableX.button(2).nodes().css({"background-color": "#0275d8"});
    // tableX.button(3).nodes().css({"background-color": "#0275d8"});

        } );   
	</script>
	
	</main>
	<?php include __DIR__ . '/partials/site_footer.php'; ?>


</body>

</html> 
