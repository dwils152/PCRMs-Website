<?php
session_start();
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/includes/catalog.php';

try {
    if (isset($_GET['crmId'], $_GET['chrome'], $_GET['genome_id'])) {
        $crmId = pcrms_validate_identifier($_GET['crmId'], 'CRM identifier');
        $genome_id = pcrms_validate_genome($_GET['genome_id']);
        $chrome = pcrms_validate_chromosomes($genome_id, [$_GET['chrome']])[0];

        $_SESSION['pcrms_tfbs_detail'] = [
            'crm_id' => $crmId,
            'chromosome' => $chrome,
            'genome_id' => $genome_id,
        ];
    } elseif (isset($_GET['crmId']) || isset($_GET['chrome']) || isset($_GET['genome_id'])) {
        throw new InvalidArgumentException('The CRM detail link is incomplete.');
    } elseif (isset($_SESSION['pcrms_tfbs_detail'])) {
        $crmId = pcrms_validate_identifier($_SESSION['pcrms_tfbs_detail']['crm_id'], 'CRM identifier');
        $genome_id = pcrms_validate_genome($_SESSION['pcrms_tfbs_detail']['genome_id']);
        $chrome = pcrms_validate_chromosomes($genome_id, [$_SESSION['pcrms_tfbs_detail']['chromosome']])[0];
    } else {
        throw new InvalidArgumentException('Choose a CRM before opening its TFBS records.');
    }
} catch (InvalidArgumentException $exception) {
    pcrms_invalid_request($exception->getMessage());
}
session_write_close();

$crmContextStatement = $mysqli->prepare(
    'SELECT 1 FROM crms WHERE crmID = ? AND Genome = ? AND Chromosome = ? LIMIT 1'
);
$crmContextStatement->bind_param('sis', $crmId, $genome_id, $chrome);
$crmContextStatement->execute();
if (!$crmContextStatement->get_result()->fetch_row()) {
    pcrms_invalid_request('That CRM does not belong to the selected organism and chromosome.');
}

$tfbsStatement = $mysqli->prepare(
    'SELECT tfbss.TFBS_Start, tfbss.TFBS_End, tfbss.umID, tfbss.Binding_Score,
            umotif_tfs_association_multiple.tfNames
     FROM tfbss
     INNER JOIN crms
        ON tfbss.crmID = crms.crmID
     INNER JOIN umotif_tfs_association_multiple
        ON tfbss.umID = umotif_tfs_association_multiple.umID
     WHERE tfbss.crmID = ?
       AND umotif_tfs_association_multiple.Genome = ?
       AND crms.Genome = ?
       AND crms.Chromosome = ?
     ORDER BY tfbss.TFBS_Start'
);
$tfbsStatement->bind_param('siis', $crmId, $genome_id, $genome_id, $chrome);
$tfbsStatement->execute();
$rs = $tfbsStatement->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>TFBSs for CRM <?php echo pcrms_h($crmId); ?> — PCRMs</title>
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
   <?php include __DIR__ . '/browse_header.php'; ?>
   <main id="main-content" class="site-main">

    <div class="jumbotron py-6 bg-info mb-1 jumbotron-fluid"></div>
	<header class="result-header shell">
		<p class="eyebrow">CRM detail</p>
		<h1>TFBS records for CRM <?php echo pcrms_h($crmId); ?></h1>
		<p><?php echo pcrms_h($PCRMS_GENOMES[$genome_id]['name']); ?> · <?php echo pcrms_h($chrome); ?></p>
	</header>

	    <div class="container">
	            <div class="row">
	                <div class="col">
	                    <div class="table-responsive">
	                    <table class="table table-striped table-hover table-sm" width="100%" id="tfbss_table">
	                    <caption class="sr-only">Predicted transcription factor binding sites within CRM <?php echo pcrms_h($crmId); ?>.</caption>
                    <thead>
                        <tr>

                            <th scope="col">Chromosome</th>
							<th scope="col">TFBS Start</th>
							<th scope="col">TFBS End</th>
                            <th scope="col">Umotif ID</th>
                            <th scope="col">TF Binding Score</th>
                            <th scope="col">Umotif logo</th>
                            <th scope="col">Matched TFs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
	                            while ($tfbs_row = $rs->fetch_assoc()) {
	                                $imagePath = pcrms_motif_path($genome_id, $tfbs_row['umID']);
	                                echo '<tr>';
	                                echo '<td>' . pcrms_h($chrome) . '</td>';
	                                echo '<td>' . pcrms_h($tfbs_row['TFBS_Start']) . '</td>';
	                                echo '<td>' . pcrms_h($tfbs_row['TFBS_End']) . '</td>';
	                                echo '<td>' . pcrms_h($tfbs_row['umID']) . '</td>';
	                                echo '<td>' . pcrms_h($tfbs_row['Binding_Score']) . '</td>';
	                                if ($imagePath) {
	                                    echo '<td><img src="' . pcrms_h($imagePath) . '" alt="Motif logo for ' . pcrms_h($tfbs_row['umID']) . '" height="30" width="150"></td>';
	                                } else {
	                                    echo '<td><span class="motif-unavailable">Logo unavailable</span></td>';
	                                }
	                                echo '<td>' . pcrms_h($tfbs_row['tfNames']) . '</td>';
	                                echo '</tr>';
                            }

                        ?>
                    
                    </tbody>

                    <tfoot>
                        <tr>
                            <th scope="col">Chromosome</th>
							<th scope="col">TFBS Start</th>
							<th scope="col">TFBS End</th>
                            <th scope="col">Umotif ID</th>
                            <th scope="col">TF Binding Score</th>
                            <th scope="col">Umotif logo</th>
                            <th scope="col">Matched TFs</th>
                        </tr>
                    </tfoot>


	                </table>
	                </div>
                
                </div>

                    
            </div>

    
    
    </div>

    <script>
        $(document).ready(function() {
            var tableX = $('#tfbss_table').DataTable( {
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
