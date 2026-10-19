<?php
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/includes/catalog.php';

try {
    if (!isset($_GET['crmId'], $_GET['genome_id'])) {
        throw new InvalidArgumentException('Choose a CRM from a search-results table.');
    }

    $crmId = pcrms_validate_identifier($_GET['crmId'], 'CRM identifier');
    $genome_id = pcrms_validate_genome($_GET['genome_id']);
} catch (InvalidArgumentException $exception) {
    pcrms_invalid_request($exception->getMessage());
}

$crmQuery = "SELECT crms.crmID, crms.Chromosome, crms.Start_Pos, crms.End_Pos, crms.Score,
             crms.P_value, crms.Distance, crms_genes.Genes_Symbol AS Symbol,
             MIN(genes.Start_Pos) AS geneStart, MAX(genes.End_Pos) AS geneEnd
             FROM crms
             INNER JOIN crms_genes_association ON crms.crmID = crms_genes_association.crmID
             INNER JOIN genes ON crms_genes_association.geneID = genes.geneID
                AND genes.Genome = crms.Genome
             INNER JOIN crms_genes ON crms.crmID = crms_genes.crmID
                AND crms_genes.Genome = crms.Genome
             WHERE crms.crmID = ? AND crms.Genome = ?
             GROUP BY crms.crmID, crms.Chromosome, crms.Start_Pos, crms.End_Pos,
                      crms.Score, crms.P_value, crms.Distance, crms_genes.Genes_Symbol";
$crmStatement = $mysqli->prepare($crmQuery);
$crmStatement->bind_param('si', $crmId, $genome_id);
$crmStatement->execute();
$row = $crmStatement->get_result()->fetch_assoc();

if (!$row) {
    pcrms_invalid_request('No CRM matched that identifier and organism.');
}

$positions = [(int) $row['Start_Pos'], (int) $row['End_Pos'], (int) $row['geneStart'], (int) $row['geneEnd']];
$markerSet = $row['Start_Pos'] . ':' . $row['End_Pos'] . '|' . $row['crmID'] . '|#8B0000';
$geneLength = abs((int) $row['geneEnd'] - (int) $row['geneStart']);
$crmLength = abs((int) $row['End_Pos'] - (int) $row['Start_Pos']);
$padding = 2 * max($geneLength, $crmLength, 1);
$genomeView = (min($positions) - $padding) . ':' . (max($positions) + $padding);
$chromosome = $row['Chromosome'];

$chromosomeStatement = $mysqli->prepare(
    'SELECT ncbiName FROM chromosomes WHERE genomeID = ? AND chromosomeName = ?'
);
$chromosomeStatement->bind_param('is', $genome_id, $chromosome);
$chromosomeStatement->execute();
$chromosomeRow = $chromosomeStatement->get_result()->fetch_assoc();

if (!$chromosomeRow) {
    pcrms_invalid_request('The NCBI reference sequence for this CRM is unavailable.');
}

$ncbiChromosome = $chromosomeRow['ncbiName'];
$tfbsUrl = 'tfbs.php?crmId=' . rawurlencode($crmId)
    . '&chrome=' . rawurlencode($chromosome)
    . '&genome_id=' . $genome_id;
$viewerUrl = '?embedded=true&appname=pcrms&id=' . rawurlencode($ncbiChromosome)
    . '&v=' . rawurlencode($genomeView)
    . '&mk=' . rawurlencode($markerSet);
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <title>CRM <?php echo pcrms_h($crmId); ?> — PCRMs</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <script type="text/javascript" src="https://www.ncbi.nlm.nih.gov/projects/sviewer/js/sviewer.js"></script>
 <link rel="stylesheet" type="text/css" href="https://www.ncbi.nlm.nih.gov/projects/sviewer/css/sv-cleanup.css">
    <link rel="stylesheet" href="css/modern.css">
</head>





<body class="d-flex flex-column min-vh-100">
	<?php include __DIR__ . '/browse_header.php'; ?>
	<main id="main-content" class="site-main">

	<div class="jumbotron py-6 bg-info mb-1 jumbotron-fluid py-3"></div>
	<header class="result-header shell">
		<p class="eyebrow">CRM detail</p>
		<h1>CRM <?php echo pcrms_h($crmId); ?></h1>
		<p><?php echo pcrms_h($PCRMS_GENOMES[$genome_id]['name']); ?> · <?php echo pcrms_h($chromosome . ':' . $row['Start_Pos'] . '-' . $row['End_Pos']); ?></p>
	</header>
	<div class="container-fluid">
		<div class="row px-2">
			<div class="col-3 pr-1">

				<div class="row">
					<div class="col">
					<div class="card">
					<div class="card-header text-primary">
						<h5>CRM ID: <a href="<?php echo pcrms_h($tfbsUrl); ?>"><?php echo pcrms_h($crmId); ?></a></h5>
					</div>
					<div class="card-body">
						<h5 class="card-title">Information</h5>
						<ul>
							<li>CRM coordinate:<?php
								echo pcrms_h($chromosome . ':' . $row['Start_Pos'] . '-' . $row['End_Pos']);
							?></li>
							<li>CRM score: <?php echo pcrms_h($row['Score']); ?></li>
							<li>CRM P value: <?php echo pcrms_h($row['P_value']); ?></li>
							<li>Closest gene(s): <?php echo pcrms_h($row['Symbol']); ?></li>
						</ul>
					
					</div>
				</div>
					
					
					
					</div>
				
				</div>

			</div>

			<div class="col-9">
				<div id="mySeqViewer1" class="SeqViewerApp" data-autoload>
				 <a href="<?php echo pcrms_h($viewerUrl); ?>"></a>
	  			 </div>	
			</div>
		</div>
	</div>

	</main>
	<?php include __DIR__ . '/partials/site_footer.php'; ?>


</body>
</html>
