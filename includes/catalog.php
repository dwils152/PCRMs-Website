<?php

require_once __DIR__ . '/bootstrap.php';

// Genomes, keyed by the database genome ID. 'ucsc' maps each genome to the
// UCSC Genome Browser assembly PCRMs uses; 'chromosomes' there lists only the
// names that differ from the PCRMs database (ce10 uses roman numerals and the
// TAIR10 GenArk assembly uses RefSeq accessions). 'searchable' is false for
// genomes whose data are not in the database yet; the site shows them as
// coming soon.
$PCRMS_GENOMES = [
    1 => [
        'key' => 'hs',
        'searchable' => true,
        'name' => 'Homo sapiens',
        'short' => 'H. sapiens',
        'common' => 'Human',
        'assembly' => 'GRCh38 / hg38',
        'assembly_short' => 'hg38',
        'version' => 2,
        'gene_example' => 'e.g. RUNX1 or ENSG00000159216',
        'tf_example' => 'e.g. RUNX1 or CTCF',
        'ucsc' => ['db' => 'hg38', 'chromosomes' => []],
        'chromosomes' => [
            'chr1', 'chr2', 'chr3', 'chr4', 'chr5', 'chr6', 'chr7', 'chr8',
            'chr9', 'chr10', 'chr11', 'chr12', 'chr13', 'chr14', 'chr15',
            'chr16', 'chr17', 'chr18', 'chr19', 'chr20', 'chr21', 'chr22',
            'chrX', 'chrY',
        ],
    ],
    2 => [
        'key' => 'mm',
        'searchable' => true,
        'name' => 'Mus musculus',
        'short' => 'M. musculus',
        'common' => 'Mouse',
        'assembly' => 'GRCm38 / mm10',
        'assembly_short' => 'mm10',
        'version' => 2,
        'gene_example' => 'e.g. Runx1',
        'tf_example' => 'e.g. Ctcf',
        'ucsc' => ['db' => 'mm10', 'chromosomes' => []],
        'chromosomes' => [
            'chr1', 'chr2', 'chr3', 'chr4', 'chr5', 'chr6', 'chr7', 'chr8',
            'chr9', 'chr10', 'chr11', 'chr12', 'chr13', 'chr14', 'chr15',
            'chr16', 'chr17', 'chr18', 'chr19', 'chrX', 'chrY',
        ],
    ],
    3 => [
        'key' => 'ce',
        'searchable' => false,
        'name' => 'Caenorhabditis elegans',
        'short' => 'C. elegans',
        'common' => 'Worm',
        'assembly' => 'ce10',
        'assembly_short' => 'ce10',
        'version' => 1,
        'gene_example' => 'e.g. unc-54',
        'tf_example' => 'e.g. pha-4',
        'ucsc' => ['db' => 'ce10', 'chromosomes' => [
            'chr1' => 'chrI', 'chr2' => 'chrII', 'chr3' => 'chrIII', 'chr4' => 'chrIV', 'chr5' => 'chrV',
        ]],
        'chromosomes' => ['chr1', 'chr2', 'chr3', 'chr4', 'chr5'],
    ],
    4 => [
        'key' => 'at',
        'searchable' => false,
        'name' => 'Arabidopsis thaliana',
        'short' => 'A. thaliana',
        'common' => 'Thale cress',
        'assembly' => 'TAIR10',
        'assembly_short' => 'TAIR10',
        'version' => 1,
        'gene_example' => 'e.g. AT1G01010',
        'tf_example' => 'e.g. LFY',
        'ucsc' => ['db' => 'GCF_000001735.3', 'chromosomes' => [
            'chr1' => 'NC_003070.9', 'chr2' => 'NC_003071.7', 'chr3' => 'NC_003074.8',
            'chr4' => 'NC_003075.7', 'chr5' => 'NC_003076.8', 'chrM' => 'NC_001284.2', 'chrC' => 'NC_000932.1',
        ]],
        'chromosomes' => ['chr1', 'chr2', 'chr3', 'chr4', 'chr5', 'chrM', 'chrC'],
    ],
    5 => [
        'key' => 'dm',
        'searchable' => false,
        'name' => 'Drosophila melanogaster',
        'short' => 'D. melanogaster',
        'common' => 'Fruit fly',
        'assembly' => 'dm3',
        'assembly_short' => 'dm3',
        'version' => 1,
        'gene_example' => 'e.g. eve',
        'tf_example' => 'e.g. bcd',
        'ucsc' => ['db' => 'dm3', 'chromosomes' => []],
        'chromosomes' => [
            'chr2L', 'chr2LHet', 'chr2R', 'chr2RHet', 'chr3L', 'chr3LHet',
            'chr3R', 'chr3RHet', 'chr4', 'chrU', 'chrUextra', 'chrX',
            'chrXHet', 'chrYHet', 'chrY', 'chrM',
        ],
    ],
];

// Display order used across the site: human, mouse, fly, worm, thale cress.
$PCRMS_GENOME_ORDER = [1, 2, 5, 3, 4];

$PCRMS_P_VALUES = ['0.05', '0.01', '0.0005', '0.0001', '0.000005', '0.000001'];
$PCRMS_P_VALUE_LABELS = [
    '0.05' => '0.05',
    '0.01' => '0.01',
    '0.0005' => '5 × 10⁻⁴',
    '0.0001' => '1 × 10⁻⁴',
    '0.000005' => '5 × 10⁻⁶',
    '0.000001' => '1 × 10⁻⁶',
];
$PCRMS_LOCATIONS = ['upstream', 'downstream', 'both'];
$PCRMS_LOCATION_LABELS = ['upstream' => 'Upstream', 'downstream' => 'Downstream', 'both' => 'Both sides'];
// Window sizes in units of 10 kb (get_crm_in_genes_range.php).
$PCRMS_DISTANCES = ['50', '100', '200'];
$PCRMS_DISTANCE_LABELS = ['50' => '0.5 Mb', '100' => '1 Mb', '200' => '2 Mb'];

if (!function_exists('pcrms_h')) {
    function pcrms_h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('pcrms_validate_genome')) {
    function pcrms_validate_genome($value)
    {
        global $PCRMS_GENOMES;

        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException('Select a valid organism.');
        }

        $genomeId = filter_var($value, FILTER_VALIDATE_INT);
        if ($genomeId === false || !isset($PCRMS_GENOMES[$genomeId])) {
            throw new InvalidArgumentException('Select a valid organism.');
        }
        if (!$PCRMS_GENOMES[$genomeId]['searchable']) {
            throw new InvalidArgumentException($PCRMS_GENOMES[$genomeId]['common']
                . ' CRMs are coming soon. Human and mouse are available now.');
        }

        return (int) $genomeId;
    }
}

if (!function_exists('pcrms_validate_p_value')) {
    function pcrms_validate_p_value($value)
    {
        global $PCRMS_P_VALUES;

        if (!is_string($value) && !is_numeric($value)) {
            throw new InvalidArgumentException('Select a valid P-value threshold.');
        }

        $pValue = (string) $value;
        if (!in_array($pValue, $PCRMS_P_VALUES, true)) {
            throw new InvalidArgumentException('Select a valid P-value threshold.');
        }

        return $pValue;
    }
}

if (!function_exists('pcrms_validate_chromosomes')) {
    function pcrms_validate_chromosomes($genomeId, $values)
    {
        global $PCRMS_GENOMES;

        if (!is_array($values) || !$values) {
            throw new InvalidArgumentException('Select at least one chromosome.');
        }

        foreach ($values as $value) {
            if (!is_string($value) && !is_numeric($value)) {
                throw new InvalidArgumentException('One or more chromosomes are invalid for the selected organism.');
            }
        }

        $values = array_values(array_unique(array_map('strval', $values)));
        if (array_diff($values, $PCRMS_GENOMES[$genomeId]['chromosomes'])) {
            throw new InvalidArgumentException('One or more chromosomes are invalid for the selected organism.');
        }

        return $values;
    }
}

if (!function_exists('pcrms_validate_identifier')) {
    function pcrms_validate_identifier($value, $label)
    {
        if (!is_string($value) && !is_numeric($value)) {
            throw new InvalidArgumentException('Enter a valid ' . $label . '.');
        }

        $identifier = trim((string) $value);

        if ($identifier === '' || strlen($identifier) > 100 || !preg_match('/^[A-Za-z0-9_.:-]+$/', $identifier)) {
            throw new InvalidArgumentException('Enter a valid ' . $label . '.');
        }

        return $identifier;
    }
}

if (!function_exists('pcrms_validate_enum')) {
    function pcrms_validate_enum($value, $allowed, $message)
    {
        if (!is_string($value) && !is_numeric($value)) {
            throw new InvalidArgumentException($message);
        }

        $value = (string) $value;
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }
}

if (!function_exists('pcrms_validate_query_token')) {
    function pcrms_validate_query_token($value)
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{32}$/', $value)) {
            throw new InvalidArgumentException('The result-set link is invalid or incomplete.');
        }

        return $value;
    }
}

if (!function_exists('pcrms_store_query_state')) {
    function pcrms_store_query_state($sessionKey, $state)
    {
        if (!isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = [];
        }

        $now = time();
        foreach ($_SESSION[$sessionKey] as $storedToken => $storedState) {
            if (!is_array($storedState)
                || !isset($storedState['_created_at'])
                || (int) $storedState['_created_at'] < $now - 21600
            ) {
                unset($_SESSION[$sessionKey][$storedToken]);
            }
        }

        $token = bin2hex(random_bytes(16));
        $state['_created_at'] = $now;
        $_SESSION[$sessionKey][$token] = $state;

        while (count($_SESSION[$sessionKey]) > 100) {
            array_shift($_SESSION[$sessionKey]);
        }

        return $token;
    }
}

if (!function_exists('pcrms_saved_counts')) {
    // The result counts kept with a stored search: ['total' => n,
    // 'filtered' => [filter text => n]], or an empty array.
    function pcrms_saved_counts($sessionKey, $token)
    {
        $counts = isset($_SESSION[$sessionKey][$token]['counts']) ? $_SESSION[$sessionKey][$token]['counts'] : null;
        if (!is_array($counts) || !isset($counts['total'], $counts['filtered']) || !is_array($counts['filtered'])) {
            return [];
        }
        return ['total' => (int) $counts['total'], 'filtered' => array_map('intval', $counts['filtered'])];
    }
}

if (!function_exists('pcrms_remember_counts')) {
    // Keeps a search's result counts with it in the session, so paging and
    // sorting do not count a large result set again. Pages close the session
    // before querying, so this reopens it briefly.
    function pcrms_remember_counts($sessionKey, $token, $search, $total, $filtered)
    {
        if (headers_sent()) {
            return;
        }
        pcrms_session_start();
        if (isset($_SESSION[$sessionKey][$token]) && is_array($_SESSION[$sessionKey][$token])) {
            $counts = pcrms_saved_counts($sessionKey, $token) ?: ['filtered' => []];
            $counts['total'] = (int) $total;
            $counts['filtered'][$search] = (int) $filtered;
            while (count($counts['filtered']) > 20) {
                array_shift($counts['filtered']);
            }
            $_SESSION[$sessionKey][$token]['counts'] = $counts;
        }
        session_write_close();
    }
}

if (!function_exists('pcrms_message_page')) {
    // Renders a complete page with a single message panel and stops.
    function pcrms_message_page($status, $title, $heading, $message, $actions)
    {
        require_once __DIR__ . '/layout.php';
        if (!headers_sent()) {
            http_response_code($status);
        }

        pcrms_page_start(['title' => $title, 'active' => '']);
        echo '<main id="main-content" class="message-page"><div class="shell">';
        echo '<section class="message-panel" aria-labelledby="message-title">';
        echo '<h1 id="message-title">' . pcrms_h($heading) . '</h1>';
        echo '<p>' . pcrms_h($message) . '</p><div class="button-row">';
        foreach ($actions as $index => $action) {
            $class = $index === 0 ? 'btn btn-primary' : 'btn btn-outline';
            $href = substr($action[1], 0, 1) === '/' ? $action[1] : pcrms_url($action[1]);
            echo '<a class="' . $class . '" href="' . pcrms_h($href) . '">' . pcrms_h($action[0]) . '</a>';
        }
        echo '</div></section></div></main>';
        pcrms_page_end();
        exit;
    }
}

if (!function_exists('pcrms_invalid_request')) {
    function pcrms_invalid_request($message)
    {
        pcrms_message_page(400, 'Search problem', 'We could not run that search', $message, [
            ['Browse the CRM maps', 'browse_dataset.php'],
            ['Functional analysis', 'detailed_search.php'],
        ]);
    }
}

if (!function_exists('pcrms_is_timeout')) {
    // True when a query was stopped by the statement timeout (connection.php).
    function pcrms_is_timeout($exception)
    {
        // 3024: MySQL max_execution_time; 1969: MariaDB max_statement_time.
        return $exception instanceof mysqli_sql_exception && in_array($exception->getCode(), [3024, 1969], true);
    }
}

if (!function_exists('pcrms_service_unavailable')) {
    // Shown when the database cannot be reached or a query fails.
    function pcrms_service_unavailable($exception = null)
    {
        if ($exception instanceof Throwable) {
            error_log('PCRMs page failed: ' . $exception->getMessage());
        }
        if (pcrms_is_timeout($exception)) {
            pcrms_message_page(503, 'Search took too long', 'That search took too long',
                'It matches too many records to show here. Try fewer chromosomes or a stricter p-value cutoff, or download the genome-wide files instead.', [
                    ['Go to downloads', 'download_data.php'],
                    ['Browse the CRM maps', 'browse_dataset.php'],
                ]);
        }
        if (!headers_sent()) {
            header('Retry-After: 300');
        }
        pcrms_message_page(503, 'Temporarily unavailable', 'The database is temporarily unavailable',
            'PCRMs could not reach its database just now. Please try again in a few minutes; the genome-wide files on the download page are still available.', [
                ['Try again', isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'index.php'],
                ['Go to downloads', 'download_data.php'],
            ]);
    }
}

if (!function_exists('pcrms_genome_key')) {
    function pcrms_genome_key($genomeId)
    {
        global $PCRMS_GENOMES;
        return isset($PCRMS_GENOMES[$genomeId]) ? $PCRMS_GENOMES[$genomeId]['key'] : '';
    }
}

if (!function_exists('pcrms_chromosome_label')) {
    // "chr1, chr2" for a few chromosomes, a count for many, "all chromosomes" for all.
    function pcrms_chromosome_label($genomeId, $chromosomes)
    {
        global $PCRMS_GENOMES;
        $all = $PCRMS_GENOMES[$genomeId]['chromosomes'];
        $ordered = array_values(array_intersect($all, $chromosomes));
        if (count($ordered) === count($all)) {
            return 'all chromosomes';
        }
        if (count($ordered) > 6) {
            return count($ordered) . ' chromosomes';
        }
        return implode(', ', $ordered);
    }
}

if (!function_exists('pcrms_ucsc_config')) {
    // Assembly and chromosome-name mapping for UCSC links, for use in JavaScript.
    function pcrms_ucsc_config()
    {
        global $PCRMS_GENOMES;
        $config = [];
        foreach ($PCRMS_GENOMES as $genomeId => $genome) {
            $config[$genomeId] = $genome['ucsc'];
        }
        return $config;
    }
}

if (!function_exists('pcrms_ucsc_url')) {
    // Opens the UCSC Genome Browser on a region with flanking sequence and the
    // region itself highlighted.
    function pcrms_ucsc_url($genomeId, $chromosome, $start, $end)
    {
        global $PCRMS_GENOMES;
        if (!isset($PCRMS_GENOMES[$genomeId])) {
            return '';
        }
        $ucsc = $PCRMS_GENOMES[$genomeId]['ucsc'];
        $chrom = isset($ucsc['chromosomes'][$chromosome]) ? $ucsc['chromosomes'][$chromosome] : $chromosome;
        $start = (int) $start;
        $end = (int) $end;
        $pad = max(2000, $end - $start);
        $view = $chrom . ':' . max(1, $start - $pad) . '-' . ($end + $pad);
        $mark = $ucsc['db'] . '.' . $chrom . ':' . $start . '-' . $end . '#B6DB4C';

        return 'https://genome.ucsc.edu/cgi-bin/hgTracks?' . http_build_query([
            'db' => $ucsc['db'],
            'position' => $view,
            'highlight' => $mark,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('pcrms_format_p_value')) {
    // 0.03, 0.005, 3.0 × 10⁻⁶ — the same format the results tables use.
    function pcrms_format_p_value($value)
    {
        $p = (float) $value;
        if ($p == 0) {
            return '0';
        }
        if ($p >= 0.001) {
            return rtrim(rtrim(number_format($p, 4, '.', ''), '0'), '.');
        }
        $parts = explode('e', sprintf('%.1e', $p));
        $exponent = (string) (int) $parts[1];
        $superscripts = ['-' => '⁻', '0' => '⁰', '1' => '¹', '2' => '²', '3' => '³', '4' => '⁴', '5' => '⁵', '6' => '⁶', '7' => '⁷', '8' => '⁸', '9' => '⁹'];
        return $parts[0] . ' × 10' . strtr($exponent, $superscripts);
    }
}

if (!function_exists('pcrms_execute_statement')) {
    function pcrms_execute_statement($connection, $sql, $types, $values)
    {
        $statement = $connection->prepare($sql);
        $bindArguments = [$types];

        foreach ($values as $index => $value) {
            $values[$index] = $value;
            $bindArguments[] = &$values[$index];
        }

        call_user_func_array([$statement, 'bind_param'], $bindArguments);
        $statement->execute();

        return $statement;
    }
}

if (!function_exists('pcrms_motif_path')) {
    function pcrms_motif_path($genomeId, $motifId)
    {
        $genomeId = (int) $genomeId;
        $motifId = (string) $motifId;

        if ($genomeId < 1 || !preg_match('/^[A-Za-z0-9_.-]+$/', $motifId)) {
            return null;
        }

        // SVG logos are drawn by database/plot_logos.py; older genomes still
        // have PNG logos.
        foreach (['svg', 'png'] as $extension) {
            $relativePath = 'motifs_version/' . $genomeId . '/' . $motifId . '.' . $extension;
            if (is_file(dirname(__DIR__) . '/www/' . $relativePath)) {
                return $relativePath;
            }
        }
        return null;
    }
}

if (!function_exists('pcrms_search_value')) {
    function pcrms_search_value($value, $maximumCharacters = 100)
    {
        if (!is_string($value) || !preg_match('//u', $value)) {
            throw new InvalidArgumentException('The table search contains invalid text.');
        }

        $value = trim($value);
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maximumCharacters, 'UTF-8');
        }

        preg_match_all('/./us', $value, $characters);
        return implode('', array_slice($characters[0], 0, $maximumCharacters));
    }
}
