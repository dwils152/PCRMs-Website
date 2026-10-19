(function () {
    'use strict';

    var IDENTIFIER = /^[A-Za-z0-9_.:-]{1,100}$/;
    var IDENTIFIER_ERROR = 'Use letters, numbers and . _ : - only, with no spaces.';
    var WHERE = { upstream: 'upstream of', downstream: 'downstream of', both: 'on either side of' };

    function checked(form, name) {
        return form.querySelector('input[name="' + name + '"]:checked');
    }

    function chromosomeText(selected, total) {
        if (selected.length === total) {
            return 'all chromosomes';
        }
        if (selected.length > 6) {
            return selected.length + ' chromosomes';
        }
        return selected.join(', ');
    }

    function setupForm(form) {
        var type = form.getAttribute('data-search-form');
        var summary = form.querySelector('[data-summary]');
        var button = form.querySelector('button[type="submit"]');
        var field = form.querySelector('[data-identifier]');
        var hint = field ? document.getElementById(field.getAttribute('aria-describedby')) : null;
        var count = form.querySelector('[data-chrom-count]');
        var figure = form.querySelector('[data-range-figure]');
        var touched = false;

        form.noValidate = true;

        function genome() {
            return checked(form, 'genome_id');
        }

        function currentGroup() {
            var input = genome();
            return input ? form.querySelector('.chrom-group[data-genome="' + input.value + '"]') : null;
        }

        function selectedChromosomes() {
            var group = currentGroup();
            if (!group) {
                return [];
            }
            return Array.prototype.map.call(group.querySelectorAll('input:checked'), function (input) {
                return input.value;
            });
        }

        // '' when empty, 'invalid' or 'ok'.
        function identifierState() {
            var value = field.value.trim();
            if (value === '') {
                return '';
            }
            return IDENTIFIER.test(value) ? 'ok' : 'invalid';
        }

        function showFieldMessage(message) {
            field.setAttribute('aria-invalid', message ? 'true' : 'false');
            hint.textContent = message || hint.getAttribute('data-hint');
            hint.classList.toggle('is-error', Boolean(message));
        }

        function updateFigure() {
            var location = checked(form, 'location').value;
            var distance = checked(form, 'distance');
            var width = parseInt(distance.value, 10) / 100 * 105;
            var label = distance.getAttribute('data-label');
            var up = location !== 'downstream' ? '1' : '0';
            var down = location !== 'upstream' ? '1' : '0';
            var parts = {};
            Array.prototype.forEach.call(figure.querySelectorAll('[data-part]'), function (node) {
                parts[node.getAttribute('data-part')] = node;
            });
            parts.up.setAttribute('x', 270 - width);
            parts.up.setAttribute('width', width);
            parts.up.setAttribute('opacity', up);
            parts.down.setAttribute('width', width);
            parts.down.setAttribute('opacity', down);
            parts['up-label'].setAttribute('x', 270 - width / 2);
            parts['up-label'].setAttribute('opacity', up);
            parts['up-label'].textContent = label + ' upstream';
            parts['down-label'].setAttribute('x', 332 + width / 2);
            parts['down-label'].setAttribute('opacity', down);
            parts['down-label'].textContent = label + ' downstream';
            parts.gene.textContent = identifierState() === 'ok' ? field.value.trim() : 'your gene';
            figure.setAttribute('aria-label', 'Search window: ' + label + ' ' + WHERE[location] + ' the gene');
        }

        // Works out whether the form can be sent and what the summary says.
        function describe() {
            var name = genome() ? genome().getAttribute('data-name') : '';
            var pValue = checked(form, 'p_value');
            var pText = pValue ? ' · p ≤ ' + pValue.getAttribute('data-label') : '';
            var chromosomes = selectedChromosomes();
            var group = currentGroup();
            var total = group ? group.querySelectorAll('input').length : 0;
            var value = field ? field.value.trim() : '';
            var state = field ? identifierState() : 'ok';

            if (type === 'browse') {
                if (!chromosomes.length) {
                    return { ok: false, lead: 'Pick at least one chromosome', rest: 'to search.' };
                }
                return { ok: true, lead: name, rest: '· ' + chromosomeText(chromosomes, total) + pText };
            }

            if (type === 'tf') {
                if (state !== 'ok') {
                    return { ok: false, lead: state === '' ? 'Enter a TF symbol' : 'Check the TF symbol', rest: 'to search.' };
                }
                if (!chromosomes.length) {
                    return { ok: false, lead: 'Pick at least one chromosome', rest: 'to search.' };
                }
                return { ok: true, lead: name, rest: '· ' + value + ' binding sites · ' + chromosomeText(chromosomes, total) };
            }

            if (state !== 'ok') {
                return { ok: false, lead: state === '' ? 'Enter a gene' : 'Check the gene name', rest: 'to search.' };
            }
            if (type === 'range') {
                var location = checked(form, 'location').value;
                var distance = checked(form, 'distance').getAttribute('data-label');
                return { ok: true, lead: name, rest: '· CRMs within ' + distance + ' ' + WHERE[location] + ' ' + value + pText };
            }
            return { ok: true, lead: name, rest: '· CRMs closest to ' + value + pText };
        }

        function update() {
            var group = currentGroup();
            Array.prototype.forEach.call(form.querySelectorAll('.chrom-group'), function (node) {
                node.classList.toggle('is-current', node === group);
            });
            if (count && group) {
                count.textContent = selectedChromosomes().length + ' of ' + group.querySelectorAll('input').length + ' selected';
            }
            if (field) {
                var state = identifierState();
                if (state === 'invalid') {
                    showFieldMessage(IDENTIFIER_ERROR);
                } else if (state === 'ok' || !touched) {
                    showFieldMessage('');
                }
            }
            if (figure) {
                updateFigure();
            }

            var result = describe();
            summary.textContent = '';
            var strong = document.createElement('strong');
            strong.textContent = result.lead;
            summary.appendChild(strong);
            summary.appendChild(document.createTextNode(' ' + result.rest));
            button.setAttribute('aria-disabled', result.ok ? 'false' : 'true');
            return result;
        }

        form.addEventListener('change', function (event) {
            if (event.target.name === 'genome_id') {
                var group = currentGroup();
                if (group && !group.querySelector('input:checked')) {
                    group.querySelector('input').checked = true;
                }
                if (field) {
                    field.placeholder = event.target.getAttribute(field.name === 'tf_id' ? 'data-tf-example' : 'data-gene-example');
                }
            }
            update();
        });

        if (field) {
            field.addEventListener('input', update);
            field.addEventListener('blur', function () {
                touched = true;
            });
        }

        Array.prototype.forEach.call(form.querySelectorAll('[data-chrom-action]'), function (control) {
            control.addEventListener('click', function () {
                var all = control.getAttribute('data-chrom-action') === 'all';
                Array.prototype.forEach.call(currentGroup().querySelectorAll('input'), function (input) {
                    input.checked = all;
                });
                update();
            });
        });

        form.addEventListener('submit', function (event) {
            var result = update();
            if (result.ok) {
                return;
            }
            event.preventDefault();
            if (field && identifierState() !== 'ok') {
                touched = true;
                showFieldMessage(identifierState() === '' ? 'Enter a ' + (field.name === 'tf_id' ? 'TF symbol' : 'gene symbol or ID') + '.' : IDENTIFIER_ERROR);
                field.focus();
            } else if (currentGroup()) {
                currentGroup().querySelector('input').focus();
            }
        });

        // Browsers restore form values when going back; refresh the derived text.
        window.addEventListener('pageshow', update);
        update();
    }

    Array.prototype.forEach.call(document.querySelectorAll('form[data-search-form]'), setupForm);

    // Analysis tabs switch panels in place and keep the address shareable.
    var tabs = document.querySelector('[data-tool-tabs]');
    if (tabs) {
        tabs.addEventListener('click', function (event) {
            var link = event.target.closest('a[data-tool]');
            if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
                return;
            }
            event.preventDefault();
            var tool = link.getAttribute('data-tool');
            Array.prototype.forEach.call(tabs.querySelectorAll('a[data-tool]'), function (other) {
                if (other === link) {
                    other.setAttribute('aria-current', 'true');
                } else {
                    other.removeAttribute('aria-current');
                }
            });
            Array.prototype.forEach.call(document.querySelectorAll('.tool-panel'), function (panel) {
                panel.classList.toggle('is-current', panel.getAttribute('data-tool') === tool);
            });
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', link.getAttribute('href'));
            }
        });
    }
}());
