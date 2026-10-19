#!/usr/bin/env python3
"""Rewrite ProSampler motif files as standard MEME motif files.

Usage:
    python3 -I database/clean_meme.py IN_DIR OUT_DIR

IN_DIR holds ProSampler output, one motif per file, named
cluster_<number>.<...>.meme.bio. For each one OUT_DIR gets <number>.meme in
the MEME minimal motif format
(https://meme-suite.org/meme/doc/meme-format.html), plus all_motifs.meme with
every motif, for MEME-suite tools such as Tomtom and FIMO.

What changes:
- the "MEME version 4" and "strands: + -" lines are written as the format
  specifies (ProSampler capitalises them);
- the ProSampler command and run times, which name files on the cluster that
  made them, are left out;
- each motif is named by its number, which is its umID in the database and
  its logo's file name, with the consensus as the alternate name:
  "MOTIF 328 RCACWGAGCT";
- all_motifs.meme uses a uniform background, because each file has its own.

The matrix, its nsites and score, and each file's background are copied
unchanged. Stops with an error if a file does not hold exactly one motif or
its matrix does not match its header.
"""

import os
import re
import sys

FILE_NAME = re.compile(r'cluster_(\d+)\.')
MOTIF = re.compile(r'^MOTIF\s+(\S+)', re.M)
BACKGROUND = re.compile(r'^Background letter frequencies.*\n\s*(A\s+\S+\s+C\s+\S+\s+G\s+\S+\s+T\s+\S+)', re.M)
MATRIX = re.compile(r'^letter-probability matrix:(.*)$', re.M)

HEADER = 'MEME version 4\n\nALPHABET= ACGT\n\nstrands: + -\n\nBackground letter frequencies ({}):\n{}\n\n'


class MotifError(Exception):
    pass


def read_motif(path):
    """Returns (number, consensus, background, matrix header, rows)."""
    name = os.path.basename(path)
    number = FILE_NAME.match(name)
    if not number:
        raise MotifError('%s: the file name does not start with cluster_<number>.' % name)
    text = open(path).read()

    motifs = MOTIF.findall(text)
    if len(motifs) != 1:
        raise MotifError('%s: expected one motif, found %d' % (name, len(motifs)))
    consensus = motifs[0].rsplit('.', 1)[-1]

    background = BACKGROUND.search(text)
    if not background:
        raise MotifError('%s: no background letter frequencies' % name)
    background = ' '.join(background.group(1).split())

    matrix = MATRIX.search(text)
    if not matrix:
        raise MotifError('%s: no letter-probability matrix' % name)
    fields = dict(re.findall(r'(\w+)=\s*(\S+)', matrix.group(1)))
    if fields.get('alength') != '4' or 'w' not in fields:
        raise MotifError('%s: the matrix header needs alength= 4 and w=' % name)

    rows = []
    for line in text[matrix.end():].splitlines()[1:]:
        values = line.split()
        if not values:
            if rows:
                break
            continue
        if len(values) != 4:
            raise MotifError('%s: matrix row %r does not have 4 values' % (name, line))
        if abs(sum(float(value) for value in values) - 1) > 0.01:
            raise MotifError('%s: matrix row %r does not sum to 1' % (name, line))
        rows.append(values)
    if len(rows) != int(fields['w']):
        raise MotifError('%s: the header says w= %s but the matrix has %d rows' % (name, fields['w'], len(rows)))

    header = ' '.join('%s= %s' % item for item in fields.items())
    return int(number.group(1)), consensus, background, header, rows


def motif_text(number, consensus, header, rows):
    lines = ['MOTIF %d %s' % (number, consensus), '', 'letter-probability matrix: ' + header]
    lines += [' ' + '  '.join(row) for row in rows]
    return '\n'.join(lines) + '\n\n'


def main(argv):
    if len(argv) != 3:
        sys.exit(__doc__)
    in_dir, out_dir = argv[1], argv[2]
    names = sorted(name for name in os.listdir(in_dir) if name.endswith('.meme.bio'))
    if not names:
        sys.exit('No ProSampler .meme.bio files in %s' % in_dir)

    try:
        motifs = sorted(read_motif(os.path.join(in_dir, name)) for name in names)
    except MotifError as error:
        sys.exit('Stopped: %s' % error)
    numbers = [motif[0] for motif in motifs]
    if len(set(numbers)) != len(numbers):
        sys.exit('Stopped: two files have the same motif number')

    os.makedirs(out_dir, exist_ok=True)
    combined = HEADER.format('uniform background', 'A 0.25000 C 0.25000 G 0.25000 T 0.25000')
    for number, consensus, background, header, rows in motifs:
        text = motif_text(number, consensus, header, rows)
        with open(os.path.join(out_dir, '%d.meme' % number), 'w') as handle:
            handle.write(HEADER.format('from dataset', background) + text)
        combined += text
    with open(os.path.join(out_dir, 'all_motifs.meme'), 'w') as handle:
        handle.write(combined)
    print('%d motifs (numbers %d-%d) written to %s' % (len(motifs), numbers[0], numbers[-1], out_dir))


if __name__ == '__main__':
    main(sys.argv)
