<?php

// Canonical release metadata used by the homepage and download catalog.
// These values reflect the existing 2022 source copy and should be updated
// once the authoritative release manifest is supplied.
$PCRMS_RELEASES = [
    [
        'common' => 'Human',
        'scientific' => 'Homo sapiens',
        'assembly' => 'GRCh38 / hg38',
        'version' => 2,
        'crm_count' => 1426947,
        'tfbs_count' => 95717222,
        'chip_seq_count' => 11348,
        'coverage' => '85.5%',
        'title' => 'Version 2 regulatory map',
        'facts' => ['1,426,947 CRMs', '95,717,222 TFBSs', '11,348 ChIP-seq datasets', '85.5% genome coverage'],
        'files' => [
            ['name' => 'Human_CRMCs_TFBSs_v2_hg38.bed.tar.gz', 'label' => 'Version 2 · CRM + TFBS coordinates', 'expected' => 'Expected size: 1.4 GB'],
            ['name' => 'Human_CRMCs_TFBSs_v1_hg38.bed.tar.gz', 'label' => 'Version 1 · legacy release', 'expected' => 'Expected size: 1.4 GB'],
        ],
    ],
    [
        'common' => 'Mouse',
        'scientific' => 'Mus musculus',
        'assembly' => 'mm10 / mm39',
        'version' => 2,
        'crm_count' => 912197,
        'tfbs_count' => 125113756,
        'chip_seq_count' => 9060,
        'coverage' => '77.9%',
        'title' => 'Version 2 regulatory map',
        'facts' => ['912,197 CRMs', '125,113,756 TFBSs', '9,060 ChIP-seq datasets', '77.9% genome coverage'],
        'files' => [
            ['name' => 'Mouse_CRMCs_TFBSs_v2_mm10.bed.tar.gz', 'label' => 'Version 2 · mm10 CRM + TFBS coordinates', 'expected' => 'Expected size: 1.9 GB'],
            ['name' => 'Mouse_CRMCs_v2_mm39.bed.tar.gz', 'label' => 'Version 2 · CRM coordinates lifted to mm39'],
            ['name' => 'Mouse_CRMCs_TFBSs_v1_mm10.bed.tar.gz', 'label' => 'Version 1 · legacy mm10 release', 'expected' => 'Expected size: 1.6 GB'],
        ],
    ],
    [
        'common' => 'Roundworm',
        'scientific' => 'Caenorhabditis elegans',
        'assembly' => 'ce10',
        'version' => 1,
        'title' => 'Version 1 CRM and TFBS map',
        'facts' => ['CRM coordinates', 'TFBS coordinates', 'Scores and P values'],
        'files' => [
            ['name' => 'ce10_v1.tar.gz', 'label' => 'Version 1 · complete release'],
        ],
    ],
    [
        'common' => 'Thale cress',
        'scientific' => 'Arabidopsis thaliana',
        'assembly' => 'TAIR10',
        'version' => 1,
        'title' => 'Version 1 CRM and TFBS map',
        'facts' => ['CRM coordinates', 'TFBS coordinates', 'Scores and P values'],
        'files' => [
            ['name' => 'tair10_v1.tar.gz', 'label' => 'Version 1 · complete release'],
        ],
    ],
    [
        'common' => 'Fruit fly',
        'scientific' => 'Drosophila melanogaster',
        'assembly' => 'dm3',
        'version' => 1,
        'title' => 'Version 1 CRM and TFBS map',
        'facts' => ['CRM coordinates', 'TFBS coordinates', 'Scores and P values'],
        'files' => [
            ['name' => 'dm3_v1.tar.gz', 'label' => 'Version 1 · complete release'],
        ],
    ],
];

$PCRMS_FEATURED_TOTALS = [
    'crm_count' => $PCRMS_RELEASES[0]['crm_count'] + $PCRMS_RELEASES[1]['crm_count'],
    'tfbs_count' => $PCRMS_RELEASES[0]['tfbs_count'] + $PCRMS_RELEASES[1]['tfbs_count'],
    'chip_seq_count' => $PCRMS_RELEASES[0]['chip_seq_count'] + $PCRMS_RELEASES[1]['chip_seq_count'],
];
