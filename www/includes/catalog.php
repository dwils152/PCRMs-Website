<?php

$PCRMS_GENOMES = [
    1 => [
        'name' => 'Homo sapiens',
        'assembly' => 'GRCh38 / hg38',
        'chromosomes' => [
            'chr1', 'chr2', 'chr3', 'chr4', 'chr5', 'chr6', 'chr7', 'chr8',
            'chr9', 'chr10', 'chr11', 'chr12', 'chr13', 'chr14', 'chr15',
            'chr16', 'chr17', 'chr18', 'chr19', 'chr20', 'chr21', 'chr22',
            'chrX', 'chrY',
        ],
    ],
    2 => [
        'name' => 'Mus musculus',
        'assembly' => 'GRCm38 / mm10',
        'chromosomes' => [
            'chr1', 'chr2', 'chr3', 'chr4', 'chr5', 'chr6', 'chr7', 'chr8',
            'chr9', 'chr10', 'chr11', 'chr12', 'chr13', 'chr14', 'chr15',
            'chr16', 'chr17', 'chr18', 'chr19', 'chr20', 'chrX', 'chrY',
        ],
    ],
    3 => [
        'name' => 'Caenorhabditis elegans',
        'assembly' => 'ce10',
        'chromosomes' => ['chr1', 'chr2', 'chr3', 'chr4', 'chr5'],
    ],
    4 => [
        'name' => 'Arabidopsis thaliana',
        'assembly' => 'TAIR10',
        'chromosomes' => ['chr1', 'chr2', 'chr3', 'chr4', 'chr5', 'chrM', 'chrC'],
    ],
    5 => [
        'name' => 'Drosophila melanogaster',
        'assembly' => 'dm3',
        'chromosomes' => [
            'chr2L', 'chr2LHet', 'chr2R', 'chr2RHet', 'chr3L', 'chr3LHet',
            'chr3R', 'chr3RHet', 'chr4', 'chrU', 'chrUextra', 'chrX',
            'chrXHet', 'chrYHet', 'chrY', 'chrM',
        ],
    ],
];

$PCRMS_P_VALUES = ['0.05', '0.01', '0.000005', '0.000001'];
$PCRMS_LOCATIONS = ['upstream', 'downstream', 'both'];
$PCRMS_DISTANCES = ['50', '100', '200'];

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

if (!function_exists('pcrms_invalid_request')) {
    function pcrms_invalid_request($message)
    {
        http_response_code(400);
        $safeMessage = pcrms_h($message);

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>Invalid request — PCRMs</title><link rel="stylesheet" href="css/modern.css"></head>';
        echo '<body><main class="site-main"><section class="section"><div class="shell">';
        echo '<div class="panel" style="max-width:42rem;margin:auto"><p class="eyebrow">Request error</p>';
        echo '<h1 class="section-title">We could not run that search.</h1><p class="lede">' . $safeMessage . '</p>';
        echo '<div class="button-row"><a class="button button--primary" href="detailed_search.php">Return to analysis tools</a>';
        echo '<a class="button button--secondary" href="browse_dataset.php">Browse datasets</a></div>';
        echo '</div></div></section></main></body></html>';
        exit;
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

        $relativePath = 'motifs_version/' . $genomeId . '/' . $motifId . '.png';
        return is_file(dirname(__DIR__) . '/' . $relativePath) ? $relativePath : null;
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
