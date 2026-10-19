<?php

// Results tables shared by the browse, functional analysis and CRM pages.
//
// The table state (page, size, sort, dir, search) lives in the page URL, so
// paging, sorting and filtering work as plain links without JavaScript.
// js/results.js reads the same state and redraws the table in place, either
// from a JSON endpoint ('remote' tables) or from rows embedded in the page
// ('local' tables, used for small result sets).

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/queries.php';

const PCRMS_PAGE_SIZES = [25, 50, 100];

// Tables with more rows than this page from the server instead of sending
// every row with the page.
const PCRMS_LOCAL_ROWS_MAX = 1000;

// Column types: ucsc (row ID linking to UCSC), site (location linking to
// UCSC), location, record (link to the CRM page), details (a "Details" link to
// the CRM page), text, score, p and logo. The first column is the row header.
$PCRMS_RESULT_COLUMNS = [
    'crms' => [
        ['key' => 'id', 'label' => 'CRM ID', 'type' => 'ucsc', 'sort' => true, 'search' => true],
        ['key' => 'location', 'label' => 'Location', 'type' => 'location', 'sort' => true],
        ['key' => 'genes', 'label' => 'Closest gene(s)', 'type' => 'text', 'sort' => true, 'search' => true],
        ['key' => 'score', 'label' => 'Score', 'type' => 'score', 'sort' => true],
        ['key' => 'p', 'label' => 'p-value', 'type' => 'p', 'sort' => true],
        ['key' => 'record', 'label' => 'Binding sites', 'type' => 'details'],
    ],
    'gene-crms' => [
        ['key' => 'id', 'label' => 'CRM ID', 'type' => 'ucsc', 'sort' => true, 'search' => true],
        ['key' => 'location', 'label' => 'Location', 'type' => 'location', 'sort' => true],
        ['key' => 'score', 'label' => 'Score', 'type' => 'score', 'sort' => true],
        ['key' => 'p', 'label' => 'p-value', 'type' => 'p', 'sort' => true],
        ['key' => 'record', 'label' => 'Binding sites', 'type' => 'details'],
    ],
    'tf-sites' => [
        ['key' => 'site', 'label' => 'Binding site', 'type' => 'site', 'sort' => true],
        ['key' => 'crm', 'label' => 'CRM', 'type' => 'record', 'sort' => true, 'search' => true],
        ['key' => 'tf', 'label' => 'TF', 'type' => 'text', 'sort' => true, 'search' => true],
        ['key' => 'motif', 'label' => 'Motif', 'type' => 'text', 'sort' => true, 'search' => true],
        ['key' => 'logo', 'label' => 'Motif logo', 'type' => 'logo'],
        ['key' => 'score', 'label' => 'Binding score', 'type' => 'score', 'sort' => true],
    ],
    'crm-sites' => [
        ['key' => 'site', 'label' => 'Binding site', 'type' => 'site', 'sort' => true],
        ['key' => 'motif', 'label' => 'Motif', 'type' => 'text', 'sort' => true, 'search' => true],
        ['key' => 'tf', 'label' => 'Matched TFs', 'type' => 'text', 'sort' => true, 'search' => true],
        ['key' => 'logo', 'label' => 'Motif logo', 'type' => 'logo'],
        ['key' => 'score', 'label' => 'Binding score', 'type' => 'score', 'sort' => true],
    ],
];

// Default sort and page size for each table.
$PCRMS_RESULT_DEFAULTS = [
    'crms' => ['sort' => 'location', 'dir' => 'asc', 'size' => 25],
    'gene-crms' => ['sort' => 'location', 'dir' => 'asc', 'size' => 25],
    'tf-sites' => ['sort' => 'site', 'dir' => 'asc', 'size' => 25],
    'crm-sites' => ['sort' => 'site', 'dir' => 'asc', 'size' => 25],
];

if (!function_exists('pcrms_results_state')) {
    // Reads the table state from the query string, falling back to defaults.
    function pcrms_results_state($columns, $defaults)
    {
        $sortable = [];
        foreach ($columns as $column) {
            if (!empty($column['sort'])) {
                $sortable[] = $column['key'];
            }
        }
        $state = $defaults;
        if (isset($_GET['sort']) && is_string($_GET['sort']) && in_array($_GET['sort'], $sortable, true)) {
            $state['sort'] = $_GET['sort'];
        }
        if (isset($_GET['dir']) && in_array($_GET['dir'], ['asc', 'desc'], true)) {
            $state['dir'] = $_GET['dir'];
        }
        $size = isset($_GET['size']) ? filter_var($_GET['size'], FILTER_VALIDATE_INT) : false;
        if ($size !== false && in_array($size, PCRMS_PAGE_SIZES, true)) {
            $state['size'] = $size;
        }
        $page = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_VALIDATE_INT) : false;
        $state['page'] = $page !== false && $page > 0 ? min($page, 1000000) : 1;
        $state['search'] = isset($_GET['search']) && is_string($_GET['search']) ? pcrms_search_value($_GET['search']) : '';
        return $state;
    }
}

if (!function_exists('pcrms_results_url')) {
    // $base already carries the result-set token; only non-default state is added.
    function pcrms_results_url($base, $state, $defaults, $changes = [])
    {
        $state = array_merge($state, $changes);
        $params = [];
        if ($state['search'] !== '') {
            $params['search'] = $state['search'];
        }
        foreach (['sort', 'dir', 'size'] as $key) {
            if ($state[$key] !== $defaults[$key]) {
                $params[$key] = $state[$key];
            }
        }
        if ($state['page'] > 1) {
            $params['page'] = $state['page'];
        }
        return $params ? $base . '&' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : $base;
    }
}

if (!function_exists('pcrms_chromosome_sort_key')) {
    // chr1 … chr22 in number order first, then the rest by name.
    function pcrms_chromosome_sort_key($chromosome)
    {
        $number = (int) substr($chromosome, 3);
        return [$number === 0 ? 1 : 0, $number, $chromosome];
    }
}

if (!function_exists('pcrms_results_local')) {
    // Filters, sorts and pages rows held in memory: ['total', 'filtered', 'page', 'rows'].
    function pcrms_results_local($rows, $columns, $state)
    {
        $types = [];
        $searchKeys = [];
        foreach ($columns as $column) {
            $types[$column['key']] = $column['type'];
            if (!empty($column['search'])) {
                $searchKeys[] = $column['key'];
            }
        }

        $total = count($rows);
        if ($state['search'] !== '') {
            $needle = function_exists('mb_strtolower') ? mb_strtolower($state['search'], 'UTF-8') : strtolower($state['search']);
            $rows = array_values(array_filter($rows, function ($row) use ($searchKeys, $needle) {
                foreach ($searchKeys as $key) {
                    $value = function_exists('mb_strtolower') ? mb_strtolower($row[$key], 'UTF-8') : strtolower($row[$key]);
                    if (strpos($value, $needle) !== false) {
                        return true;
                    }
                }
                return false;
            }));
        }

        $byLocation = function ($a, $b) {
            $order = pcrms_chromosome_sort_key($a['chrom']) <=> pcrms_chromosome_sort_key($b['chrom']);
            return $order !== 0 ? $order : ($a['start'] <=> $b['start'] ?: $a['end'] <=> $b['end']);
        };
        $key = $state['sort'];
        $type = $types[$key];
        $sign = $state['dir'] === 'desc' ? -1 : 1;
        usort($rows, function ($a, $b) use ($key, $type, $sign, $byLocation) {
            if ($type === 'location' || $type === 'site') {
                $order = $byLocation($a, $b);
            } elseif ($type === 'score' || $type === 'p') {
                $order = $a[$key] <=> $b[$key];
            } else {
                $order = strnatcasecmp($a[$key], $b[$key]);
            }
            return $order !== 0 ? $sign * $order : $byLocation($a, $b);
        });

        $filtered = count($rows);
        $page = min($state['page'], max(1, (int) ceil($filtered / $state['size'])));
        return [
            'total' => $total,
            'filtered' => $filtered,
            'page' => $page,
            'rows' => array_slice($rows, ($page - 1) * $state['size'], $state['size']),
            'all' => $rows,
        ];
    }
}

if (!function_exists('pcrms_results_cell')) {
    function pcrms_results_cell($column, $row)
    {
        $external = '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M9 3h4v4M13 3 7 9M11 9.5V13H3V5h3.5"/></svg>'
            . '<span class="sr-only"> (UCSC Genome Browser, opens in a new tab)</span>';
        $location = $row['chrom'] . ':' . number_format($row['start']) . '–' . number_format($row['end']);
        $value = isset($row[$column['key']]) ? $row[$column['key']] : '';

        switch ($column['type']) {
            case 'ucsc':
                return '<a class="external-link" href="' . pcrms_h($row['ucsc']) . '" target="_blank" rel="noopener" title="Open in the UCSC Genome Browser">'
                    . pcrms_h($value) . $external . '</a>';
            case 'site':
                return '<a class="external-link" href="' . pcrms_h($row['ucsc']) . '" target="_blank" rel="noopener" title="Open in the UCSC Genome Browser">'
                    . pcrms_h($location) . $external . '</a>';
            case 'location':
                return pcrms_h($location);
            case 'record':
                return '<a href="' . pcrms_h($row['record']) . '">' . pcrms_h($value) . '</a>';
            case 'details':
                return '<a href="' . pcrms_h($row['record']) . '">View<span class="sr-only"> binding sites of ' . pcrms_h($row['id']) . '</span></a>';
            case 'score':
                return number_format((float) $value, 2);
            case 'p':
                return pcrms_h(pcrms_format_p_value($value));
            case 'logo':
                return $value === ''
                    ? '<span class="motif-missing">No logo</span>'
                    : '<img class="motif-logo" src="' . pcrms_h($value) . '" alt="Logo of motif ' . pcrms_h($row['motif']) . '" width="150" height="30" loading="lazy">';
            default:
                return $value === '' ? '<span class="motif-missing">—</span>' : pcrms_h($value);
        }
    }
}

if (!function_exists('pcrms_results_cell_class')) {
    function pcrms_results_cell_class($type)
    {
        if ($type === 'score' || $type === 'p') {
            return 'num';
        }
        return $type === 'location' || $type === 'site' ? 'nowrap' : '';
    }
}

if (!function_exists('pcrms_results_pages')) {
    // Page numbers to show, with null marking a gap: 1 … 4 5 6 … 20.
    function pcrms_results_pages($page, $pages)
    {
        $list = [];
        $previous = 0;
        for ($n = 1; $n <= $pages; $n++) {
            if ($n === 1 || $n === $pages || abs($n - $page) <= 1) {
                if ($n - $previous > 1) {
                    $list[] = null;
                }
                $list[] = $n;
                $previous = $n;
            }
        }
        return $list;
    }
}

if (!function_exists('pcrms_results_card')) {
    /**
     * Renders a results table. Options:
     *   columns, defaults (sort, dir, size), state, result (total, filtered, page, rows),
     *   url (this page with its result-set token), endpoint (JSON URL, remote tables),
     *   all_rows (every row, local tables), csv (download URL), caption,
     *   filter_label, noun ([singular, plural]), empty ([title, text]), limit (CSV rows, 0 for none).
     */
    function pcrms_results_card($options)
    {
        $columns = $options['columns'];
        $state = $options['state'];
        $defaults = $options['defaults'];
        $result = $options['result'];
        $url = $options['url'];
        $pages = max(1, (int) ceil($result['filtered'] / $state['size']));
        $state['page'] = $result['page'];
        $limit = isset($options['limit']) ? (int) $options['limit'] : 0;
        $tooLarge = $limit > 0 && $result['filtered'] > $limit;
        $csv = $state['search'] !== '' ? $options['csv'] . '&search=' . rawurlencode($state['search']) : $options['csv'];

        parse_str((string) parse_url($url, PHP_URL_QUERY), $hidden);
        $config = [
            'mode' => isset($options['endpoint']) ? 'remote' : 'local',
            'endpoint' => isset($options['endpoint']) ? $options['endpoint'] : null,
            'url' => $url,
            'csv' => $options['csv'],
            'columns' => $columns,
            'defaults' => $defaults,
            'state' => $state,
            'total' => $result['total'],
            'filtered' => $result['filtered'],
            'noun' => $options['noun'],
            'empty' => $options['empty'],
            'limit' => $limit,
        ];
        $first = $result['filtered'] ? ($state['page'] - 1) * $state['size'] + 1 : 0;
        $last = min($state['page'] * $state['size'], $result['filtered']);
        ?>
        <section class="results-card" aria-label="Results" data-results="<?php echo pcrms_h(json_encode($config, JSON_UNESCAPED_UNICODE)); ?>">
            <div class="results-toolbar">
                <form class="filter-box" method="get" action="<?php echo pcrms_h(strtok($url, '?')); ?>" role="search">
                    <label class="sr-only" for="results-filter"><?php echo pcrms_h($options['filter_label']); ?></label>
                    <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="9" cy="9" r="6"/><path d="m17 17-3.6-3.6"/></svg>
                    <?php foreach ($hidden as $name => $value): ?>
                        <input type="hidden" name="<?php echo pcrms_h($name); ?>" value="<?php echo pcrms_h($value); ?>">
                    <?php endforeach; ?>
                    <?php foreach (['sort', 'dir', 'size'] as $name): ?>
                        <?php if ($state[$name] !== $defaults[$name]): ?>
                            <input type="hidden" name="<?php echo $name; ?>" value="<?php echo pcrms_h($state[$name]); ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <input id="results-filter" type="search" name="search" value="<?php echo pcrms_h($state['search']); ?>"
                        placeholder="<?php echo pcrms_h($options['filter_label']); ?>" autocomplete="off" spellcheck="false" maxlength="100">
                </form>
                <div class="toolbar-group">
                    <div class="size-group" role="group" aria-label="Rows per page">
                        <span aria-hidden="true">Rows</span>
                        <?php foreach (PCRMS_PAGE_SIZES as $size): ?>
                            <a class="tbtn" href="<?php echo pcrms_h(pcrms_results_url($url, $state, $defaults, ['size' => $size, 'page' => 1])); ?>"
                                data-size="<?php echo $size; ?>" aria-label="<?php echo $size; ?> rows per page"<?php echo $size === $state['size'] ? ' aria-current="true"' : ''; ?>><?php echo $size; ?></a>
                        <?php endforeach; ?>
                    </div>
                    <a class="tbtn" data-csv href="<?php echo pcrms_h($csv); ?>"<?php echo $tooLarge ? ' aria-disabled="true"' : ''; ?>>
                        <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M10 3v10m-4-4 4 4 4-4M4 17h12"/></svg>Download CSV</a>
                </div>
            </div>
            <p class="results-note" data-csv-note<?php echo $tooLarge ? '' : ' hidden'; ?>>This result has more than <?php echo number_format($limit); ?> rows, too many to download as CSV. Narrow the search, or get the genome-wide files from <a href="download_data.php">Downloads</a>.</p>

            <div class="table-scroll" data-table<?php echo $result['filtered'] ? '' : ' hidden'; ?>>
                <table class="results-table">
                    <caption class="sr-only"><?php echo pcrms_h($options['caption']); ?></caption>
                    <thead>
                        <tr>
                            <?php foreach ($columns as $column): ?>
                                <?php
                                $class = pcrms_results_cell_class($column['type']);
                                $sorted = $state['sort'] === $column['key'];
                                $nextDir = $sorted && $state['dir'] === 'asc' ? 'desc' : 'asc';
                                ?>
                                <th scope="col"<?php echo $class ? ' class="' . $class . '"' : ''; ?><?php echo !empty($column['sort']) ? ' aria-sort="' . ($sorted ? ($state['dir'] === 'asc' ? 'ascending' : 'descending') : 'none') . '"' : ''; ?>>
                                    <?php if (!empty($column['sort'])): ?>
                                        <a class="sort-button" data-sort="<?php echo pcrms_h($column['key']); ?>"
                                            href="<?php echo pcrms_h(pcrms_results_url($url, $state, $defaults, ['sort' => $column['key'], 'dir' => $nextDir, 'page' => 1])); ?>"><span><?php echo pcrms_h($column['label']); ?></span><span class="sort-button__arrow" aria-hidden="true"><?php echo $sorted ? ($state['dir'] === 'asc' ? '▲' : '▼') : '↕'; ?></span></a>
                                    <?php else: ?>
                                        <?php echo pcrms_h($column['label']); ?>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['rows'] as $row): ?>
                            <tr>
                                <?php foreach ($columns as $index => $column): ?>
                                    <?php
                                    $class = pcrms_results_cell_class($column['type']);
                                    $tag = $index === 0 ? 'th scope="row"' : 'td';
                                    ?>
                                    <<?php echo $tag; ?><?php echo $class ? ' class="' . $class . '"' : ''; ?>><?php echo pcrms_results_cell($column, $row); ?></<?php echo $index === 0 ? 'th' : 'td'; ?>>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="empty-state" data-empty role="status"<?php echo $result['filtered'] ? ' hidden' : ''; ?>>
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5M8.5 11h5"/></svg>
                <?php if ($state['search'] !== ''): ?>
                    <h2>No <?php echo pcrms_h($options['noun'][1]); ?> match “<?php echo pcrms_h($state['search']); ?>”</h2>
                    <p>Try a different search, or clear the filter.</p>
                <?php else: ?>
                    <h2><?php echo pcrms_h($options['empty'][0]); ?></h2>
                    <p><?php echo pcrms_h($options['empty'][1]); ?></p>
                <?php endif; ?>
            </div>

            <div class="results-footer" data-footer<?php echo $result['filtered'] ? '' : ' hidden'; ?>>
                <p data-showing aria-live="polite">Showing <?php echo number_format($first); ?>–<?php echo number_format($last); ?> of <?php echo number_format($result['filtered']); ?><?php echo $state['search'] !== '' ? ' matching “' . pcrms_h($state['search']) . '”' : ''; ?></p>
                <nav class="pager" aria-label="Results pages">
                    <?php if ($state['page'] > 1): ?>
                        <a class="tbtn" data-page="<?php echo $state['page'] - 1; ?>" href="<?php echo pcrms_h(pcrms_results_url($url, $state, $defaults, ['page' => $state['page'] - 1])); ?>" rel="prev">‹ Prev</a>
                    <?php else: ?>
                        <span class="tbtn" aria-disabled="true">‹ Prev</span>
                    <?php endif; ?>
                    <?php foreach (pcrms_results_pages($state['page'], $pages) as $n): ?>
                        <?php if ($n === null): ?>
                            <span class="pager__gap" aria-hidden="true">…</span>
                        <?php else: ?>
                            <a class="tbtn" data-page="<?php echo $n; ?>" href="<?php echo pcrms_h(pcrms_results_url($url, $state, $defaults, ['page' => $n])); ?>"
                                aria-label="Page <?php echo $n; ?>"<?php echo $n === $state['page'] ? ' aria-current="page"' : ''; ?>><?php echo $n; ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($state['page'] < $pages): ?>
                        <a class="tbtn" data-page="<?php echo $state['page'] + 1; ?>" href="<?php echo pcrms_h(pcrms_results_url($url, $state, $defaults, ['page' => $state['page'] + 1])); ?>" rel="next">Next ›</a>
                    <?php else: ?>
                        <span class="tbtn" aria-disabled="true">Next ›</span>
                    <?php endif; ?>
                </nav>
            </div>
            <div class="alert" data-error role="alert" hidden>
                <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="8"/><path d="M10 6v5M10 14h.01"/></svg>
                <p>The table could not be updated. Check your connection and try again.</p>
            </div>
        </section>
        <?php if (isset($options['all_rows'])): ?>
            <script type="application/json" data-results-rows><?php echo json_encode($options['all_rows'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); ?></script>
        <?php endif; ?>
        <?php
    }
}

if (!function_exists('pcrms_results_band')) {
    // Results page header: breadcrumb, headline, search tags and "Edit search".
    function pcrms_results_band($crumb, $headline, $tags, $editUrl)
    {
        ?>
        <section class="band" aria-labelledby="page-title">
            <div class="band__inner shell band__row">
                <div>
                    <p class="breadcrumb"><a href="<?php echo pcrms_h($crumb[1]); ?>"><?php echo pcrms_h($crumb[0]); ?></a> <span aria-hidden="true">/</span> Results</p>
                    <h1 id="page-title"><?php echo pcrms_h($headline); ?></h1>
                    <ul class="tags" aria-label="Search">
                        <?php foreach ($tags as $tag): ?>
                            <li><?php echo $tag; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <a class="btn btn-on-pine" href="<?php echo pcrms_h($editUrl); ?>">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M13.5 3.5l3 3L7 16H4v-3z"/></svg>Edit search</a>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('pcrms_results_csv')) {
    // Streams rows as CSV. $fields maps a CSV header to [row key, is text].
    function pcrms_results_csv($filename, $fields, $rows)
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo implode(',', array_keys($fields)) . "\n";
        foreach ($rows as $row) {
            $cells = [];
            foreach ($fields as $field) {
                $cells[] = pcrms_csv_cell($row[$field[0]], $field[1]);
            }
            echo implode(',', $cells) . "\n";
        }
        exit;
    }
}

if (!function_exists('pcrms_genome_tag')) {
    function pcrms_genome_tag($genomeId)
    {
        global $PCRMS_GENOMES;
        $genome = $PCRMS_GENOMES[$genomeId];
        return '<em>' . pcrms_h($genome['name']) . '</em> · ' . pcrms_h($genome['assembly_short']);
    }
}
