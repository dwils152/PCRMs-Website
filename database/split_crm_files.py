#!/usr/bin/env python3
"""Split PCRMs CRM files into one tab-separated file per database table.

Usage:
    python3 -I database/split_crm_files.py OUT_DIR GENOME[:DATASET]=FILE ...

GENOME and DATASET are the IDs in the genomes and datasets tables; DATASET
defaults to 1. Each FILE is one dataset's CRM file as released (plain or
gzipped), one CRM per line:

    chromosome  start  end  binding sites  CRM score  P-value

with the binding sites written as comma-separated
motif|start:end+binding score+motif score.

Writes chromosomes.tsv, crms.tsv, umotifs.tsv and tfbss.tsv to OUT_DIR, in
the column order of database/schema.sql and sorted by primary key, ready for
database/load.sql. CRM IDs are numbered in genomic order (genome, dataset,
chromosome, start); motif IDs in the order the motifs first appear.

Stops with an error when the data breaks a schema rule: a missing or invalid
P-value, a duplicate CRM or binding site, a site outside its CRM, or a motif
with more than one width or motif score.
"""

import gzip
import os
import re
import shutil
import sys

SITE = re.compile(r'(\d+)\|(\d+):(\d+)\+([^+]+)\+([^+]+)$')
CHROMOSOME = re.compile(r'chr(\d+)(.*)$')


class DataError(Exception):
    pass


def chromosome_key(name):
    """chr1, chr2 ... chr10, then chrX, chrY and other names."""
    match = CHROMOSOME.match(name)
    if match:
        return (0, int(match.group(1)), match.group(2))
    return (1, 0, name)


def plain_copy(path, out_dir):
    """Returns a seekable, uncompressed path for FILE."""
    if not path.endswith('.gz'):
        return path, False
    target = os.path.join(out_dir, '.input.txt')
    with gzip.open(path, 'rb') as source, open(target, 'wb') as plain:
        shutil.copyfileobj(source, plain, 1 << 20)
    return target, True


def index_crms(real_path, path):
    """Pass 1: (chromosome, start, end, byte offset) for every CRM line."""
    crms = []
    seen = set()
    with open(real_path, 'rb') as handle:
        offset = 0
        for line_number, line in enumerate(handle, 1):
            fields = line.split(b'\t', 3)
            if len(fields) < 4:
                raise DataError('%s line %d: expected at least 4 columns' % (path, line_number))
            location = (fields[0].decode(), int(fields[1]), int(fields[2]))
            if location in seen:
                raise DataError('%s line %d: duplicate CRM %s:%d-%d' % ((path, line_number) + location))
            if location[1] >= location[2]:
                raise DataError('%s line %d: CRM start is not before its end' % (path, line_number))
            seen.add(location)
            crms.append((chromosome_key(location[0]), location[1], location[2], location[0], offset, line_number))
            offset += len(line)
    crms.sort()
    return crms


def number(text, path, line, label):
    try:
        return float(text)
    except ValueError:
        raise DataError('%s line %d: %s %r is not a number' % (path, line, label, text))


def split_dataset(genome_id, dataset, real_path, path, next_crm_id, motifs, out):
    """Pass 2: reads the CRMs in genomic order and writes their rows.

    path is the file name used in error messages."""
    crms = index_crms(real_path, path)
    chromosomes = []
    sites_written = 0
    empty = 0
    with open(real_path, 'rb') as handle:
        for crm_id, (_, start, end, chromosome, offset, line_number) in enumerate(crms, next_crm_id):
            handle.seek(offset)
            fields = handle.readline().rstrip(b'\r\n').decode().split('\t')
            if len(fields) < 6 or fields[5] == '':
                raise DataError('%s line %d: no P-value column' % (path, line_number))
            score, p_value = fields[4], fields[5]
            number(score, path, line_number, 'CRM score')
            if not 0 <= number(p_value, path, line_number, 'P-value') <= 1:
                raise DataError('%s line %d: P-value %s is outside 0-1' % (path, line_number, p_value))
            if not chromosomes or chromosomes[-1] != chromosome:
                chromosomes.append(chromosome)
            out['crms'].write('%d\t%d\t%d\t%s\t%d\t%d\t%s\t%s\n' % (
                crm_id, genome_id, dataset, chromosome, start, end, score, p_value))

            sites = {}
            if not fields[3]:
                empty += 1
            for site in fields[3].split(',') if fields[3] else ():
                match = SITE.match(site)
                if not match:
                    raise DataError('%s line %d: cannot read binding site %r' % (path, line_number, site))
                um_id, site_start, site_end = (int(match.group(i)) for i in (1, 2, 3))
                binding_score, motif_score = match.group(4), match.group(5)
                number(binding_score, path, line_number, 'binding score')
                if not start <= site_start < site_end <= end:
                    raise DataError('%s line %d: site %r lies outside its CRM' % (path, line_number, site))
                width = site_end - site_start
                if (genome_id, dataset, um_id) not in motifs:
                    number(motif_score, path, line_number, 'motif score')
                    motifs[(genome_id, dataset, um_id)] = (len(motifs) + 1, width, motif_score)
                motif_id, known_width, known_score = motifs[(genome_id, dataset, um_id)]
                if width != known_width or (motif_score != known_score and float(motif_score) != float(known_score)):
                    raise DataError('%s line %d: motif %d has width %d and motif score %s here but %d and %s elsewhere' % (
                        path, line_number, um_id, width, motif_score, known_width, known_score))
                if (site_start, motif_id) in sites:
                    raise DataError('%s line %d: duplicate binding site %r' % (path, line_number, site))
                sites[(site_start, motif_id)] = (site_end, binding_score)
            for (site_start, motif_id), (site_end, binding_score) in sorted(sites.items()):
                out['tfbss'].write('%d\t%d\t%d\t%d\t%s\n' % (
                    crm_id, site_start, motif_id, site_end, binding_score))
            sites_written += len(sites)

    return len(crms), sites_written, empty, chromosomes


def main(argv):
    inputs = []
    for arg in argv[2:]:
        match = re.match(r'(\d+)(?::(\d+))?=(.+)$', arg)
        if not match:
            sys.exit(__doc__)
        inputs.append((int(match.group(1)), int(match.group(2) or 1), match.group(3)))
    if not inputs or len(set(item[:2] for item in inputs)) < len(inputs):
        sys.exit(__doc__)
    inputs.sort()
    out_dir = argv[1]
    os.makedirs(out_dir, exist_ok=True)

    motifs = {}
    out = {name: open(os.path.join(out_dir, name + '.tsv'), 'w') for name in ('chromosomes', 'crms', 'tfbss')}
    chromosomes = {}
    next_crm_id = 1
    try:
        for genome_id, dataset, path in inputs:
            plain, temporary = plain_copy(path, out_dir)
            try:
                count, sites, empty, names = split_dataset(genome_id, dataset, plain, path, next_crm_id, motifs, out)
            finally:
                if temporary:
                    os.remove(plain)
            for name in names:
                chromosomes.setdefault((genome_id, name), len(chromosomes))
            print('genome %d dataset %d: %d CRMs (IDs %d-%d, %d without binding sites), %d binding sites, %d motifs' % (
                genome_id, dataset, count, next_crm_id, next_crm_id + count - 1, empty, sites,
                sum(1 for key in motifs if key[:2] == (genome_id, dataset))))
            next_crm_id += count
        for genome_id, name in sorted(chromosomes, key=lambda key: (key[0], chromosomes[key])):
            out['chromosomes'].write('%d\t%s\n' % (genome_id, name))
    except DataError as error:
        sys.exit('Stopped: %s' % error)
    finally:
        for handle in out.values():
            handle.close()

    with open(os.path.join(out_dir, 'umotifs.tsv'), 'w') as handle:
        for (genome_id, dataset, um_id), (motif_id, width, motif_score) in sorted(motifs.items(), key=lambda item: item[1]):
            handle.write('%d\t%d\t%d\t%d\t%d\t%s\n' % (motif_id, genome_id, dataset, um_id, width, motif_score))


if __name__ == '__main__':
    main(sys.argv)
