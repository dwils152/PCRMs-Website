(function () {
    'use strict';

    // Redraws a results table (includes/results.php) in place. The page URL
    // keeps the table state, so a reload or shared link shows the same view.
    var card = document.querySelector('[data-results]');
    if (!card || !window.fetch || !window.JSON) {
        return;
    }

    var config = JSON.parse(card.getAttribute('data-results'));
    var columns = config.columns;
    var defaults = config.defaults;
    var state = config.state;
    var rowsNode = document.querySelector('[data-results-rows]');
    var allRows = config.mode === 'local' && rowsNode ? JSON.parse(rowsNode.textContent) : null;

    var form = card.querySelector('.filter-box');
    var filter = form.querySelector('input[type="search"]');
    var tableWrap = card.querySelector('[data-table]');
    var thead = card.querySelector('thead');
    var tbody = card.querySelector('tbody');
    var empty = card.querySelector('[data-empty]');
    var footer = card.querySelector('[data-footer]');
    var showing = card.querySelector('[data-showing]');
    var pager = card.querySelector('.pager');
    var csv = card.querySelector('[data-csv]');
    var csvNote = card.querySelector('[data-csv-note]');
    var errorBox = card.querySelector('[data-error]');
    var latest = 0;
    var timer = null;

    var EXTERNAL = '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M9 3h4v4M13 3 7 9M11 9.5V13H3V5h3.5"/></svg>'
        + '<span class="sr-only"> (UCSC Genome Browser, opens in a new tab)</span>';
    var SUPERSCRIPT = { '-': '⁻', '0': '⁰', '1': '¹', '2': '²', '3': '³', '4': '⁴', '5': '⁵', '6': '⁶', '7': '⁷', '8': '⁸', '9': '⁹' };

    function commas(value) {
        return Number(value).toLocaleString('en-US');
    }

    function formatScore(value) {
        return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Matches pcrms_format_p_value(): 0.05, 0.0012, 3.0 × 10⁻⁶.
    function formatP(value) {
        var p = Number(value);
        if (p >= 0.001) {
            return String(Number(p.toFixed(4)));
        }
        var parts = p.toExponential(1).split('e');
        return parts[0] + ' × 10' + String(parseInt(parts[1], 10)).split('').map(function (ch) {
            return SUPERSCRIPT[ch] || ch;
        }).join('');
    }

    function locationText(row) {
        return row.chrom + ':' + commas(row.start) + '–' + commas(row.end);
    }

    function link(href, text, className) {
        var a = document.createElement('a');
        a.href = href;
        a.textContent = text;
        if (className) {
            a.className = className;
        }
        return a;
    }

    function ucscLink(row, text) {
        var a = link(row.ucsc, text, 'external-link');
        a.target = '_blank';
        a.rel = 'noopener';
        a.title = 'Open in the UCSC Genome Browser';
        a.insertAdjacentHTML('beforeend', EXTERNAL);
        return a;
    }

    function muted(text) {
        var span = document.createElement('span');
        span.className = 'motif-missing';
        span.textContent = text;
        return span;
    }

    // Mirrors pcrms_results_cell().
    function cellContent(column, row) {
        var value = row[column.key];
        switch (column.type) {
        case 'ucsc':
            return ucscLink(row, value);
        case 'site':
            return ucscLink(row, locationText(row));
        case 'location':
            return document.createTextNode(locationText(row));
        case 'record':
            return link(row.record, value);
        case 'details':
            var details = link(row.record, 'View');
            var hidden = document.createElement('span');
            hidden.className = 'sr-only';
            hidden.textContent = ' binding sites of ' + row.id;
            details.appendChild(hidden);
            return details;
        case 'score':
            return document.createTextNode(formatScore(value));
        case 'p':
            return document.createTextNode(formatP(value));
        case 'logo':
            if (!value) {
                return muted('No logo');
            }
            var img = document.createElement('img');
            img.className = 'motif-logo';
            img.src = value;
            img.alt = 'Logo of motif ' + row.motif;
            img.width = 150;
            img.height = 30;
            img.loading = 'lazy';
            return img;
        default:
            return value === '' || value === null || value === undefined ? muted('—') : document.createTextNode(value);
        }
    }

    function cellClass(type) {
        if (type === 'score' || type === 'p') {
            return 'num';
        }
        return type === 'location' || type === 'site' ? 'nowrap' : '';
    }

    // Mirrors pcrms_results_url(): only non-default state goes in the address.
    function urlFor(changes) {
        var next = Object.assign({}, state, changes || {});
        var params = [];
        if (next.search !== '') {
            params.push('search=' + encodeURIComponent(next.search));
        }
        ['sort', 'dir', 'size'].forEach(function (key) {
            if (next[key] !== defaults[key]) {
                params.push(key + '=' + encodeURIComponent(next[key]));
            }
        });
        if (next.page > 1) {
            params.push('page=' + next.page);
        }
        return params.length ? config.url + '&' + params.join('&') : config.url;
    }

    function chromosomeKey(chrom) {
        var number = parseInt(chrom.slice(3), 10) || 0;
        return [number === 0 ? 1 : 0, number, chrom];
    }

    function compareKeys(a, b) {
        for (var i = 0; i < a.length; i += 1) {
            if (a[i] < b[i]) {
                return -1;
            }
            if (a[i] > b[i]) {
                return 1;
            }
        }
        return 0;
    }

    function byLocation(a, b) {
        return compareKeys(chromosomeKey(a.chrom), chromosomeKey(b.chrom)) || a.start - b.start || a.end - b.end;
    }

    // Mirrors pcrms_results_local().
    function localResult() {
        var needle = state.search.toLowerCase();
        var searchKeys = columns.filter(function (column) {
            return column.search;
        }).map(function (column) {
            return column.key;
        });
        var rows = needle === '' ? allRows.slice() : allRows.filter(function (row) {
            return searchKeys.some(function (key) {
                return String(row[key]).toLowerCase().indexOf(needle) !== -1;
            });
        });
        var type = columns.filter(function (column) {
            return column.key === state.sort;
        })[0].type;
        var sign = state.dir === 'desc' ? -1 : 1;
        rows.sort(function (a, b) {
            var order;
            if (type === 'location' || type === 'site') {
                order = byLocation(a, b);
            } else if (type === 'score' || type === 'p') {
                order = a[state.sort] - b[state.sort];
            } else {
                order = String(a[state.sort]).localeCompare(String(b[state.sort]), 'en', { numeric: true, sensitivity: 'base' });
            }
            return order !== 0 ? sign * order : byLocation(a, b);
        });
        var page = Math.min(state.page, Math.max(1, Math.ceil(rows.length / state.size)));
        return {
            total: allRows.length,
            filtered: rows.length,
            page: page,
            rows: rows.slice((page - 1) * state.size, page * state.size)
        };
    }

    function pageList(page, pages) {
        var list = [];
        var previous = 0;
        for (var n = 1; n <= pages; n += 1) {
            if (n === 1 || n === pages || Math.abs(n - page) <= 1) {
                if (n - previous > 1) {
                    list.push(null);
                }
                list.push(n);
                previous = n;
            }
        }
        return list;
    }

    function pagerButton(text, page, extra) {
        var node;
        if (page === null) {
            node = document.createElement('span');
            node.setAttribute('aria-disabled', 'true');
        } else {
            node = link(urlFor({ page: page }), '');
            node.setAttribute('data-page', page);
        }
        node.className = 'tbtn';
        node.textContent = text;
        Object.keys(extra || {}).forEach(function (name) {
            node.setAttribute(name, extra[name]);
        });
        return node;
    }

    function renderPager(pages) {
        pager.textContent = '';
        pager.appendChild(state.page > 1 ? pagerButton('‹ Prev', state.page - 1, { rel: 'prev' }) : pagerButton('‹ Prev', null));
        pageList(state.page, pages).forEach(function (n) {
            if (n === null) {
                var gap = document.createElement('span');
                gap.className = 'pager__gap';
                gap.setAttribute('aria-hidden', 'true');
                gap.textContent = '…';
                pager.appendChild(gap);
                return;
            }
            var extra = { 'aria-label': 'Page ' + n };
            if (n === state.page) {
                extra['aria-current'] = 'page';
            }
            pager.appendChild(pagerButton(String(n), n, extra));
        });
        pager.appendChild(state.page < pages ? pagerButton('Next ›', state.page + 1, { rel: 'next' }) : pagerButton('Next ›', null));
    }

    function render(result) {
        state.page = result.page;
        var pages = Math.max(1, Math.ceil(result.filtered / state.size));
        var hasRows = result.filtered > 0;
        tableWrap.hidden = !hasRows;
        footer.hidden = !hasRows;
        empty.hidden = hasRows;

        if (!hasRows) {
            empty.querySelector('h2').textContent = state.search !== ''
                ? 'No ' + config.noun[1] + ' match “' + state.search + '”'
                : config.empty[0];
            empty.querySelector('p').textContent = state.search !== '' ? 'Try a different search, or clear the filter.' : config.empty[1];
        }

        var fragment = document.createDocumentFragment();
        result.rows.forEach(function (row) {
            var tr = document.createElement('tr');
            columns.forEach(function (column, index) {
                var cell = document.createElement(index === 0 ? 'th' : 'td');
                if (index === 0) {
                    cell.scope = 'row';
                }
                var className = cellClass(column.type);
                if (className) {
                    cell.className = className;
                }
                cell.appendChild(cellContent(column, row));
                tr.appendChild(cell);
            });
            fragment.appendChild(tr);
        });
        tbody.textContent = '';
        tbody.appendChild(fragment);

        Array.prototype.forEach.call(thead.querySelectorAll('[data-sort]'), function (control) {
            var key = control.getAttribute('data-sort');
            var sorted = key === state.sort;
            control.parentNode.setAttribute('aria-sort', sorted ? (state.dir === 'asc' ? 'ascending' : 'descending') : 'none');
            control.querySelector('.sort-button__arrow').textContent = sorted ? (state.dir === 'asc' ? '▲' : '▼') : '↕';
            control.href = urlFor({ sort: key, dir: sorted && state.dir === 'asc' ? 'desc' : 'asc', page: 1 });
        });

        Array.prototype.forEach.call(card.querySelectorAll('[data-size]'), function (control) {
            var size = parseInt(control.getAttribute('data-size'), 10);
            if (size === state.size) {
                control.setAttribute('aria-current', 'true');
            } else {
                control.removeAttribute('aria-current');
            }
            control.href = urlFor({ size: size, page: 1 });
        });

        var first = hasRows ? (state.page - 1) * state.size + 1 : 0;
        var last = Math.min(state.page * state.size, result.filtered);
        showing.textContent = 'Showing ' + commas(first) + '–' + commas(last) + ' of ' + commas(result.filtered)
            + (state.search !== '' ? ' matching “' + state.search + '”' : '');
        renderPager(pages);

        var tooLarge = config.limit > 0 && result.filtered > config.limit;
        csv.href = state.search !== '' ? config.csv + '&search=' + encodeURIComponent(state.search) : config.csv;
        if (tooLarge) {
            csv.setAttribute('aria-disabled', 'true');
        } else {
            csv.removeAttribute('aria-disabled');
        }
        csvNote.hidden = !tooLarge;

        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', urlFor({}));
        }
    }

    function load() {
        errorBox.hidden = true;
        if (allRows) {
            render(localResult());
            return Promise.resolve();
        }
        var request = latest += 1;
        card.setAttribute('aria-busy', 'true');
        var params = 'page=' + state.page + '&size=' + state.size + '&sort=' + encodeURIComponent(state.sort)
            + '&dir=' + state.dir + '&search=' + encodeURIComponent(state.search);
        return fetch(config.endpoint + '&' + params, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (response) {
                return response.json().then(function (body) {
                    if (!response.ok || body.error) {
                        var error = new Error(body.error || 'Request failed');
                        error.fromServer = Boolean(body.error);
                        throw error;
                    }
                    return body;
                });
            })
            .then(function (body) {
                if (request === latest) {
                    render(body);
                }
            })
            .catch(function (error) {
                if (request === latest) {
                    errorBox.querySelector('p').textContent = error.fromServer
                        ? error.message
                        : 'The table could not be updated. Check your connection and try again.';
                    errorBox.hidden = false;
                }
            })
            .then(function () {
                if (request === latest) {
                    card.removeAttribute('aria-busy');
                }
            });
    }

    function change(changes, after) {
        Object.assign(state, changes);
        var done = load();
        if (after) {
            done.then(after);
        }
    }

    card.addEventListener('click', function (event) {
        var control = event.target.closest('a');
        if (!control || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        if (control.hasAttribute('data-csv')) {
            if (control.getAttribute('aria-disabled') === 'true') {
                event.preventDefault();
            }
            return;
        }
        if (control.hasAttribute('data-sort')) {
            event.preventDefault();
            var key = control.getAttribute('data-sort');
            change({ sort: key, dir: key === state.sort && state.dir === 'asc' ? 'desc' : 'asc', page: 1 });
        } else if (control.hasAttribute('data-size')) {
            event.preventDefault();
            change({ size: parseInt(control.getAttribute('data-size'), 10), page: 1 });
        } else if (control.hasAttribute('data-page')) {
            event.preventDefault();
            change({ page: parseInt(control.getAttribute('data-page'), 10) }, function () {
                var current = pager.querySelector('[aria-current="page"]');
                if (current) {
                    current.focus();
                }
                if (card.getBoundingClientRect().top < 0) {
                    card.scrollIntoView({ block: 'start' });
                }
            });
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        window.clearTimeout(timer);
        change({ search: filter.value.trim(), page: 1 });
    });

    filter.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(function () {
            var search = filter.value.trim();
            if (search !== state.search) {
                change({ search: search, page: 1 });
            }
        }, 300);
    });
}());
