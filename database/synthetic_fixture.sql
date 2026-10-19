-- PCRMs synthetic development fixture
--
-- This file is intentionally self-contained and destructive only to the
-- dedicated `pcrms_synthetic` database. Every identifier and coordinate below
-- is synthetic and must not be used for scientific analysis.

CREATE DATABASE IF NOT EXISTS pcrms_synthetic
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE pcrms_synthetic;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW IF EXISTS genes_ranges;
DROP VIEW IF EXISTS crms_genes;
DROP VIEW IF EXISTS genes_crms_association;
DROP VIEW IF EXISTS umotif_tfs_association_multiple;

DROP TABLE IF EXISTS tfbss;
DROP TABLE IF EXISTS umotif_tfs_association;
DROP TABLE IF EXISTS crms_genes_association;
DROP TABLE IF EXISTS genes;
DROP TABLE IF EXISTS crms;
DROP TABLE IF EXISTS chromosomes;
DROP TABLE IF EXISTS visitors;
DROP TABLE IF EXISTS genomes;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE genomes (
    genomeID TINYINT UNSIGNED NOT NULL,
    genomeName VARCHAR(100) NOT NULL,
    PRIMARY KEY (genomeID),
    UNIQUE KEY uq_genomes_name (genomeName)
) ENGINE = InnoDB;

CREATE TABLE chromosomes (
    genomeID TINYINT UNSIGNED NOT NULL,
    chromosomeName VARCHAR(32) NOT NULL,
    ncbiName VARCHAR(64) NOT NULL,
    PRIMARY KEY (genomeID, chromosomeName),
    CONSTRAINT fk_chromosomes_genome
        FOREIGN KEY (genomeID) REFERENCES genomes (genomeID)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE genes (
    geneID VARCHAR(64) NOT NULL,
    Symbol VARCHAR(64) NOT NULL,
    Genome TINYINT UNSIGNED NOT NULL,
    Chromosome VARCHAR(32) NOT NULL,
    Start_Pos INT UNSIGNED NOT NULL,
    End_Pos INT UNSIGNED NOT NULL,
    Strand ENUM('+', '-') NOT NULL,
    PRIMARY KEY (geneID),
    KEY idx_genes_symbol (Genome, Symbol),
    KEY idx_genes_location (Genome, Chromosome, Start_Pos, End_Pos),
    CONSTRAINT fk_genes_chromosome
        FOREIGN KEY (Genome, Chromosome)
        REFERENCES chromosomes (genomeID, chromosomeName)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_genes_coordinates CHECK (End_Pos >= Start_Pos)
) ENGINE = InnoDB;

CREATE TABLE crms (
    crmID VARCHAR(64) NOT NULL,
    Genome TINYINT UNSIGNED NOT NULL,
    Chromosome VARCHAR(32) NOT NULL,
    Start_Pos INT UNSIGNED NOT NULL,
    End_Pos INT UNSIGNED NOT NULL,
    Score DECIMAL(10, 4) NOT NULL,
    P_value DOUBLE NOT NULL,
    Distance INT NOT NULL DEFAULT 0,
    PRIMARY KEY (crmID),
    KEY idx_crms_browser (Genome, Chromosome, P_value),
    KEY idx_crms_location (Genome, Chromosome, Start_Pos, End_Pos),
    CONSTRAINT fk_crms_chromosome
        FOREIGN KEY (Genome, Chromosome)
        REFERENCES chromosomes (genomeID, chromosomeName)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_crms_coordinates CHECK (End_Pos >= Start_Pos),
    CONSTRAINT chk_crms_p_value CHECK (P_value >= 0 AND P_value <= 1)
) ENGINE = InnoDB;

CREATE TABLE crms_genes_association (
    crmID VARCHAR(64) NOT NULL,
    geneID VARCHAR(64) NOT NULL,
    PRIMARY KEY (crmID, geneID),
    KEY idx_crms_genes_gene (geneID, crmID),
    CONSTRAINT fk_crms_genes_crm
        FOREIGN KEY (crmID) REFERENCES crms (crmID)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_crms_genes_gene
        FOREIGN KEY (geneID) REFERENCES genes (geneID)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE umotif_tfs_association (
    umID VARCHAR(32) NOT NULL,
    tfID VARCHAR(64) NOT NULL,
    Genome TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (Genome, umID, tfID),
    KEY idx_umotif_tf_search (Genome, tfID, umID),
    CONSTRAINT fk_umotif_genome
        FOREIGN KEY (Genome) REFERENCES genomes (genomeID)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE tfbss (
    crmID VARCHAR(64) NOT NULL,
    TFBS_Start INT UNSIGNED NOT NULL,
    TFBS_End INT UNSIGNED NOT NULL,
    umID VARCHAR(32) NOT NULL,
    Binding_Score DECIMAL(10, 4) NOT NULL,
    PRIMARY KEY (crmID, TFBS_Start, TFBS_End, umID),
    KEY idx_tfbss_motif (umID, crmID),
    CONSTRAINT fk_tfbss_crm
        FOREIGN KEY (crmID) REFERENCES crms (crmID)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_tfbss_coordinates CHECK (TFBS_End >= TFBS_Start)
) ENGINE = InnoDB;

-- Column order is significant because tracker/track.php uses INSERT ... VALUES
-- without a column list.
CREATE TABLE visitors (
    visitorID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    IP VARCHAR(45) NOT NULL,
    Country VARCHAR(100) NOT NULL,
    City VARCHAR(100) NOT NULL,
    Visited_At DATETIME NOT NULL,
    PRIMARY KEY (visitorID),
    KEY idx_visitors_date (Visited_At)
) ENGINE = InnoDB;

START TRANSACTION;

INSERT INTO genomes (genomeID, genomeName) VALUES
    (1, 'Homo_sapiens'),
    (2, 'Mus_musculus'),
    (3, 'Caenorhabditis_elegans'),
    (4, 'Arabidopsis_thaliana'),
    (5, 'Drosophila_melanogaster');

INSERT INTO chromosomes (genomeID, chromosomeName, ncbiName) VALUES
    (1, 'chr1',  'NC_000001.11'),
    (1, 'chr2',  'NC_000002.12'),
    (2, 'chr1',  'NC_000067.6'),
    (2, 'chr2',  'NC_000068.7'),
    (3, 'chr1',  'NC_003279.8'),
    (3, 'chr2',  'NC_003280.10'),
    (4, 'chr1',  'NC_003070.9'),
    (4, 'chr2',  'NC_003071.7'),
    (5, 'chr2L', 'NT_033779.5'),
    (5, 'chr2R', 'NT_033778.4');

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

-- Six deliberately positioned CRMs per organism cover:
--   * all four supported P-value thresholds;
--   * upstream/downstream queries on both strands; and
--   * an overlapping CRM.
INSERT INTO crms
    (crmID, Genome, Chromosome, Start_Pos, End_Pos, Score, P_value, Distance)
SELECT CONCAT(code, '_SYN_CRM_UPSTREAM'), genomeID, primaryChromosome,
       1800000, 1800249, 9.8000, 0.0000005, -199751
FROM synthetic_species;

INSERT INTO crms
    (crmID, Genome, Chromosome, Start_Pos, End_Pos, Score, P_value, Distance)
SELECT CONCAT(code, '_SYN_CRM_DOWNSTREAM'), genomeID, primaryChromosome,
       2100000, 2100299, 8.7000, 0.000003, 90000
FROM synthetic_species;

INSERT INTO crms
    (crmID, Genome, Chromosome, Start_Pos, End_Pos, Score, P_value, Distance)
SELECT CONCAT(code, '_SYN_CRM_OVERLAP'), genomeID, primaryChromosome,
       2003000, 2003299, 7.6000, 0.005, 0
FROM synthetic_species;

INSERT INTO crms
    (crmID, Genome, Chromosome, Start_Pos, End_Pos, Score, P_value, Distance)
SELECT CONCAT(code, '_SYN_CRM_FAR'), genomeID, primaryChromosome,
       1200000, 1200249, 6.5000, 0.03, -799751
FROM synthetic_species;

INSERT INTO crms
    (crmID, Genome, Chromosome, Start_Pos, End_Pos, Score, P_value, Distance)
SELECT CONCAT(code, '_SYN_CRM_MINUS_UPSTREAM'), genomeID, secondaryChromosome,
       4100000, 4100249, 9.1000, 0.0000005, 90000
FROM synthetic_species;

INSERT INTO crms
    (crmID, Genome, Chromosome, Start_Pos, End_Pos, Score, P_value, Distance)
SELECT CONCAT(code, '_SYN_CRM_MINUS_DOWNSTREAM'), genomeID, secondaryChromosome,
       3900000, 3900249, 7.2000, 0.005, -100000
FROM synthetic_species;

-- Seventy-five additional human records make both CRM and TF server-side
-- tables cross the default 50-row page boundary.
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

INSERT INTO crms
    (crmID, Genome, Chromosome, Start_Pos, End_Pos, Score, P_value, Distance)
SELECT
    CONCAT('HS_SYN_CRM_PAGE_', LPAD(n, 3, '0')),
    1,
    'chr1',
    1250000 + (n * 7000),
    1250299 + (n * 7000),
    6.0000 + (n / 100),
    CASE MOD(n, 4)
        WHEN 0 THEN 0.0000005
        WHEN 1 THEN 0.000003
        WHEN 2 THEN 0.005
        ELSE 0.03
    END,
    -750000 + (CAST(n AS SIGNED) * 7000)
FROM synthetic_numbers;

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

INSERT INTO crms_genes_association (crmID, geneID) VALUES
    ('HS_SYN_CRM_OVERLAP', 'HS_SYN_GENE_NEIGHBOR');

CREATE TEMPORARY TABLE synthetic_motifs (
    genomeID TINYINT UNSIGNED NOT NULL,
    primaryUmID VARCHAR(32) NOT NULL,
    secondaryUmID VARCHAR(32) NOT NULL,
    PRIMARY KEY (genomeID)
);

-- IDs 88/162 and 8/9 have repository motif images for genomes 1-3.
-- IDs 501/502 and 601/602 intentionally have no image, exercising the
-- "Logo unavailable" state for genomes 4-5.
INSERT INTO synthetic_motifs (genomeID, primaryUmID, secondaryUmID) VALUES
    (1, '88',  '162'),
    (2, '88',  '162'),
    (3, '8',   '9'),
    (4, '501', '502'),
    (5, '601', '602');

INSERT INTO umotif_tfs_association (umID, tfID, Genome)
SELECT primaryUmID, 'TF_ALPHA', genomeID FROM synthetic_motifs;

INSERT INTO umotif_tfs_association (umID, tfID, Genome)
SELECT primaryUmID, 'TF_SHARED', genomeID FROM synthetic_motifs;

INSERT INTO umotif_tfs_association (umID, tfID, Genome)
SELECT secondaryUmID, 'TF_BETA', genomeID FROM synthetic_motifs;

INSERT INTO umotif_tfs_association (umID, tfID, Genome)
SELECT secondaryUmID, 'TF_SHARED', genomeID FROM synthetic_motifs;

-- Every CRM receives one TF_ALPHA/TF_SHARED site. The downstream and overlap
-- records also receive TF_BETA sites for multi-row detail-page testing.
INSERT INTO tfbss
    (crmID, TFBS_Start, TFBS_End, umID, Binding_Score)
SELECT
    c.crmID,
    c.Start_Pos + 20,
    c.Start_Pos + 39,
    m.primaryUmID,
    7.1000 + (MOD(c.End_Pos, 100) / 1000)
FROM crms AS c
INNER JOIN synthetic_motifs AS m ON m.genomeID = c.Genome;

INSERT INTO tfbss
    (crmID, TFBS_Start, TFBS_End, umID, Binding_Score)
SELECT
    c.crmID,
    c.Start_Pos + 60,
    c.Start_Pos + 79,
    m.secondaryUmID,
    8.2000 + (MOD(c.End_Pos, 100) / 1000)
FROM crms AS c
INNER JOIN synthetic_motifs AS m ON m.genomeID = c.Genome
WHERE c.crmID LIKE '%_SYN_CRM_DOWNSTREAM'
   OR c.crmID LIKE '%_SYN_CRM_OVERLAP';

-- RFC 5737 / RFC 3849 documentation addresses avoid using personal data.
INSERT INTO visitors (IP, Country, City, Visited_At) VALUES
    ('192.0.2.10', 'Synthetic Country', 'Test City', '2026-01-15 10:00:00'),
    ('198.51.100.25', 'Synthetic Country', 'Example Town', '2026-02-20 14:30:00'),
    ('2001:db8::42', 'Synthetic Country', 'Fixture Village', '2026-03-25 09:15:00');

DROP TEMPORARY TABLE synthetic_motifs;
DROP TEMPORARY TABLE synthetic_numbers;
DROP TEMPORARY TABLE synthetic_species;

COMMIT;

-- The application uses both association names. Keeping one canonical table
-- and exposing the reverse spelling as a view prevents fixture drift.
CREATE VIEW genes_crms_association AS
SELECT geneID, crmID
FROM crms_genes_association;

CREATE VIEW crms_genes AS
SELECT
    c.crmID,
    c.Genome,
    c.Chromosome,
    c.Start_Pos,
    c.End_Pos,
    COALESCE(
        GROUP_CONCAT(DISTINCT g.Symbol ORDER BY g.Symbol SEPARATOR ', '),
        'Unassigned'
    ) AS Genes_Symbol,
    c.Score,
    c.P_value
FROM crms AS c
LEFT JOIN crms_genes_association AS a ON a.crmID = c.crmID
LEFT JOIN genes AS g ON g.geneID = a.geneID
GROUP BY
    c.crmID, c.Genome, c.Chromosome, c.Start_Pos, c.End_Pos,
    c.Score, c.P_value;

CREATE VIEW genes_ranges AS
SELECT
    g.geneID,
    g.Symbol,
    g.Genome,
    g.Chromosome,
    g.Start_Pos,
    g.End_Pos,
    g.Strand,
    CASE WHEN g.Start_Pos > 500000
        THEN g.Start_Pos - 500000 ELSE 0 END AS Start_Pos_50,
    g.End_Pos + 500000 AS End_Pos_50,
    CASE WHEN g.Start_Pos > 1000000
        THEN g.Start_Pos - 1000000 ELSE 0 END AS Start_Pos_100,
    g.End_Pos + 1000000 AS End_Pos_100,
    CASE WHEN g.Start_Pos > 2000000
        THEN g.Start_Pos - 2000000 ELSE 0 END AS Start_Pos_200,
    g.End_Pos + 2000000 AS End_Pos_200
FROM genes AS g;

CREATE VIEW umotif_tfs_association_multiple AS
SELECT
    umID,
    Genome,
    GROUP_CONCAT(DISTINCT tfID ORDER BY tfID SEPARATOR ', ') AS tfNames
FROM umotif_tfs_association
GROUP BY umID, Genome;

-- Importing the fixture prints a small sanity summary.
SELECT 'genomes' AS fixture_object, COUNT(*) AS row_count FROM genomes
UNION ALL
SELECT 'genes', COUNT(*) FROM genes
UNION ALL
SELECT 'crms', COUNT(*) FROM crms
UNION ALL
SELECT 'tfbss', COUNT(*) FROM tfbss
UNION ALL
SELECT 'visitors', COUNT(*) FROM visitors;
