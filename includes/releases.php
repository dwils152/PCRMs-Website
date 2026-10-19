<?php

// Release metadata for the home and download pages, in display order.
// Counts reflect the published version 2 maps (Database, 2022). The version 1
// genomes have no published counts yet: set 'crm_count' once they are confirmed.
// Files are gzip-compressed text in www/data; 'kind' picks the format note on
// the download page, and 'expected' sizes are shown for files not on the
// server. database/make_downloads.py writes the per-site and CRM-only files.
// Releases without files (fly, worm, thale cress for now) appear on the home
// page but not on the download page. A fact's optional third item links an
// asterisk to where the home page explains it.
$PCRMS_RELEASES = [
    [
        'genome_id' => 1,
        'version' => 2,
        'crm_count' => 1426947,
        'facts' => [['1,426,947', 'CRMs'], ['95,717,222', 'TFBSs'], ['11,348', 'ChIP-seq datasets'], ['85.5%', 'Genome covered by extended peaks', 'index.php#step-a']],
        'files' => [
            ['name' => 'Human_CRMs_TFBSs_v2_hg38.bed.gz', 'kind' => 'combined', 'label' => 'CRMs and their binding sites · one CRM per line', 'expected' => '1.4 GB'],
            ['name' => 'Human_TFBSs_v2_hg38.bed.gz', 'kind' => 'sites', 'label' => 'Binding sites · one site per line', 'expected' => '1.3 GB'],
            ['name' => 'Human_CRMs_v2_hg38.bed.gz', 'kind' => 'crms', 'label' => 'CRMs only · one CRM per line', 'expected' => '28 MB'],
        ],
    ],
    [
        'genome_id' => 2,
        'version' => 2,
        'crm_count' => 912197,
        'facts' => [['912,197', 'CRMs'], ['125,113,756', 'TFBSs'], ['9,060', 'ChIP-seq datasets'], ['77.9%', 'Genome covered by extended peaks', 'index.php#step-a']],
        'files' => [
            ['name' => 'Mouse_CRMs_TFBSs_v2_mm10.bed.gz', 'kind' => 'combined', 'label' => 'CRMs and their binding sites · one CRM per line', 'expected' => '1.9 GB'],
            ['name' => 'Mouse_TFBSs_v2_mm10.bed.gz', 'kind' => 'sites', 'label' => 'Binding sites · one site per line', 'expected' => '1.7 GB'],
            ['name' => 'Mouse_CRMs_v2_mm10.bed.gz', 'kind' => 'crms', 'label' => 'CRMs only · one CRM per line', 'expected' => '18 MB'],
        ],
    ],
    [
        'genome_id' => 5,
        'version' => 1,
        'crm_count' => null,
        'facts' => [],
        'files' => [
        ],
    ],
    [
        'genome_id' => 3,
        'version' => 1,
        'crm_count' => null,
        'facts' => [],
        'files' => [
        ],
    ],
    [
        'genome_id' => 4,
        'version' => 1,
        'crm_count' => null,
        'facts' => [],
        'files' => [
        ],
    ],
];
