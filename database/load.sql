-- Loads the PCRMs data into an empty database created by schema.sql.
--
-- Run from the folder that holds the .tsv files:
--
--   mysql --local-infile=1 --show-warnings --init-command="SET sql_log_bin = 0" \
--       PCRMSV7 < /path/to/database/load.sql
--
-- The server needs local_infile=ON while loading; turn it off afterwards.
-- sql_log_bin = 0 keeps the 5.5 GB binding-site load out of the binary log
-- (see database/README.md).
-- LOAD DATA LOCAL skips rows it cannot store (a duplicate key, a missing
-- chromosome, text in a number column) with a warning instead of stopping, so
-- read every warning printed by --show-warnings and compare the counts at the
-- end with the split script's output.
--
-- Files, tab-separated, no header line:
--   chromosomes.tsv, crms.tsv, umotifs.tsv, tfbss.tsv
--       written by database/split_crm_files.py
--   genes.tsv      geneID, genomeID, symbol, chromosome, start, end, strand (+ or -)
--   crm_genes.tsv  genomeID, dataset, chromosome, CRM start, CRM end, geneID
--   motif_tfs.tsv  genomeID, dataset, motif number (umID), TF name
--
-- Add a row to datasets below for each new map (for example the Nucleotide
-- Transformer maps) before loading its files.

SET NAMES utf8mb4;

INSERT INTO genomes (genomeID, genomeName, assembly) VALUES
    (1, 'Homo sapiens', 'hg38'),
    (2, 'Mus musculus', 'mm10'),
    (3, 'Caenorhabditis elegans', 'ce10'),
    (4, 'Arabidopsis thaliana', 'TAIR10'),
    (5, 'Drosophila melanogaster', 'dm3');

INSERT INTO datasets (genomeID, datasetNo, method, releaseVersion) VALUES
    (1, 1, 'dePCRM2', 2),
    (2, 1, 'dePCRM2', 2),
    (3, 1, 'dePCRM2', 1),
    (4, 1, 'dePCRM2', 1),
    (5, 1, 'dePCRM2', 1);

LOAD DATA LOCAL INFILE 'chromosomes.tsv' INTO TABLE chromosomes
    (genomeID, chromosomeName);

-- Genes can sit on chromosomes that have no CRMs.
CREATE TEMPORARY TABLE gene_rows (
    geneID VARCHAR(64) NOT NULL,
    Genome TINYINT UNSIGNED NOT NULL,
    Symbol VARCHAR(64) NOT NULL,
    Chromosome VARCHAR(32) NOT NULL,
    Start_Pos INT UNSIGNED NOT NULL,
    End_Pos INT UNSIGNED NOT NULL,
    Strand ENUM('+', '-') NOT NULL
) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;
LOAD DATA LOCAL INFILE 'genes.tsv' INTO TABLE gene_rows;
INSERT INTO chromosomes (genomeID, chromosomeName)
SELECT DISTINCT r.Genome, r.Chromosome
FROM gene_rows AS r
WHERE NOT EXISTS (
    SELECT 1 FROM chromosomes AS c
    WHERE c.genomeID = r.Genome AND c.chromosomeName = r.Chromosome
);

LOAD DATA LOCAL INFILE 'crms.tsv' INTO TABLE crms
    (crmID, Genome, Dataset, Chromosome, Start_Pos, End_Pos, Score, P_value);
LOAD DATA LOCAL INFILE 'umotifs.tsv' INTO TABLE umotifs
    (motifID, Genome, Dataset, umID, Width, Motif_Score);
LOAD DATA LOCAL INFILE 'tfbss.tsv' INTO TABLE tfbss
    (crmID, TFBS_Start, motifID, TFBS_End, Binding_Score);
INSERT INTO genes SELECT * FROM gene_rows;

-- The association files name CRMs by location and motifs by their number,
-- because the numeric IDs only exist once the split script has run.
CREATE TEMPORARY TABLE crm_gene_rows (
    Genome TINYINT UNSIGNED NOT NULL,
    Dataset TINYINT UNSIGNED NOT NULL,
    Chromosome VARCHAR(32) NOT NULL,
    Start_Pos INT UNSIGNED NOT NULL,
    End_Pos INT UNSIGNED NOT NULL,
    geneID VARCHAR(64) NOT NULL
) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;
LOAD DATA LOCAL INFILE 'crm_genes.tsv' INTO TABLE crm_gene_rows;
INSERT INTO crms_genes_association (crmID, geneID)
SELECT c.crmID, r.geneID
FROM crm_gene_rows AS r
INNER JOIN crms AS c
    ON c.Genome = r.Genome AND c.Dataset = r.Dataset AND c.Chromosome = r.Chromosome
    AND c.Start_Pos = r.Start_Pos AND c.End_Pos = r.End_Pos;

CREATE TEMPORARY TABLE motif_tf_rows (
    Genome TINYINT UNSIGNED NOT NULL,
    Dataset TINYINT UNSIGNED NOT NULL,
    umID SMALLINT UNSIGNED NOT NULL,
    tfID VARCHAR(64) NOT NULL
) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;
LOAD DATA LOCAL INFILE 'motif_tfs.tsv' INTO TABLE motif_tf_rows;
INSERT INTO umotif_tfs_association (motifID, tfID)
SELECT m.motifID, r.tfID
FROM motif_tf_rows AS r
INNER JOIN umotifs AS m ON m.Genome = r.Genome AND m.Dataset = r.Dataset AND m.umID = r.umID;

-- Checks. Every "problem" count must be 0.

SELECT 'association rows naming a CRM that does not exist' AS problem, COUNT(*) AS count
FROM crm_gene_rows AS r
LEFT JOIN crms AS c
    ON c.Genome = r.Genome AND c.Dataset = r.Dataset AND c.Chromosome = r.Chromosome
    AND c.Start_Pos = r.Start_Pos AND c.End_Pos = r.End_Pos
WHERE c.crmID IS NULL
UNION ALL
SELECT 'CRM-gene pairs from different genomes', COUNT(*)
FROM crms_genes_association AS a
INNER JOIN crms AS c ON c.crmID = a.crmID
INNER JOIN genes AS g ON g.geneID = a.geneID
WHERE c.Genome <> g.Genome
UNION ALL
-- Reads every binding site; takes a few minutes on the full data.
SELECT 'binding sites whose motif belongs to another dataset', COUNT(*)
FROM tfbss AS t
INNER JOIN crms AS c ON c.crmID = t.crmID
INNER JOIN umotifs AS m ON m.motifID = t.motifID
WHERE m.Genome <> c.Genome OR m.Dataset <> c.Dataset;

-- Information, not a problem: TF names for motifs that have no binding sites
-- in the data (motif files can hold motifs no CRM uses) are not loaded.
SELECT COUNT(*) AS tf_names_for_motifs_without_sites
FROM motif_tf_rows AS r
LEFT JOIN umotifs AS m ON m.Genome = r.Genome AND m.Dataset = r.Dataset AND m.umID = r.umID
WHERE m.motifID IS NULL;

SELECT d.genomeID, g.assembly, d.datasetNo, d.method, d.releaseVersion,
    (SELECT COUNT(*) FROM crms AS c WHERE c.Genome = d.genomeID AND c.Dataset = d.datasetNo) AS crms,
    (SELECT COUNT(*) FROM umotifs AS m WHERE m.Genome = d.genomeID AND m.Dataset = d.datasetNo) AS motifs,
    (SELECT COUNT(*) FROM umotifs AS m WHERE m.Genome = d.genomeID AND m.Dataset = d.datasetNo AND NOT EXISTS (
        SELECT 1 FROM umotif_tfs_association AS u WHERE u.motifID = m.motifID)) AS motifs_without_tf,
    (SELECT COUNT(*) FROM genes AS n WHERE n.Genome = d.genomeID) AS genes
FROM datasets AS d
INNER JOIN genomes AS g ON g.genomeID = d.genomeID
ORDER BY d.genomeID, d.datasetNo;

SELECT COUNT(*) AS binding_sites FROM tfbss;

DROP TEMPORARY TABLE gene_rows, crm_gene_rows, motif_tf_rows;
