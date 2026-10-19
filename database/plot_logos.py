#!/usr/bin/env python3
"""Draw a sequence logo for each motif in MEME files, as SVG, with logomaker.

Usage:
    python3 database/plot_logos.py OUT_DIR MEME_FILE [MEME_FILE ...]

Needs logomaker (pip install logomaker). Every motif in the MEME files
becomes OUT_DIR/<motif name>.svg. Letter heights are in bits: the information
content of each position against the file's background letter frequencies,
as MEME's own logos show it. Letters use MEME's colours (A red, C blue,
G orange, T green) and are drawn as outlines, so the files need no fonts.
The same input always gives byte-identical files.

For the website, run it on the cleaned motif files (database/clean_meme.py)
and write to www/motifs_version/<genome ID>/:

    python3 database/plot_logos.py www/motifs_version/1 Human-v2/umotif_clean/*.meme
"""

import math
import os
import re
import sys

import matplotlib

matplotlib.use('Agg')
import matplotlib.pyplot as plt  # noqa: E402
import logomaker  # noqa: E402
import pandas as pd  # noqa: E402

LETTERS = ['A', 'C', 'G', 'T']
COLOURS = {'A': '#CC0000', 'C': '#0000CC', 'G': '#FFB300', 'T': '#008000'}

plt.rcParams['svg.hashsalt'] = 'pcrms'
plt.rcParams['svg.fonttype'] = 'path'
plt.rcParams['font.family'] = 'DejaVu Sans'


def read_meme(path):
    """Yields (name, background, rows) for each motif in a MEME file."""
    text = open(path).read()
    background = dict.fromkeys(LETTERS, 0.25)
    found = re.search(r'^Background letter frequencies.*\n\s*(.+)$', text, re.M)
    if found:
        values = found.group(1).split()
        background = {values[i]: float(values[i + 1]) for i in range(0, len(values) - 1, 2)}

    for block in re.split(r'^MOTIF\s+', text, flags=re.M)[1:]:
        name = block.split()[0]
        matrix = re.search(r'letter-probability matrix:.*?\bw=\s*(\d+).*\n', block)
        if not matrix:
            continue
        lines = block[matrix.end():].splitlines()
        rows = [[float(value) for value in line.split()] for line in lines[:int(matrix.group(1))]]
        yield name, background, rows


def draw(rows, background, path):
    probabilities = pd.DataFrame(rows, columns=LETTERS, index=range(1, len(rows) + 1))
    # Rows are rounded to four decimals; make each sum to exactly 1.
    probabilities = probabilities.div(probabilities.sum(axis=1), axis=0)
    bits = logomaker.transform_matrix(probabilities, from_type='probability', to_type='information',
                                      background=[background[letter] for letter in LETTERS])

    width = len(rows)
    figure, axes = plt.subplots(figsize=(0.32 * width + 0.6, 1.5))
    logomaker.Logo(bits, ax=axes, color_scheme=COLOURS, font_name='DejaVu Sans',
                   font_weight='bold', stack_order='big_on_top', vpad=0.02)

    top = max(2.0, math.ceil(bits.sum(axis=1).max() * 10) / 10)
    axes.set_ylim(0, top)
    axes.set_yticks([0, 1, 2])
    axes.set_ylabel('bits', fontsize=9)
    axes.set_xticks(range(1, width + 1))
    axes.tick_params(labelsize=8, length=2)
    for side in ('top', 'right'):
        axes.spines[side].set_visible(False)
    figure.tight_layout(pad=0.2)
    figure.savefig(path, format='svg', metadata={'Date': None})
    plt.close(figure)


def main(argv):
    if len(argv) < 3:
        sys.exit(__doc__)
    out_dir = argv[1]
    os.makedirs(out_dir, exist_ok=True)
    count = 0
    for path in argv[2:]:
        for name, background, rows in read_meme(path):
            if not re.match(r'^[A-Za-z0-9_.-]+$', name):
                sys.exit('Stopped: motif name %r in %s cannot be a file name' % (name, path))
            draw(rows, background, os.path.join(out_dir, name + '.svg'))
            count += 1
    print('%d logos written to %s' % (count, out_dir))


if __name__ == '__main__':
    main(sys.argv)
