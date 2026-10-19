<?php

require_once __DIR__ . '/catalog.php';

// Largest result set the CSV export will stream; bigger sets should use the
// genome-wide files on the download page.
const PCRMS_EXPORT_LIMIT = 250000;

if (!function_exists('pcrms_load_browse_query')) {
    // Returns the validated browse search stored in the session under $token.
    function pcrms_load_browse_query($token)
    {
        $token = pcrms_validate_query_token($token);
        if (!isset(
            $_SESSION['pcrms_browse_queries'][$token]['genome_id'],
            $_SESSION['pcrms_browse_queries'][$token]['chr_id'],
            $_SESSION['pcrms_browse_queries'][$token]['p_value']
        )) {
            throw new InvalidArgumentException('That search has expired. Run it again from the browse page.');
        }
        $state = $_SESSION['pcrms_browse_queries'][$token];
        $genomeId = pcrms_validate_genome($state['genome_id']);

        return [
            'token' => $token,
            'genome_id' => $genomeId,
            'chromosomes' => pcrms_validate_chromosomes($genomeId, $state['chr_id']),
            'p_value' => pcrms_validate_p_value($state['p_value']),
        ];
    }
}

if (!function_exists('pcrms_load_tf_query')) {
    // Returns the validated TF search stored in the session under $token.
    function pcrms_load_tf_query($token)
    {
        $token = pcrms_validate_query_token($token);
        if (!isset(
            $_SESSION['pcrms_tf_queries'][$token]['genome_id'],
            $_SESSION['pcrms_tf_queries'][$token]['tf_id'],
            $_SESSION['pcrms_tf_queries'][$token]['chr_id']
        )) {
            throw new InvalidArgumentException('That search has expired. Run it again from the functional analysis page.');
        }
        $state = $_SESSION['pcrms_tf_queries'][$token];
        $genomeId = pcrms_validate_genome($state['genome_id']);

        return [
            'token' => $token,
            'genome_id' => $genomeId,
            'tf_id' => pcrms_validate_identifier($state['tf_id'], 'transcription factor symbol'),
            'chromosomes' => pcrms_validate_chromosomes($genomeId, $state['chr_id']),
        ];
    }
}

if (!function_exists('pcrms_like_pattern')) {
    // Escapes a free-text search for use with LIKE ... ESCAPE '!'.
    function pcrms_like_pattern($search)
    {
        return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
    }
}

if (!function_exists('pcrms_chromosome_order_sql')) {
    // Orders chr1, chr2 ... chr22 numerically, then chrX, chrY and other names.
    function pcrms_chromosome_order_sql($column, $direction)
    {
        $number = "CAST(SUBSTRING({$column}, 4) AS UNSIGNED)";
        return "({$number} = 0) {$direction}, {$number} {$direction}, {$column} {$direction}";
    }
}

if (!function_exists('pcrms_browse_where')) {
    // WHERE clause, bind types and values for a browse search on crms_genes.
    function pcrms_browse_where($query, $search)
    {
        $placeholders = implode(', ', array_fill(0, count($query['chromosomes']), '?'));
        $where = "Genome = ? AND Chromosome IN ({$placeholders}) AND P_value <= ?";
        $values = array_merge([$query['genome_id']], $query['chromosomes'], [(float) $query['p_value']]);
        $types = 'i' . str_repeat('s', count($query['chromosomes'])) . 'd';

        if ($search !== '') {
            $where .= " AND (crmID LIKE ? ESCAPE '!' OR Chromosome LIKE ? ESCAPE '!' OR Genes_Symbol LIKE ? ESCAPE '!')";
            $pattern = pcrms_like_pattern($search);
            array_push($values, $pattern, $pattern, $pattern);
            $types .= 'sss';
        }

        return [$where, $types, $values];
    }
}

if (!function_exists('pcrms_browse_order')) {
    // Column indexes follow the browse results table.
    function pcrms_browse_order($index, $direction)
    {
        $columns = [
            0 => "crmID {$direction}",
            1 => pcrms_chromosome_order_sql('Chromosome', $direction) . ", Start_Pos {$direction}",
            2 => "Start_Pos {$direction}",
            3 => "End_Pos {$direction}",
            4 => "Genes_Symbol {$direction}",
            5 => "Score {$direction}",
            6 => "P_value {$direction}",
        ];
        $order = isset($columns[$index]) ? $columns[$index] : $columns[1];
        return $order . ', crmID ASC, Start_Pos ASC';
    }
}

if (!function_exists('pcrms_tf_from')) {
    // FROM/WHERE clause, bind types and values for a TF binding-site search.
    function pcrms_tf_from($query, $search)
    {
        $placeholders = implode(', ', array_fill(0, count($query['chromosomes']), '?'));
        $from = "FROM tfbss t
                 INNER JOIN umotif_tfs_association u ON t.umID = u.umID
                 INNER JOIN crms c ON c.crmID = t.crmID AND c.Genome = ?
                 WHERE u.Genome = ?
                 AND c.Chromosome IN ({$placeholders})
                 AND u.tfID = ?";
        $values = array_merge([$query['genome_id'], $query['genome_id']], $query['chromosomes'], [$query['tf_id']]);
        $types = 'ii' . str_repeat('s', count($query['chromosomes'])) . 's';

        if ($search !== '') {
            $from .= " AND (c.crmID LIKE ? ESCAPE '!' OR c.Chromosome LIKE ? ESCAPE '!' OR t.umID LIKE ? ESCAPE '!' OR u.tfID LIKE ? ESCAPE '!')";
            $pattern = pcrms_like_pattern($search);
            array_push($values, $pattern, $pattern, $pattern, $pattern);
            $types .= 'ssss';
        }

        return [$from, $types, $values];
    }
}

if (!function_exists('pcrms_tf_order')) {
    // Column indexes follow the TF results table.
    function pcrms_tf_order($index, $direction)
    {
        $columns = [
            0 => "c.crmID {$direction}",
            1 => pcrms_chromosome_order_sql('c.Chromosome', $direction) . ", t.TFBS_Start {$direction}",
            2 => "t.TFBS_Start {$direction}",
            3 => "t.TFBS_End {$direction}",
            4 => "t.umID {$direction}",
            5 => "u.tfID {$direction}",
            6 => "t.Binding_Score {$direction}",
        ];
        $order = isset($columns[$index]) ? $columns[$index] : $columns[1];
        return $order . ', c.crmID ASC, t.TFBS_Start ASC, t.TFBS_End ASC, t.umID ASC';
    }
}

if (!function_exists('pcrms_csv_cell')) {
    // Quotes a CSV value and neutralises spreadsheet formulas in text fields.
    function pcrms_csv_cell($value, $isText = false)
    {
        $value = (string) $value;
        if ($isText && $value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) {
            $value = "'" . $value;
        }
        if (strpbrk($value, "\",\r\n") !== false) {
            $value = '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}

if (!function_exists('pcrms_crm_url')) {
    function pcrms_crm_url($crmId, $genomeId)
    {
        return 'crm.php?' . http_build_query(['crmId' => $crmId, 'genome_id' => (int) $genomeId], '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('pcrms_crm_row')) {
    // A CRM row as the results tables expect it.
    function pcrms_crm_row($row, $genomeId)
    {
        return [
            'id' => (string) $row['crmID'],
            'chrom' => (string) $row['Chromosome'],
            'start' => (int) $row['Start_Pos'],
            'end' => (int) $row['End_Pos'],
            'genes' => isset($row['Genes_Symbol']) ? implode(', ', array_map('trim', explode(',', (string) $row['Genes_Symbol']))) : '',
            'score' => (float) $row['Score'],
            'p' => (float) $row['P_value'],
            'ucsc' => pcrms_ucsc_url($genomeId, $row['Chromosome'], $row['Start_Pos'], $row['End_Pos']),
            'record' => pcrms_crm_url($row['crmID'], $genomeId),
        ];
    }
}

if (!function_exists('pcrms_site_row')) {
    // A TF binding-site row as the results tables expect it.
    function pcrms_site_row($row, $genomeId, $chromosome)
    {
        $logo = pcrms_motif_path($genomeId, $row['umID']);
        return [
            'crm' => isset($row['crmID']) ? (string) $row['crmID'] : '',
            'chrom' => (string) $chromosome,
            'start' => (int) $row['TFBS_Start'],
            'end' => (int) $row['TFBS_End'],
            'motif' => (string) $row['umID'],
            'tf' => isset($row['tfID']) ? (string) $row['tfID'] : (isset($row['tfNames']) ? implode(', ', array_map('trim', explode(',', (string) $row['tfNames']))) : ''),
            'score' => (float) $row['Binding_Score'],
            'logo' => $logo === null ? '' : $logo,
            'ucsc' => pcrms_ucsc_url($genomeId, $chromosome, $row['TFBS_Start'], $row['TFBS_End']),
            'record' => isset($row['crmID']) ? pcrms_crm_url($row['crmID'], $genomeId) : '',
        ];
    }
}

if (!function_exists('pcrms_fetch_browse')) {
    // One page of a browse search: ['total', 'filtered', 'page', 'rows'].
    function pcrms_fetch_browse($db, $query, $state)
    {
        $orderIndex = ['id' => 0, 'location' => 1, 'genes' => 4, 'score' => 5, 'p' => 6];
        $direction = $state['dir'] === 'desc' ? 'DESC' : 'ASC';

        list($where, $types, $values) = pcrms_browse_where($query, '');
        $total = (int) pcrms_execute_statement($db, "SELECT COUNT(*) FROM crms_genes WHERE {$where}", $types, $values)
            ->get_result()->fetch_row()[0];
        $filtered = $total;
        if ($state['search'] !== '') {
            list($where, $types, $values) = pcrms_browse_where($query, $state['search']);
            $filtered = (int) pcrms_execute_statement($db, "SELECT COUNT(*) FROM crms_genes WHERE {$where}", $types, $values)
                ->get_result()->fetch_row()[0];
        }

        $page = min($state['page'], max(1, (int) ceil($filtered / $state['size'])));
        $offset = ($page - 1) * $state['size'];
        $rows = [];
        if ($offset < $filtered) {
            $result = pcrms_execute_statement(
                $db,
                "SELECT crmID, Chromosome, Start_Pos, End_Pos, Genes_Symbol, Score, P_value
                 FROM crms_genes WHERE {$where}
                 ORDER BY " . pcrms_browse_order($orderIndex[$state['sort']], $direction) . '
                 LIMIT ? OFFSET ?',
                $types . 'ii',
                array_merge($values, [$state['size'], $offset])
            )->get_result();
            while ($row = $result->fetch_assoc()) {
                $rows[] = pcrms_crm_row($row, $query['genome_id']);
            }
        }

        return ['total' => $total, 'filtered' => $filtered, 'page' => $page, 'rows' => $rows];
    }
}

if (!function_exists('pcrms_fetch_tf')) {
    // One page of a TF binding-site search: ['total', 'filtered', 'page', 'rows'].
    function pcrms_fetch_tf($db, $query, $state)
    {
        $orderIndex = ['crm' => 0, 'site' => 1, 'motif' => 4, 'tf' => 5, 'score' => 6];
        $direction = $state['dir'] === 'desc' ? 'DESC' : 'ASC';

        list($from, $types, $values) = pcrms_tf_from($query, '');
        $total = (int) pcrms_execute_statement($db, "SELECT COUNT(*) {$from}", $types, $values)->get_result()->fetch_row()[0];
        $filtered = $total;
        if ($state['search'] !== '') {
            list($from, $types, $values) = pcrms_tf_from($query, $state['search']);
            $filtered = (int) pcrms_execute_statement($db, "SELECT COUNT(*) {$from}", $types, $values)->get_result()->fetch_row()[0];
        }

        $page = min($state['page'], max(1, (int) ceil($filtered / $state['size'])));
        $offset = ($page - 1) * $state['size'];
        $rows = [];
        if ($offset < $filtered) {
            $result = pcrms_execute_statement(
                $db,
                "SELECT c.crmID, c.Chromosome, t.TFBS_Start, t.TFBS_End, t.umID, u.tfID, t.Binding_Score
                 {$from}
                 ORDER BY " . pcrms_tf_order($orderIndex[$state['sort']], $direction) . '
                 LIMIT ? OFFSET ?',
                $types . 'ii',
                array_merge($values, [$state['size'], $offset])
            )->get_result();
            while ($row = $result->fetch_assoc()) {
                $rows[] = pcrms_site_row($row, $query['genome_id'], $row['Chromosome']);
            }
        }

        return ['total' => $total, 'filtered' => $filtered, 'page' => $page, 'rows' => $rows];
    }
}
