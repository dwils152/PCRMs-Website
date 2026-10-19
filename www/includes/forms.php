<?php

// Form pieces shared by the browse and functional analysis pages. Each echoes
// markup; the inputs are real radios and checkboxes so the forms work without
// JavaScript, and js/forms.js adds the conveniences.

require_once __DIR__ . '/layout.php';

if (!function_exists('pcrms_step_legend')) {
    function pcrms_step_legend($number, $title, $level = 2)
    {
        echo '<legend class="step__head"><h' . $level . ' class="step__title"><span class="step__num" aria-hidden="true">'
            . (int) $number . '</span>' . pcrms_h($title) . '</h' . $level . '></legend>' . "\n";
    }
}

if (!function_exists('pcrms_genome_options')) {
    function pcrms_genome_options($selectedId)
    {
        global $PCRMS_GENOMES, $PCRMS_GENOME_ORDER;
        echo '<div class="genome-grid">' . "\n";
        foreach ($PCRMS_GENOME_ORDER as $id) {
            $genome = $PCRMS_GENOMES[$id];
            ?>
            <label class="option">
                <input type="radio" name="genome_id" value="<?php echo $id; ?>" data-name="<?php echo pcrms_h($genome['name']); ?>"
                    data-gene-example="<?php echo pcrms_h($genome['gene_example']); ?>" data-tf-example="<?php echo pcrms_h($genome['tf_example']); ?>"<?php echo $id === $selectedId ? ' checked' : ''; ?> required>
                <span class="genome-card">
                    <span class="genome-card__icon"><?php echo pcrms_svg('organisms/' . $genome['key']); ?></span>
                    <span class="genome-card__name"><?php echo pcrms_h($genome['common']); ?>
                        <span class="genome-card__latin"><?php echo pcrms_h($genome['name']); ?></span></span>
                    <span class="genome-card__meta"><?php echo pcrms_h($genome['assembly_short']); ?> · v<?php echo (int) $genome['version']; ?></span>
                </span>
            </label>
            <?php
        }
        echo "</div>\n";
    }
}

if (!function_exists('pcrms_chromosome_options')) {
    // One group of chips per genome. Without JavaScript every group is shown
    // with a heading; the handlers read the group that matches genome_id.
    function pcrms_chromosome_options($selectedId, $selectedChromosomes)
    {
        global $PCRMS_GENOMES, $PCRMS_GENOME_ORDER;
        ?>
        <div class="step__tools js-only">
            <span class="muted" data-chrom-count aria-live="polite"></span>
            <button class="link-button" type="button" data-chrom-action="all">Select all</button>
            <button class="link-button" type="button" data-chrom-action="none">Clear</button>
        </div>
        <?php
        foreach ($PCRMS_GENOME_ORDER as $id) {
            $genome = $PCRMS_GENOMES[$id];
            ?>
            <div class="chrom-group<?php echo $id === $selectedId ? ' is-current' : ''; ?>" data-genome="<?php echo $id; ?>">
                <p class="chrom-group__title"><?php echo pcrms_h($genome['common']); ?> (<?php echo pcrms_h($genome['assembly_short']); ?>)</p>
                <div class="chip-grid">
                    <?php foreach ($genome['chromosomes'] as $chromosome): ?>
                        <label class="option">
                            <input type="checkbox" name="chr_id[<?php echo $id; ?>][]" value="<?php echo pcrms_h($chromosome); ?>"
                                aria-label="<?php echo pcrms_h($chromosome); ?>"<?php echo $id === $selectedId && in_array($chromosome, $selectedChromosomes, true) ? ' checked' : ''; ?>>
                            <span class="chip"><?php echo pcrms_h(substr($chromosome, 3)); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
        }
    }
}

if (!function_exists('pcrms_choice_options')) {
    // A row of pill-shaped radios: $choices maps value => label.
    function pcrms_choice_options($name, $choices, $selected)
    {
        echo '<div class="choice-row">' . "\n";
        foreach ($choices as $value => $label) {
            echo '<label class="option"><input type="radio" name="' . pcrms_h($name) . '" value="' . pcrms_h($value) . '" data-label="'
                . pcrms_h($label) . '"' . ((string) $value === (string) $selected ? ' checked' : '') . ' required><span class="choice">'
                . pcrms_h($label) . "</span></label>\n";
        }
        echo "</div>\n";
    }
}

if (!function_exists('pcrms_selected_chromosomes')) {
    // Reads chr_id from a form post: either chr_id[<genome id>][] from the
    // current forms or a plain chr_id[] list.
    function pcrms_selected_chromosomes($genomeId, $input)
    {
        if (is_array($input) && isset($input[$genomeId]) && is_array($input[$genomeId])) {
            $input = $input[$genomeId];
        }
        if (!is_array($input) || !$input) {
            throw new InvalidArgumentException('Pick at least one chromosome.');
        }
        return pcrms_validate_chromosomes($genomeId, $input);
    }
}

if (!function_exists('pcrms_search_button')) {
    function pcrms_search_button($label)
    {
        ?>
        <div class="search-summary">
            <p class="js-only" data-summary aria-live="polite"></p>
            <button class="btn btn-primary btn-large" type="submit"><?php echo pcrms_h($label); ?>
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4"/></svg></button>
        </div>
        <?php
    }
}
