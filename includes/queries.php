<?php

require_once __DIR__ . '/catalog.php';

// Largest result set the CSV export will stream; bigger sets should use the
// genome-wide files on the download page.
const PCRMS_EXPORT_LIMIT = 250000;

// The searches cover each genome's dePCRM2 map, dataset 1 in the database.
const PCRMS_DEPCRM2_DATASET = 1;

// CRM IDs are numbered in genomic order (database/schema.sql), so sorting by
// ID is sorting by location, and one chromosome's CRMs form one ID range.

// Comma-separated closest-gene symbols of the CRM in column $crmColumn.
const PCRMS_GENES_SQL = "(SELECT GROUP_CONCAT(DISTINCT g.Symbol ORDER BY g.Symbol SEPARATOR ', ')
    FROM crms_genes_association a INNER JOIN genes g ON g.geneID = a.geneID
    WHERE a.crmID = %s)";

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
            'dataset' => PCRMS_DEPCRM2_DATASET,
            'chromosomes' => pcrms_validate_chromosomes($genomeId, $state['chr_id']),
            'p_value' => pcrms_validate_p_value($state['p_value']),
            'session_key' => 'pcrms_browse_queries',
            'counts' => pcrms_saved_counts('pcrms_browse_queries', $token),
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
            'dataset' => PCRMS_DEPCRM2_DATASET,
            'tf_id' => pcrms_validate_identifier($state['tf_id'], 'transcription factor symbol'),
            'chromosomes' => pcrms_validate_chromosomes($genomeId, $state['chr_id']),
            'session_key' => 'pcrms_tf_queries',
            'counts' => pcrms_saved_counts('pcrms_tf_queries', $token),
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

if (!function_exists('pcrms_browse_from')) {
    // FROM clause (rows aliased c), bind types and values for a browse search.
    function pcrms_browse_from($query, $search)
    {
        $placeholders = implode(', ', array_fill(0, count($query['chromosomes']), '?'));
        $where = "c.Genome = ? AND c.Dataset = ? AND c.Chromosome IN ({$placeholders}) AND c.P_value <= ?";
        $values = array_merge([$query['genome_id'], $query['dataset']], $query['chromosomes'], [(float) $query['p_value']]);
        $types = 'ii' . str_repeat('s', count($query['chromosomes'])) . 'd';
        if ($search === '') {
            return ["crms c WHERE {$where}", $types, $values];
        }

        // A CRM matches the filter by chromosome, by ID or by a closest
        // gene's symbol. The chosen chromosomes are known and CRM IDs are
        // numbers, so the first two are settled here. Each way of matching is
        // its own query, joined with UNION, so each one can use an index.
        $branches = [];
        $chromosomes = array_values(array_filter($query['chromosomes'], function ($chromosome) use ($search) {
            return stripos($chromosome, $search) !== false;
        }));
        if ($chromosomes) {
            $branches[] = ['c.Chromosome IN (' . implode(', ', array_fill(0, count($chromosomes), '?')) . ')',
                str_repeat('s', count($chromosomes)), $chromosomes];
        }
        $pattern = pcrms_like_pattern($search);
        if (ctype_digit($search)) {
            $branches[] = ["c.crmID LIKE ? ESCAPE '!'", 's', [$pattern]];
        }
        $branches[] = ["c.crmID IN (SELECT a.crmID FROM crms_genes_association a INNER JOIN genes g ON g.geneID = a.geneID
            WHERE g.Genome = ? AND LOWER(g.Symbol) LIKE LOWER(?) ESCAPE '!')", 'is', [$query['genome_id'], $pattern]];

        // Both forms are derived tables MySQL cannot merge into the outer
        // query (DISTINCT, UNION), so it filters before it sorts: otherwise
        // a sort by closest genes would build every CRM's gene list first.
        if (count($branches) === 1) {
            return ["(SELECT DISTINCT c.* FROM crms c WHERE {$where} AND {$branches[0][0]}) c",
                $types . $branches[0][1], array_merge($values, $branches[0][2])];
        }
        $parts = [];
        $unionTypes = '';
        $unionValues = [];
        foreach ($branches as $branch) {
            $parts[] = "SELECT c.* FROM crms c WHERE {$where} AND {$branch[0]}";
            $unionTypes .= $types . $branch[1];
            $unionValues = array_merge($unionValues, $values, $branch[2]);
        }
        return ['(' . implode(' UNION ', $parts) . ') c', $unionTypes, $unionValues];
    }
}

if (!function_exists('pcrms_browse_order')) {
    // ORDER BY for a browse results column key.
    function pcrms_browse_order($sort, $direction)
    {
        $columns = [
            'id' => "c.crmID {$direction}",
            'location' => "c.crmID {$direction}",
            'genes' => sprintf(PCRMS_GENES_SQL, 'c.crmID') . " {$direction}",
            'score' => "c.Score {$direction}",
            'p' => "c.P_value {$direction}",
        ];
        return (isset($columns[$sort]) ? $columns[$sort] : $columns['location']) . ', c.crmID ASC';
    }
}

if (!function_exists('pcrms_crm_ranges')) {
    // [chromosome => [first, last] CRM ID] for the chosen chromosomes, in
    // genomic order. CRM IDs follow location, so each chromosome's first and
    // last CRM come straight from the location index.
    function pcrms_crm_ranges($db, $query)
    {
        $parts = [];
        $types = '';
        $values = [];
        foreach ($query['chromosomes'] as $chromosome) {
            foreach (['ASC', 'DESC'] as $direction) {
                $parts[] = "(SELECT Chromosome, crmID FROM crms WHERE Genome = ? AND Dataset = ? AND Chromosome = ?
                    ORDER BY Start_Pos {$direction}, End_Pos {$direction} LIMIT 1)";
                $types .= 'iis';
                array_push($values, $query['genome_id'], $query['dataset'], $chromosome);
            }
        }
        $result = pcrms_execute_statement($db, implode(' UNION ALL ', $parts), $types, $values)->get_result();
        $ranges = [];
        while ($row = $result->fetch_row()) {
            $ranges[$row[0]][] = (int) $row[1];
        }
        uasort($ranges, function ($a, $b) {
            return $a[0] <=> $b[0];
        });
        return $ranges;
    }
}

if (!function_exists('pcrms_tf_motifs')) {
    // [[motifID => motif number], TF name as stored] for the searched TF.
    // Case is ignored, since human and mouse write gene symbols differently
    // (CTCF, Ctcf); the table is small enough to scan.
    function pcrms_tf_motifs($db, $query)
    {
        $result = pcrms_execute_statement(
            $db,
            'SELECT m.motifID, m.umID, u.tfID FROM umotif_tfs_association u
             INNER JOIN umotifs m ON m.motifID = u.motifID
             WHERE LOWER(u.tfID) = LOWER(?) AND m.Genome = ? AND m.Dataset = ?
             ORDER BY m.umID',
            'sii',
            [$query['tf_id'], $query['genome_id'], $query['dataset']]
        )->get_result();
        $motifs = [];
        $name = $query['tf_id'];
        while ($row = $result->fetch_row()) {
            $motifs[(int) $row[0]] = (int) $row[1];
            $name = $row[2];
        }
        return [$motifs, $name];
    }
}

if (!function_exists('pcrms_tf_where')) {
    // WHERE clause, bind types and values over tfbss t for a TF search. The
    // TF's motifs and the chromosomes' CRM ID ranges are passed in as
    // constants, so MySQL reads only that slice of the (motifID, crmID)
    // index. A filter on TF, motif or chromosome is resolved here; only the
    // CRM ID part is left to the database.
    function pcrms_tf_where($query, $motifs, $ranges, $search)
    {
        $ids = array_keys($motifs);
        $where = $ids ? 't.motifID IN (' . implode(', ', array_fill(0, count($ids), '?')) . ')' : 'FALSE';
        $values = $ids;
        $types = str_repeat('i', count($ids));

        // Neighbouring chromosomes' ranges join up, so whole-genome searches
        // read one range instead of one per chromosome.
        $spans = [];
        foreach ($ranges as $range) {
            $last = count($spans) - 1;
            if ($last >= 0 && $spans[$last][1] + 1 === $range[0]) {
                $spans[$last][1] = $range[1];
            } else {
                $spans[] = $range;
            }
        }
        $between = [];
        foreach ($spans as $span) {
            $between[] = 't.crmID BETWEEN ? AND ?';
            array_push($values, $span[0], $span[1]);
            $types .= 'ii';
        }
        $where .= ' AND (' . ($between ? implode(' OR ', $between) : 'FALSE') . ')';

        if ($search !== '' && stripos($query['tf_id'], $search) === false) {
            $any = [];
            $matching = array_keys(array_filter($motifs, function ($umId) use ($search) {
                return stripos((string) $umId, $search) !== false;
            }));
            if ($matching) {
                $any[] = 't.motifID IN (' . implode(', ', array_fill(0, count($matching), '?')) . ')';
                $values = array_merge($values, $matching);
                $types .= str_repeat('i', count($matching));
            }
            foreach ($ranges as $chromosome => $range) {
                if (stripos($chromosome, $search) !== false) {
                    $any[] = 't.crmID BETWEEN ? AND ?';
                    array_push($values, $range[0], $range[1]);
                    $types .= 'ii';
                }
            }
            // CRM IDs are numbers, so only digits can match one.
            if (ctype_digit($search)) {
                $any[] = "t.crmID LIKE ? ESCAPE '!'";
                $values[] = pcrms_like_pattern($search);
                $types .= 's';
            }
            $where .= ' AND (' . ($any ? implode(' OR ', $any) : 'FALSE') . ')';
        }

        return [$where, $types, $values];
    }
}

if (!function_exists('pcrms_tf_page_sql')) {
    // SELECT for $limit binding sites from $offset, in the order of a TF
    // results column key. In genomic order each motif is read in index order
    // and only the first $offset + $limit sites of each are merged, so the
    // first pages stay fast however many sites the TF has.
    function pcrms_tf_page_sql($where, $types, $values, $motifs, $sort, $direction, $limit, $offset)
    {
        $columns = 't.crmID, t.TFBS_Start, t.TFBS_End, t.motifID, t.Binding_Score';
        $genomic = "crmID {$direction}, TFBS_Start {$direction}, motifID ASC";
        if ($sort === 'motif' && count($motifs) > 1) {
            $order = 'FIELD(motifID, ' . implode(', ', array_map('intval', array_keys($motifs))) . ") {$direction}, crmID ASC, TFBS_Start ASC";
        } elseif ($sort === 'score') {
            $order = "Binding_Score {$direction}, crmID ASC, TFBS_Start ASC, motifID ASC";
        } else {
            $order = $genomic;
        }

        if ($order !== $genomic || count($motifs) < 2) {
            return ["SELECT {$columns} FROM tfbss t WHERE {$where} ORDER BY {$order} LIMIT ? OFFSET ?",
                $types . 'ii', array_merge($values, [$limit, $offset])];
        }

        $parts = [];
        $partTypes = '';
        $partValues = [];
        foreach (array_keys($motifs) as $motifId) {
            $parts[] = "(SELECT {$columns} FROM tfbss t WHERE {$where} AND t.motifID = ? ORDER BY {$genomic} LIMIT ?)";
            $partTypes .= $types . 'ii';
            $partValues = array_merge($partValues, $values, [$motifId, $offset + $limit]);
        }
        return ['SELECT * FROM (' . implode(' UNION ALL ', $parts) . ") x ORDER BY {$genomic} LIMIT ? OFFSET ?",
            $partTypes . 'ii', array_merge($partValues, [$limit, $offset])];
    }
}

if (!function_exists('pcrms_tf_site')) {
    // A tfbss row with its chromosome, motif number and TF filled in.
    function pcrms_tf_site($row, $query, $motifs, $ranges)
    {
        foreach ($ranges as $chromosome => $range) {
            if ($row['crmID'] >= $range[0] && $row['crmID'] <= $range[1]) {
                $row['Chromosome'] = $chromosome;
                break;
            }
        }
        $row['umID'] = $motifs[$row['motifID']];
        $row['tfID'] = $query['tf_id'];
        return $row;
    }
}

if (!function_exists('pcrms_crm_genes')) {
    // Closest-gene symbols for each of $crmIds: [crmID => 'A, B'].
    function pcrms_crm_genes($db, $crmIds)
    {
        if (!$crmIds) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($crmIds), '?'));
        $result = pcrms_execute_statement(
            $db,
            "SELECT a.crmID, GROUP_CONCAT(DISTINCT g.Symbol ORDER BY g.Symbol SEPARATOR ', ')
             FROM crms_genes_association a INNER JOIN genes g ON g.geneID = a.geneID
             WHERE a.crmID IN ({$placeholders})
             GROUP BY a.crmID",
            str_repeat('i', count($crmIds)),
            array_values($crmIds)
        )->get_result();
        $genes = [];
        while ($row = $result->fetch_row()) {
            $genes[(int) $row[0]] = $row[1];
        }
        return $genes;
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

if (!function_exists('pcrms_tss_distance')) {
    /**
     * Signed distance in bp from a gene's transcription start site to a CRM:
     * 0 when the TSS lies inside the CRM, negative when the CRM is upstream
     * of the gene and positive when downstream, whichever strand the gene is
     * on. Adjacent bases are 1 bp apart. Coordinates are BED style, as the
     * genes and crms tables store them, so the TSS of a - strand gene is
     * End_Pos - 1 and a CRM covers Start_Pos to End_Pos - 1.
     */
    function pcrms_tss_distance($gene, $start, $end)
    {
        $plus = $gene['Strand'] === '+';
        $tss = $plus ? (int) $gene['Start_Pos'] : (int) $gene['End_Pos'] - 1;
        if ($tss >= $start && $tss < $end) {
            return 0;
        }
        $offset = $tss < $start ? $start - $tss : -($tss - ($end - 1));
        return $plus ? $offset : -$offset;
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

if (!function_exists('pcrms_search_counts')) {
    // [total, filtered] for a stored search, from the session when they were
    // counted before. $count($search) runs the COUNT query for a filter text.
    function pcrms_search_counts($query, $search, $count)
    {
        $saved = $query['counts'];
        $total = isset($saved['total']) ? $saved['total'] : $count('');
        if ($search === '') {
            $filtered = $total;
        } else {
            $filtered = isset($saved['filtered'][$search]) ? $saved['filtered'][$search] : $count($search);
        }
        if (!isset($saved['total'], $saved['filtered'][$search])) {
            pcrms_remember_counts($query['session_key'], $query['token'], $search, $total, $filtered);
        }
        return [$total, $filtered];
    }
}

if (!function_exists('pcrms_fetch_browse')) {
    // One page of a browse search: ['total', 'filtered', 'page', 'rows'].
    function pcrms_fetch_browse($db, $query, $state)
    {
        $direction = $state['dir'] === 'desc' ? 'DESC' : 'ASC';

        list($total, $filtered) = pcrms_search_counts($query, $state['search'], function ($search) use ($db, $query) {
            list($from, $types, $values) = pcrms_browse_from($query, $search);
            return (int) pcrms_execute_statement($db, "SELECT COUNT(*) FROM {$from}", $types, $values)
                ->get_result()->fetch_row()[0];
        });
        list($from, $types, $values) = pcrms_browse_from($query, $state['search']);

        $page = min($state['page'], max(1, (int) ceil($filtered / $state['size'])));
        $offset = ($page - 1) * $state['size'];
        $records = [];
        if ($offset < $filtered) {
            $result = pcrms_execute_statement(
                $db,
                "SELECT c.crmID, c.Chromosome, c.Start_Pos, c.End_Pos, c.Score, c.P_value
                 FROM {$from}
                 ORDER BY " . pcrms_browse_order($state['sort'], $direction) . '
                 LIMIT ? OFFSET ?',
                $types . 'ii',
                array_merge($values, [$state['size'], $offset])
            )->get_result();
            $records = $result->fetch_all(MYSQLI_ASSOC);
        }

        $genes = pcrms_crm_genes($db, array_column($records, 'crmID'));
        $rows = [];
        foreach ($records as $record) {
            $record['Genes_Symbol'] = isset($genes[$record['crmID']]) ? $genes[$record['crmID']] : '';
            $rows[] = pcrms_crm_row($record, $query['genome_id']);
        }

        return ['total' => $total, 'filtered' => $filtered, 'page' => $page, 'rows' => $rows];
    }
}

if (!function_exists('pcrms_fetch_tf')) {
    // One page of a TF binding-site search: ['total', 'filtered', 'page', 'rows'].
    function pcrms_fetch_tf($db, $query, $state)
    {
        $direction = $state['dir'] === 'desc' ? 'DESC' : 'ASC';
        $ranges = pcrms_crm_ranges($db, $query);
        list($motifs, $query['tf_id']) = pcrms_tf_motifs($db, $query);

        list($total, $filtered) = pcrms_search_counts($query, $state['search'], function ($search) use ($db, $query, $motifs, $ranges) {
            list($where, $types, $values) = pcrms_tf_where($query, $motifs, $ranges, $search);
            return (int) pcrms_execute_statement($db, "SELECT COUNT(*) FROM tfbss t WHERE {$where}", $types, $values)
                ->get_result()->fetch_row()[0];
        });
        list($where, $types, $values) = pcrms_tf_where($query, $motifs, $ranges, $state['search']);

        $page = min($state['page'], max(1, (int) ceil($filtered / $state['size'])));
        $offset = ($page - 1) * $state['size'];
        $rows = [];
        if ($offset < $filtered) {
            list($sql, $pageTypes, $pageValues) = pcrms_tf_page_sql($where, $types, $values, $motifs,
                $state['sort'], $direction, $state['size'], $offset);
            $result = pcrms_execute_statement($db, $sql, $pageTypes, $pageValues)->get_result();
            while ($row = $result->fetch_assoc()) {
                $row = pcrms_tf_site($row, $query, $motifs, $ranges);
                $rows[] = pcrms_site_row($row, $query['genome_id'], $row['Chromosome']);
            }
        }

        return ['total' => $total, 'filtered' => $filtered, 'page' => $page, 'rows' => $rows];
    }
}
