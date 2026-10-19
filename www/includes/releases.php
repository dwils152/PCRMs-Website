<?php

// Release metadata for the home and download pages, in display order.
// Counts reflect the published version 2 maps (Database, 2022). The version 1
// genomes have no published counts yet: set 'crm_count' once they are confirmed.
// 'expected' sizes are shown for archives that are not on this server.
$PCRMS_RELEASES = [
    [
        'genome_id' => 1,
        'version' => 2,
        'crm_count' => 1426947,
        'facts' => [['1,426,947', 'CRMs'], ['95,717,222', 'TFBSs'], ['11,348', 'ChIP-seq datasets'], ['85.5%', 'of the genome']],
        'files' => [
            ['name' => 'Human_CRMCs_TFBSs_v2_hg38.bed.tar.gz', 'label' => 'Version 2 · CRMs and TFBSs · hg38', 'expected' => '1.4 GB'],
            ['name' => 'Human_CRMCs_TFBSs_v1_hg38.bed.tar.gz', 'label' => 'Version 1 · CRMs and TFBSs · hg38 (earlier release)', 'expected' => '1.4 GB'],
        ],
    ],
    [
        'genome_id' => 2,
        'version' => 2,
        'crm_count' => 912197,
        'facts' => [['912,197', 'CRMs'], ['125,113,756', 'TFBSs'], ['9,060', 'ChIP-seq datasets'], ['77.9%', 'of the genome']],
        'files' => [
            ['name' => 'Mouse_CRMCs_TFBSs_v2_mm10.bed.tar.gz', 'label' => 'Version 2 · CRMs and TFBSs · mm10', 'expected' => '1.9 GB'],
            ['name' => 'Mouse_CRMCs_v2_mm39.bed.tar.gz', 'label' => 'Version 2 · CRMs only, lifted over to mm39'],
            ['name' => 'Mouse_CRMCs_TFBSs_v1_mm10.bed.tar.gz', 'label' => 'Version 1 · CRMs and TFBSs · mm10 (earlier release)', 'expected' => '1.6 GB'],
        ],
    ],
    [
        'genome_id' => 5,
        'version' => 1,
        'crm_count' => null,
        'facts' => [],
        'files' => [
            ['name' => 'dm3_v1.tar.gz', 'label' => 'Version 1 · complete release · dm3'],
        ],
    ],
    [
        'genome_id' => 3,
        'version' => 1,
        'crm_count' => null,
        'facts' => [],
        'files' => [
            ['name' => 'ce10_v1.tar.gz', 'label' => 'Version 1 · complete release · ce10'],
        ],
    ],
    [
        'genome_id' => 4,
        'version' => 1,
        'crm_count' => null,
        'facts' => [],
        'files' => [
            ['name' => 'tair10_v1.tar.gz', 'label' => 'Version 1 · complete release · TAIR10'],
        ],
    ],
];
