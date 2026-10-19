-- PCRMs synthetic development fixture
--
-- Fills a database created with database/schema.sql with deterministic
-- synthetic records. Every identifier and coordinate below is synthetic and
-- must not be used for scientific analysis. Rerunning it replaces the data in
-- that database only:
--
--   mysql -e 'CREATE DATABASE pcrms_synthetic CHARACTER SET utf8mb4 COLLATE utf8mb4_bin'
--   mysql pcrms_synthetic < database/schema.sql
--   mysql pcrms_synthetic < database/synthetic_fixture.sql

SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE tfbss;
TRUNCATE TABLE umotif_tfs_association;
TRUNCATE TABLE umotifs;
TRUNCATE TABLE crms_genes_association;
TRUNCATE TABLE genes;
TRUNCATE TABLE crms;
TRUNCATE TABLE chromosomes;
TRUNCATE TABLE datasets;
TRUNCATE TABLE genomes;
SET FOREIGN_KEY_CHECKS = 1;

START TRANSACTION;

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

INSERT INTO chromosomes (genomeID, chromosomeName) VALUES
    (1, 'chr1'), (1, 'chr2'),
    (2, 'chr1'), (2, 'chr2'),
    (3, 'chr1'), (3, 'chr2'),
    (4, 'chr1'), (4, 'chr2'),
    (5, 'chr2L'), (5, 'chr2R');

CREATE TEMPORARY TABLE synthetic_species (
    genomeID TINYINT UNSIGNED NOT NULL,
    code VARCHAR(2) NOT NULL,
    primaryChromosome VARCHAR(32) NOT NULL,
    secondaryChromosome VARCHAR(32) NOT NULL,
    PRIMARY KEY (genomeID)
);

INSERT INTO synthetic_species
    (genomeID, code, primaryChromosome, secondaryChromosome)
VALUES
    (1, 'HS', 'chr1',  'chr2'),
    (2, 'MM', 'chr1',  'chr2'),
    (3, 'CE', 'chr1',  'chr2'),
    (4, 'AT', 'chr1',  'chr2'),
    (5, 'DM', 'chr2L', 'chr2R');

-- Every organism has a positive-strand and negative-strand gene so the range
-- search can exercise both directional branches.
INSERT INTO genes
    (geneID, Symbol, Genome, Chromosome, Start_Pos, End_Pos, Strand)
SELECT
    CONCAT(code, '_SYN_GENE_PLUS'),
    CONCAT('SYNTH_', code, '_PLUS'),
    genomeID,
    primaryChromosome,
    2000000,
    2010000,
    '+'
FROM synthetic_species;

INSERT INTO genes
    (geneID, Symbol, Genome, Chromosome, Start_Pos, End_Pos, Strand)
SELECT
    CONCAT(code, '_SYN_GENE_MINUS'),
    CONCAT('SYNTH_', code, '_MINUS'),
    genomeID,
    secondaryChromosome,
    4000000,
    4010000,
    '-'
FROM synthetic_species;

-- A second human gene lets the CRM detail page exercise its multi-gene extent
-- and comma-separated symbol rendering.
INSERT INTO genes
    (geneID, Symbol, Genome, Chromosome, Start_Pos, End_Pos, Strand)
VALUES
    ('HS_SYN_GENE_NEIGHBOR', 'SYNTH_HS_NEIGHBOR', 1, 'chr1',
     2030000, 2042000, '+');

-- CRMs are staged with readable labels, then numbered in genomic order like
-- the real data. Six deliberately positioned CRMs per organism cover:
--   * all four supported P-value thresholds;
--   * upstream/downstream queries on both strands; and
--   * an overlapping CRM.
CREATE TEMPORARY TABLE synthetic_crms (
    label VARCHAR(64) NOT NULL,
    Genome TINYINT UNSIGNED NOT NULL,
    Chromosome VARCHAR(32) NOT NULL,
    Start_Pos INT UNSIGNED NOT NULL,
    End_Pos INT UNSIGNED NOT NULL,
    Score DOUBLE NOT NULL,
    P_value DOUBLE NOT NULL,
    PRIMARY KEY (label)
);

INSERT INTO synthetic_crms
SELECT CONCAT(code, '_SYN_CRM_UPSTREAM'), genomeID, primaryChromosome,
       1800000, 1800249, 9.8, 0.0000005
FROM synthetic_species;

INSERT INTO synthetic_crms
SELECT CONCAT(code, '_SYN_CRM_DOWNSTREAM'), genomeID, primaryChromosome,
       2100000, 2100299, 8.7, 0.000003
FROM synthetic_species;

INSERT INTO synthetic_crms
SELECT CONCAT(code, '_SYN_CRM_OVERLAP'), genomeID, primaryChromosome,
       2003000, 2003299, 7.6, 0.005
FROM synthetic_species;

INSERT INTO synthetic_crms
SELECT CONCAT(code, '_SYN_CRM_FAR'), genomeID, primaryChromosome,
       1200000, 1200249, 6.5, 0.03
FROM synthetic_species;

INSERT INTO synthetic_crms
SELECT CONCAT(code, '_SYN_CRM_MINUS_UPSTREAM'), genomeID, secondaryChromosome,
       4100000, 4100249, 9.1, 0.0000005
FROM synthetic_species;

INSERT INTO synthetic_crms
SELECT CONCAT(code, '_SYN_CRM_MINUS_DOWNSTREAM'), genomeID, secondaryChromosome,
       3900000, 3900249, 7.2, 0.005
FROM synthetic_species;

-- Seventy-five additional human records make both CRM and TF server-side
-- tables cross several 25-row pages.
CREATE TEMPORARY TABLE synthetic_numbers (
    n TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (n)
);

INSERT INTO synthetic_numbers (n)
SELECT ones.n + (10 * tens.n) + 1
FROM (
    SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL
    SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL
    SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
) AS ones
CROSS JOIN (
    SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL
    SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL
    SELECT 6 UNION ALL SELECT 7
) AS tens
WHERE ones.n + (10 * tens.n) < 75;

INSERT INTO synthetic_crms
SELECT
    CONCAT('HS_SYN_CRM_PAGE_', LPAD(n, 3, '0')),
    1,
    'chr1',
    1250000 + (n * 7000),
    1250299 + (n * 7000),
    6.0 + (n / 100),
    CASE MOD(n, 4)
        WHEN 0 THEN 0.0000005
        WHEN 1 THEN 0.000003
        WHEN 2 THEN 0.005
        ELSE 0.03
    END
FROM synthetic_numbers;

INSERT INTO crms (crmID, Genome, Dataset, Chromosome, Start_Pos, End_Pos, Score, P_value)
SELECT
    ROW_NUMBER() OVER (ORDER BY Genome, Chromosome, Start_Pos),
    Genome, 1, Chromosome, Start_Pos, End_Pos, Score, P_value
FROM synthetic_crms;

-- Associate primary-chromosome CRMs with the positive-strand gene and
-- secondary-chromosome CRMs with the negative-strand gene.
INSERT INTO crms_genes_association (crmID, geneID)
SELECT c.crmID, CONCAT(s.code, '_SYN_GENE_PLUS')
FROM crms AS c
INNER JOIN synthetic_species AS s
    ON s.genomeID = c.Genome
   AND s.primaryChromosome = c.Chromosome;

INSERT INTO crms_genes_association (crmID, geneID)
SELECT c.crmID, CONCAT(s.code, '_SYN_GENE_MINUS')
FROM crms AS c
INNER JOIN synthetic_species AS s
    ON s.genomeID = c.Genome
   AND s.secondaryChromosome = c.Chromosome;

INSERT INTO crms_genes_association (crmID, geneID)
SELECT c.crmID, 'HS_SYN_GENE_NEIGHBOR'
FROM crms AS c
INNER JOIN synthetic_crms AS l
    ON l.Genome = c.Genome AND l.Chromosome = c.Chromosome
   AND l.Start_Pos = c.Start_Pos AND l.End_Pos = c.End_Pos
WHERE l.label = 'HS_SYN_CRM_OVERLAP';

-- Two motifs per organism, motif IDs 2g-1 and 2g for genome g. Numbers
-- 88/162 and 8/9 have repository motif images for genomes 1-3; 501/502 and
-- 601/602 intentionally have none, exercising the "No logo" state.
CREATE TEMPORARY TABLE synthetic_motifs (
    genomeID TINYINT UNSIGNED NOT NULL,
    primaryUmID SMALLINT UNSIGNED NOT NULL,
    secondaryUmID SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (genomeID)
);

INSERT INTO synthetic_motifs (genomeID, primaryUmID, secondaryUmID) VALUES
    (1, 88,  162),
    (2, 88,  162),
    (3, 8,   9),
    (4, 501, 502),
    (5, 601, 602);

INSERT INTO umotifs (motifID, Genome, Dataset, umID, Width, Motif_Score)
SELECT genomeID * 2 - 1, genomeID, 1, primaryUmID, 19, 5.0 FROM synthetic_motifs;

INSERT INTO umotifs (motifID, Genome, Dataset, umID, Width, Motif_Score)
SELECT genomeID * 2, genomeID, 1, secondaryUmID, 19, 4.0 FROM synthetic_motifs;

INSERT INTO umotif_tfs_association (motifID, tfID)
SELECT genomeID * 2 - 1, 'TF_ALPHA' FROM synthetic_motifs;

INSERT INTO umotif_tfs_association (motifID, tfID)
SELECT genomeID * 2 - 1, 'TF_SHARED' FROM synthetic_motifs;

INSERT INTO umotif_tfs_association (motifID, tfID)
SELECT genomeID * 2, 'TF_BETA' FROM synthetic_motifs;

INSERT INTO umotif_tfs_association (motifID, tfID)
SELECT genomeID * 2, 'TF_SHARED' FROM synthetic_motifs;

-- Every CRM receives one TF_ALPHA/TF_SHARED site. The downstream and overlap
-- records also receive TF_BETA sites for multi-row detail-page testing.
INSERT INTO tfbss (crmID, TFBS_Start, motifID, TFBS_End, Binding_Score)
SELECT
    crmID,
    Start_Pos + 20,
    Genome * 2 - 1,
    Start_Pos + 39,
    7.1 + (MOD(End_Pos, 100) / 1000)
FROM crms;

INSERT INTO tfbss (crmID, TFBS_Start, motifID, TFBS_End, Binding_Score)
SELECT
    c.crmID,
    c.Start_Pos + 60,
    c.Genome * 2,
    c.Start_Pos + 79,
    8.2 + (MOD(c.End_Pos, 100) / 1000)
FROM crms AS c
INNER JOIN synthetic_crms AS l
    ON l.Genome = c.Genome AND l.Chromosome = c.Chromosome
   AND l.Start_Pos = c.Start_Pos AND l.End_Pos = c.End_Pos
WHERE l.label LIKE '%\_SYN\_CRM\_DOWNSTREAM'
   OR l.label LIKE '%\_SYN\_CRM\_OVERLAP';

-- The labelled CRMs the README test cases refer to, with their numeric IDs.
SELECT l.label, c.crmID
FROM synthetic_crms AS l
INNER JOIN crms AS c
    ON c.Genome = l.Genome AND c.Chromosome = l.Chromosome
   AND c.Start_Pos = l.Start_Pos AND c.End_Pos = l.End_Pos
WHERE l.label LIKE 'HS\_SYN\_CRM\_%' AND l.label NOT LIKE 'HS\_SYN\_CRM\_PAGE\_%'
ORDER BY c.crmID;

DROP TEMPORARY TABLE synthetic_motifs;
DROP TEMPORARY TABLE synthetic_numbers;
DROP TEMPORARY TABLE synthetic_crms;
DROP TEMPORARY TABLE synthetic_species;

COMMIT;

-- Importing the fixture prints a small sanity summary.
SELECT 'genomes' AS fixture_object, COUNT(*) AS row_count FROM genomes
UNION ALL
SELECT 'genes', COUNT(*) FROM genes
UNION ALL
SELECT 'crms', COUNT(*) FROM crms
UNION ALL
SELECT 'tfbss', COUNT(*) FROM tfbss;
