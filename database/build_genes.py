#!/usr/bin/env python3
"""Build genes.tsv and crm_genes.tsv from a GENCODE annotation.

Usage:
    python3 -I database/build_genes.py --gtf gencode.v50.annotation.gtf.gz \\
        --crms split/crms.tsv --genome 1 --dataset 1 --out genes/human [--all-genes]

genes.tsv lists the protein-coding genes (every gene with --all-genes) on the
chromosomes that have CRMs. crm_genes.tsv gives each CRM of the dataset in
crms.tsv (written by split_crm_files.py) its closest gene: the gene whose
transcription start site (TSS) is nearest the CRM, at distance 0 when the TSS
lies inside it. Genes tied for nearest are all listed. Both files are in the
formats database/load.sql and database/load_genes.sql read.

Coordinates are written like the CRM files' (BED style: 0-based start, end
exclusive), so GENCODE's 1-based starts move down by one. Gene IDs are
Ensembl IDs without their version (ENSG00000159216).
"""

import argparse
import bisect
import gzip
import os
import re
import sys

ATTRIBUTE = re.compile(r'(\w+) "([^"]*)"')


def read_genes(path, chromosomes, all_genes):
    """[(geneID, symbol, chromosome, start, end, strand)] from the gene lines of a GTF."""
    genes = []
    seen = set()
    opener = gzip.open if path.endswith('.gz') else open
    with opener(path, 'rt') as handle:
        for line in handle:
            if line.startswith('#'):
                continue
            fields = line.rstrip('\n').split('\t')
            if len(fields) < 9 or fields[2] != 'gene' or fields[0] not in chromosomes:
                continue
            attributes = dict(ATTRIBUTE.findall(fields[8]))
            if not all_genes and attributes.get('gene_type') != 'protein_coding':
                continue
            gene_id = re.sub(r'\.\d+(?=_|$)', '', attributes['gene_id'])
            if gene_id in seen:
                # A gene annotated twice, as in the X/Y pseudoautosomal regions.
                gene_id += '_' + fields[0]
            seen.add(gene_id)
            genes.append((gene_id, attributes.get('gene_name', gene_id), fields[0],
                          int(fields[3]) - 1, int(fields[4]), fields[6]))
    return genes


def read_crms(path, genome, dataset):
    """{chromosome: [(start, end)]} for one genome and dataset of crms.tsv."""
    crms = {}
    with open(path) as handle:
        for line in handle:
            fields = line.rstrip('\n').split('\t')
            if int(fields[1]) == genome and int(fields[2]) == dataset:
                crms.setdefault(fields[3], []).append((int(fields[4]), int(fields[5])))
    return crms


def closest_genes(crms, genes):
    """Yields (chromosome, start, end, [geneID], distance) for every CRM."""
    by_chromosome = {}
    for gene_id, _, chromosome, start, end, strand in genes:
        tss = start if strand == '+' else end - 1
        by_chromosome.setdefault(chromosome, {}).setdefault(tss, []).append(gene_id)

    for chromosome, intervals in crms.items():
        sites = by_chromosome.get(chromosome, {})
        positions = sorted(sites)
        for start, end in intervals:
            if not positions:
                yield chromosome, start, end, [], None
                continue
            i = bisect.bisect_left(positions, start)
            j = bisect.bisect_left(positions, end, i)
            if j > i:
                inside = [gene for position in positions[i:j] for gene in sites[position]]
                yield chromosome, start, end, inside, 0
                continue
            candidates = []
            if i > 0:
                candidates.append((start - positions[i - 1], positions[i - 1]))
            if i < len(positions):
                candidates.append((positions[i] - (end - 1), positions[i]))
            distance = min(candidate[0] for candidate in candidates)
            nearest = [gene for d, position in candidates if d == distance for gene in sites[position]]
            yield chromosome, start, end, nearest, distance


def main():
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    parser.add_argument('--gtf', required=True)
    parser.add_argument('--crms', required=True, help='crms.tsv from split_crm_files.py')
    parser.add_argument('--genome', required=True, type=int)
    parser.add_argument('--dataset', required=True, type=int)
    parser.add_argument('--out', required=True)
    parser.add_argument('--all-genes', action='store_true', help='keep every gene type, not only protein-coding')
    args = parser.parse_args()

    crms = read_crms(args.crms, args.genome, args.dataset)
    if not crms:
        sys.exit('Stopped: no CRMs for genome %d dataset %d in %s' % (args.genome, args.dataset, args.crms))
    genes = read_genes(args.gtf, set(crms), args.all_genes)

    os.makedirs(args.out, exist_ok=True)
    with open(os.path.join(args.out, 'genes.tsv'), 'w') as handle:
        for gene_id, name, chromosome, start, end, strand in genes:
            handle.write('%s\t%d\t%s\t%s\t%d\t%d\t%s\n' % (gene_id, args.genome, name, chromosome, start, end, strand))

    count = linked = inside = tied = 0
    distances = []
    with open(os.path.join(args.out, 'crm_genes.tsv'), 'w') as handle:
        for chromosome, start, end, nearest, distance in closest_genes(crms, genes):
            count += 1
            if not nearest:
                continue
            linked += 1
            inside += distance == 0
            tied += len(nearest) > 1
            distances.append(distance)
            for gene_id in nearest:
                handle.write('%d\t%d\t%s\t%d\t%d\t%s\n' % (args.genome, args.dataset, chromosome, start, end, gene_id))

    distances.sort()
    print('%d genes; %d of %d CRMs linked to their closest gene (%d with a TSS inside, %d ties); '
          'median distance %d bp' % (len(genes), linked, count, inside, tied,
                                     distances[len(distances) // 2] if distances else 0))


if __name__ == '__main__':
    main()
