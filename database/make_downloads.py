#!/usr/bin/env python3
"""Write the CRM-only and binding-site download files for one CRM map.

Usage:
    python3 -I database/make_downloads.py SPLIT_DIR --genome 1 --dataset 1 \\
        --crms Human_CRMs_v2_hg38.bed.gz --tfbss Human_TFBSs_v2_hg38.bed.gz

SPLIT_DIR holds crms.tsv, umotifs.tsv and tfbss.tsv from split_crm_files.py.
Both files are gzip-compressed, tab-separated and in genomic order, with one
header line starting with #:

  CRMs           chrom  start  end  crm_id  score  p_value
  binding sites  chrom  start  end  motif  binding_score  crm_id

crm_id is the CRM's ID on the website (crm.php?crmId=…) and motif its
number, as in the motif logos. Coordinates are as in the released CRM files.
The combined file, one CRM per line with its binding sites, is the released
file itself, compressed with gzip.
"""

import argparse
import os
import subprocess
import sys


def gzip_writer(path):
    """A text stream into `gzip -6 > path`; the gzip binary is much faster than Python's."""
    handle = open(path + '.part', 'wb')
    process = subprocess.Popen(['gzip', '-6', '-c'], stdin=subprocess.PIPE, stdout=handle)
    return process, handle


def finish(process, handle, path):
    process.stdin.close()
    if process.wait() != 0:
        sys.exit('Stopped: gzip failed for %s' % path)
    handle.close()
    os.replace(path + '.part', path)


def main():
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    parser.add_argument('split_dir')
    parser.add_argument('--genome', required=True, type=int)
    parser.add_argument('--dataset', required=True, type=int)
    parser.add_argument('--crms', required=True, help='output path for the CRM-only file (.bed.gz)')
    parser.add_argument('--tfbss', required=True, help='output path for the binding-site file (.bed.gz)')
    args = parser.parse_args()

    chromosome_of = {}
    process, handle = gzip_writer(args.crms)
    process.stdin.write(b'#chrom\tstart\tend\tcrm_id\tscore\tp_value\n')
    with open(os.path.join(args.split_dir, 'crms.tsv')) as crms:
        for line in crms:
            crm_id, genome, dataset, chromosome, start, end, score, p_value = line.rstrip('\n').split('\t')
            if int(genome) == args.genome and int(dataset) == args.dataset:
                chromosome_of[int(crm_id)] = chromosome
                process.stdin.write(('\t'.join([chromosome, start, end, crm_id, score, p_value]) + '\n').encode())
    finish(process, handle, args.crms)
    if not chromosome_of:
        sys.exit('Stopped: no CRMs for genome %d dataset %d' % (args.genome, args.dataset))
    # CRM IDs of one map are one block, so a range check finds this map's sites.
    first, last = min(chromosome_of), max(chromosome_of)

    motif_number = {}
    with open(os.path.join(args.split_dir, 'umotifs.tsv')) as motifs:
        for line in motifs:
            motif_id, _, _, um_id = line.split('\t')[:4]
            motif_number[motif_id] = um_id

    sites = 0
    process, handle = gzip_writer(args.tfbss)
    process.stdin.write(b'#chrom\tstart\tend\tmotif\tbinding_score\tcrm_id\n')
    rows = []
    with open(os.path.join(args.split_dir, 'tfbss.tsv')) as tfbss:
        for line in tfbss:
            crm_id, start, motif_id, end, binding_score = line.rstrip('\n').split('\t')
            crm = int(crm_id)
            if crm < first or crm > last:
                continue
            rows.append('\t'.join([chromosome_of[crm], start, end, motif_number[motif_id], binding_score, crm_id]))
            sites += 1
            if len(rows) >= 100000:
                process.stdin.write(('\n'.join(rows) + '\n').encode())
                rows = []
    if rows:
        process.stdin.write(('\n'.join(rows) + '\n').encode())
    finish(process, handle, args.tfbss)
    print('genome %d dataset %d: %d CRMs, %d binding sites' % (args.genome, args.dataset, len(chromosome_of), sites))


if __name__ == '__main__':
    main()
