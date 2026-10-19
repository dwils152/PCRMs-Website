#!/usr/bin/env python3
"""Name the TFs each umotif matches, with Tomtom and four motif databases.

Usage:
    python3 -I database/match_motifs.py --species human --genome 1 --dataset 1 \\
        --umotifs Human-v2/umotif_clean/all_motifs.meme --out matches/human \\
        --jaspar JASPAR2026_CORE_vertebrates_non-redundant_pfms_meme.txt \\
        --hocomoco H14CORE_meme_format.meme --hocomoco-annotation H14CORE_annotation.jsonl \\
        --cisbp-pwms PWMs.zip --cisbp-info TF_Information_all_motifs.txt \\
        --uniprobe uniprobe_mouse.meme [--tomtom PATH] [--q 0.1] [--reuse-tomtom]

Needs Tomtom from the MEME suite (conda install -c conda-forge -c bioconda meme).
All four databases are searched in one Tomtom run, so the q-values account
for every comparison. Writes to OUT:

  targets/            the databases as Tomtom reads them (CIS-BP converted
                      from its PWM tables to MEME format)
  tomtom.tsv          Tomtom's own output: every match with E <= 10
  motif_matches.tsv   the same matches with database and TF names, for review
  motif_tfs.tsv       genome, dataset, motif number, TF name: one row per
                      motif-TF pair with q <= --q, ready for database/load.sql

--reuse-tomtom reads OUT/tomtom.tsv from an earlier run instead of running
Tomtom again, to try another --q in seconds.

TF names come from each database, in the species' gene-symbol style (CTCF
for human, Ctcf for mouse): JASPAR's profile names, with dimers such as
FOS::JUN counted for both TFs; HOCOMOCO's gene symbol for the species from
its annotation; CIS-BP's TF name for the species, from directly determined
motifs only (TF_Information_all_motifs.txt, from TF_Information.zip in the
bulk download); UniPROBE's name without _primary or _secondary. Tomtom runs
with the web server's settings: Pearson correlation, overlap of at least 5,
no small-sample correction.
"""

import argparse
import csv
import io
import json
import os
import re
import shutil
import subprocess
import sys
import zipfile

SPECIES = {'human': ('Homo_sapiens', 'HUMAN'), 'mouse': ('Mus_musculus', 'MOUSE')}

# JASPAR names that would be read as the wrong TF. MA0089 ("MAFG::NRF1") is
# MAFG with NFE2L1, also called Nrf1, not nuclear respiratory factor NRF1.
JASPAR_NAMES = {'MAFG::NRF1': 'MAFG::NFE2L1'}
MEME_HEADER = ('MEME version 4\n\nALPHABET= ACGT\n\nstrands: + -\n\n'
               'Background letter frequencies (from uniform background):\nA 0.25 C 0.25 G 0.25 T 0.25\n\n')


def symbol(name, species):
    """A TF name in the species' gene-symbol style."""
    name = re.sub(r'\(var\.\d+\)$', '', name.strip())
    if species == 'human':
        return re.sub(r'C(\d+)ORF(\d+)', r'C\1orf\2', name.upper())
    return name[:1].upper() + name[1:].lower()


def motif_names(path):
    """[(motif ID, alternate name)] from the MOTIF lines of a MEME file."""
    names = []
    for line in open(path):
        if line.startswith('MOTIF'):
            parts = line.split()
            names.append((parts[1], parts[2] if len(parts) > 2 else ''))
    return names


def jaspar_tfs(path, species):
    return {motif: [symbol(part, species) for part in JASPAR_NAMES.get(alt, alt).split('::')]
            for motif, alt in motif_names(path)}


def hocomoco_tfs(path, annotation, species):
    tfs = {}
    key = SPECIES[species][1]
    for line in open(annotation):
        record = json.loads(line)
        info = record['masterlist_info']['species'].get(key)
        if info and info.get('gene_symbol'):
            tfs[record['name']] = [info['gene_symbol']]
    return {motif: tfs.get(motif, []) for motif, _ in motif_names(path)}


def uniprobe_tfs(path, species):
    return {motif: [symbol(re.sub(r'_(primary|secondary).*$', '', alt), species)] for motif, alt in motif_names(path)}


def cisbp_targets(pwms_zip, info_path, species, out_path):
    """Writes the species' directly determined CIS-BP motifs as MEME; returns their TF names."""
    tfs = {}
    latin = SPECIES[species][0]
    with open(info_path, encoding='utf-8', errors='replace') as handle:
        header = handle.readline().rstrip('\n').split('\t')
        column = {name: header.index(name) for name in ('Motif_ID', 'TF_Name', 'TF_Species', 'TF_Status')}
        for line in handle:
            # The table covers every species; skip the others before splitting.
            if latin not in line:
                continue
            fields = line.rstrip('\n').split('\t')
            motif = fields[column['Motif_ID']]
            if fields[column['TF_Species']] == latin and fields[column['TF_Status']] == 'D' and motif not in ('', '.'):
                tfs.setdefault(motif, []).append(symbol(fields[column['TF_Name']], species))

    written = 0
    with zipfile.ZipFile(pwms_zip) as archive, open(out_path, 'w') as out:
        out.write(MEME_HEADER)
        members = {os.path.basename(name)[:-4]: name for name in archive.namelist() if name.endswith('.txt')}
        for motif in sorted(tfs):
            if motif not in members:
                continue
            rows = []
            for line in archive.read(members[motif]).decode().splitlines()[1:]:
                values = line.split('\t')[1:5]
                if len(values) == 4:
                    rows.append(values)
            if not rows:
                continue
            out.write('MOTIF %s %s\n\nletter-probability matrix: alength= 4 w= %d nsites= 20 E= 0\n' % (
                motif, '_'.join(sorted(set(tfs[motif]))), len(rows)))
            out.write(''.join(' ' + '  '.join(row) + '\n' for row in rows) + '\n')
            written += 1
    return {motif: sorted(set(names)) for motif, names in tfs.items()}, written


def main():
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    parser.add_argument('--species', required=True, choices=sorted(SPECIES))
    parser.add_argument('--genome', required=True, type=int)
    parser.add_argument('--dataset', required=True, type=int)
    parser.add_argument('--umotifs', required=True, help='MEME file of the umotifs, named by number')
    parser.add_argument('--out', required=True)
    parser.add_argument('--jaspar', required=True)
    parser.add_argument('--hocomoco', required=True)
    parser.add_argument('--hocomoco-annotation', required=True)
    parser.add_argument('--cisbp-pwms', required=True)
    parser.add_argument('--cisbp-info', required=True)
    parser.add_argument('--uniprobe', required=True)
    parser.add_argument('--tomtom', default=shutil.which('tomtom') or 'tomtom')
    parser.add_argument('--q', type=float, default=0.1, help='q-value cutoff for motif_tfs.tsv (default 0.1)')
    parser.add_argument('--reuse-tomtom', action='store_true', help='use OUT/tomtom.tsv from an earlier run')
    args = parser.parse_args()

    targets_dir = os.path.join(args.out, 'targets')
    os.makedirs(targets_dir, exist_ok=True)
    cisbp_path = os.path.join(targets_dir, 'cisbp_3.10_%s.meme' % SPECIES[args.species][0])
    cisbp, cisbp_count = cisbp_targets(args.cisbp_pwms, args.cisbp_info, args.species, cisbp_path)
    databases = [
        ('JASPAR 2026', args.jaspar, jaspar_tfs(args.jaspar, args.species)),
        ('HOCOMOCO v14', args.hocomoco, hocomoco_tfs(args.hocomoco, args.hocomoco_annotation, args.species)),
        ('CIS-BP 3.10', cisbp_path, cisbp),
        ('UniPROBE', args.uniprobe, uniprobe_tfs(args.uniprobe, args.species)),
    ]
    source = {}
    for name, path, tfs in databases:
        count = cisbp_count if name.startswith('CIS-BP') else len(tfs)
        print('%s: %d motifs' % (name, count))
        for motif in tfs:
            source[motif] = (name, tfs[motif])

    tomtom_path = os.path.join(args.out, 'tomtom.tsv')
    if args.reuse_tomtom:
        tomtom_output = open(tomtom_path).read()
    else:
        command = [args.tomtom, '-no-ssc', '-min-overlap', '5', '-dist', 'pearson', '-evalue', '-thresh', '10',
                   '-text', args.umotifs] + [path for _, path, _ in databases]
        result = subprocess.run(command, stdout=subprocess.PIPE, stderr=subprocess.PIPE, universal_newlines=True)
        if result.returncode != 0:
            sys.exit('Tomtom failed:\n' + result.stderr[-2000:])
        tomtom_output = result.stdout
        with open(tomtom_path, 'w') as handle:
            handle.write(tomtom_output)

    rows = [row for row in csv.DictReader(io.StringIO(tomtom_output), delimiter='\t')
            if row.get('Query_ID') and not row['Query_ID'].startswith('#')]
    pairs = set()
    with open(os.path.join(args.out, 'motif_matches.tsv'), 'w') as handle:
        handle.write('motif\tdatabase\ttarget\ttfs\tp_value\te_value\tq_value\toverlap\torientation\n')
        for row in sorted(rows, key=lambda r: (int(r['Query_ID']), float(r['q-value']), float(r['p-value']))):
            database, tfs = source.get(row['Target_ID'], ('?', []))
            handle.write('\t'.join([row['Query_ID'], database, row['Target_ID'], ', '.join(tfs), row['p-value'],
                                    row['E-value'], row['q-value'], row['Overlap'], row['Orientation']]) + '\n')
            if float(row['q-value']) <= args.q:
                for tf in tfs:
                    pairs.add((int(row['Query_ID']), tf))

    with open(os.path.join(args.out, 'motif_tfs.tsv'), 'w') as handle:
        for motif, tf in sorted(pairs):
            handle.write('%d\t%d\t%d\t%s\n' % (args.genome, args.dataset, motif, tf))
    print('%d Tomtom matches; %d motifs have at least one TF at q <= %g (%d motif-TF pairs)' % (
        len(rows), len({motif for motif, _ in pairs}), args.q, len(pairs)))


if __name__ == '__main__':
    main()
