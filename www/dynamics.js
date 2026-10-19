(function () {
    'use strict';

    var catalog = window.PCRMS_CATALOG || {};
    var chromosomes = {};

    Object.keys(catalog).forEach(function (genomeId) {
        chromosomes[genomeId] = catalog[genomeId].chromosomes || [];
    });

    window.PCRMS_CHROMOSOMES = chromosomes;

    function initialiseChromosomePicker() {
        var genomePicker = document.getElementById('genome_id');
        var chromosomePicker = document.getElementById('chr_id');

        if (!genomePicker || !chromosomePicker) {
            return;
        }

        var initiallySelected = Array.prototype.map.call(
            chromosomePicker.selectedOptions || [],
            function (option) { return option.value; }
        ).filter(Boolean);

        function populateChromosomes(selectedValues) {
            var selectedChromosomes = chromosomes[genomePicker.value] || [];
            chromosomePicker.innerHTML = '';

            if (!selectedChromosomes.length) {
                chromosomePicker.add(new Option('Select an organism first', ''));
                chromosomePicker.disabled = true;
                return;
            }

            selectedChromosomes.forEach(function (chromosome) {
                var option = new Option(chromosome, chromosome);
                option.selected = selectedValues.indexOf(chromosome) !== -1;
                chromosomePicker.add(option);
            });
            chromosomePicker.disabled = false;
        }

        genomePicker.addEventListener('change', function () {
            populateChromosomes([]);
        });

        if (genomePicker.value) {
            populateChromosomes(initiallySelected);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiseChromosomePicker);
    } else {
        initialiseChromosomePicker();
    }
}());
