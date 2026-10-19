<?php

// Binding sites now sit on the CRM page; keep old links working.
require_once __DIR__ . '/includes/catalog.php';

try {
    if (!isset($_GET['crmId'], $_GET['genome_id'])) {
        throw new InvalidArgumentException('Open a CRM from a results table.');
    }
    $crmId = pcrms_validate_identifier($_GET['crmId'], 'CRM ID');
    $genomeId = pcrms_validate_genome($_GET['genome_id']);
} catch (InvalidArgumentException $exception) {
    pcrms_invalid_request($exception->getMessage());
}

header('Location: crm.php?' . http_build_query(['crmId' => $crmId, 'genome_id' => $genomeId], '', '&', PHP_QUERY_RFC3986), true, 301);
exit;
