-- PCRMs database schema (third normal form).
--
-- Every fact is stored once, in the table whose key it depends on:
--
--   genomes                 genomeID -> name, assembly
--   datasets                (genomeID, datasetNo) -> method, version
--   chromosomes             (genomeID, chromosomeName)
--   crms                    crmID -> dataset, location, score, P-value
--   umotifs                 motifID -> dataset, motif number, width, motif score
--   umotif_tfs_association  (motifID, tfID): which TFs a motif matches
--   tfbss                   (crmID, TFBS_Start, motifID) -> end, binding score
--   genes                   geneID -> genome, symbol, location, strand
--   crms_genes_association  (crmID, geneID)
--
-- A dataset is one CRM map of a genome: the dePCRM2 maps (versions 1 and 2)
-- and, later, the Nucleotide Transformer maps. A genome can have several.
-- Datasets are numbered within their genome, so CRMs and motifs keep their
-- Genome column: it is part of their key, and their chromosome links to it.
--
-- Lists the pages show (gene symbols per CRM, TF names per motif) are built
-- by the queries or by the view at the end, never stored copies.
--
-- Run on an empty database, for example:
--   mysql PCRMSV7 < database/schema.sql
-- then load the data with database/load.sql.

SET NAMES utf8mb4;

CREATE TABLE genomes (
    genomeID TINYINT UNSIGNED NOT NULL,
    genomeName VARCHAR(100) NOT NULL,
    assembly VARCHAR(32) NOT NULL,
    PRIMARY KEY (genomeID),
    UNIQUE KEY uq_genomes_name (genomeName),
    UNIQUE KEY uq_genomes_assembly (assembly)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

CREATE TABLE datasets (
    genomeID TINYINT UNSIGNED NOT NULL,
    datasetNo TINYINT UNSIGNED NOT NULL,
    method VARCHAR(64) NOT NULL,
    releaseVersion TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (genomeID, datasetNo),
    UNIQUE KEY uq_datasets_release (genomeID, method, releaseVersion),
    CONSTRAINT fk_datasets_genome
        FOREIGN KEY (genomeID) REFERENCES genomes (genomeID)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

CREATE TABLE chromosomes (
    genomeID TINYINT UNSIGNED NOT NULL,
    chromosomeName VARCHAR(32) NOT NULL,
    PRIMARY KEY (genomeID, chromosomeName),
    CONSTRAINT fk_chromosomes_genome
        FOREIGN KEY (genomeID) REFERENCES genomes (genomeID)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- crmID is a number assigned in genomic order (genome, dataset, chromosome,
-- start), so one chromosome's CRMs in a dataset form a single ID range. That keeps binding-site
-- lookups by chromosome on an index without copying the chromosome into
-- tfbss.
CREATE TABLE crms (
    crmID INT UNSIGNED NOT NULL,
    Genome TINYINT UNSIGNED NOT NULL,
    Dataset TINYINT UNSIGNED NOT NULL,
    Chromosome VARCHAR(32) NOT NULL,
    Start_Pos INT UNSIGNED NOT NULL,
    End_Pos INT UNSIGNED NOT NULL,
    Score DOUBLE NOT NULL,
    P_value DOUBLE NOT NULL,
    PRIMARY KEY (crmID),
    UNIQUE KEY uq_crms_location (Genome, Dataset, Chromosome, Start_Pos, End_Pos),
    KEY idx_crms_p_value (Genome, Dataset, Chromosome, P_value),
    CONSTRAINT fk_crms_dataset
        FOREIGN KEY (Genome, Dataset) REFERENCES datasets (genomeID, datasetNo),
    CONSTRAINT fk_crms_chromosome
        FOREIGN KEY (Genome, Chromosome)
        REFERENCES chromosomes (genomeID, chromosomeName),
    CONSTRAINT ck_crms_span CHECK (Start_Pos < End_Pos),
    CONSTRAINT ck_crms_p_value CHECK (P_value BETWEEN 0 AND 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- One row per motif. umID is the motif's number within its dataset, as in
-- the source files (for the version 2 maps, also the logo file name in
-- www/motifs_version/<genome>/<umID>.png).
-- Width and Motif_Score depend only on the motif, so they live here and not
-- on each binding site.
CREATE TABLE umotifs (
    motifID SMALLINT UNSIGNED NOT NULL,
    Genome TINYINT UNSIGNED NOT NULL,
    Dataset TINYINT UNSIGNED NOT NULL,
    umID SMALLINT UNSIGNED NOT NULL,
    Width TINYINT UNSIGNED NOT NULL,
    Motif_Score DOUBLE NOT NULL,
    PRIMARY KEY (motifID),
    UNIQUE KEY uq_umotifs_number (Genome, Dataset, umID),
    CONSTRAINT fk_umotifs_dataset
        FOREIGN KEY (Genome, Dataset) REFERENCES datasets (genomeID, datasetNo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

CREATE TABLE umotif_tfs_association (
    motifID SMALLINT UNSIGNED NOT NULL,
    tfID VARCHAR(64) NOT NULL,
    PRIMARY KEY (motifID, tfID),
    KEY idx_umotif_tfs_tf (tfID, motifID),
    CONSTRAINT fk_umotif_tfs_motif
        FOREIGN KEY (motifID) REFERENCES umotifs (motifID)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- TFBS_End is always TFBS_Start + the motif's Width. Keeping it is still 3NF
-- because (crmID, TFBS_End, motifID) is also a key, and it saves a join on
-- every query. The CRM's dataset and chromosome are not repeated here.
-- Binding_Score is FLOAT (about 7 significant digits) to keep this table,
-- the largest by far, small.
CREATE TABLE tfbss (
    crmID INT UNSIGNED NOT NULL,
    TFBS_Start INT UNSIGNED NOT NULL,
    motifID SMALLINT UNSIGNED NOT NULL,
    TFBS_End INT UNSIGNED NOT NULL,
    Binding_Score FLOAT NOT NULL,
    PRIMARY KEY (crmID, TFBS_Start, motifID),
    KEY idx_tfbss_motif (motifID, crmID, TFBS_Start),
    CONSTRAINT fk_tfbss_crm
        FOREIGN KEY (crmID) REFERENCES crms (crmID),
    CONSTRAINT fk_tfbss_motif
        FOREIGN KEY (motifID) REFERENCES umotifs (motifID),
    CONSTRAINT ck_tfbss_span CHECK (TFBS_Start < TFBS_End)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

CREATE TABLE genes (
    geneID VARCHAR(64) NOT NULL,
    Genome TINYINT UNSIGNED NOT NULL,
    Symbol VARCHAR(64) NOT NULL,
    Chromosome VARCHAR(32) NOT NULL,
    Start_Pos INT UNSIGNED NOT NULL,
    End_Pos INT UNSIGNED NOT NULL,
    Strand ENUM('+', '-') NOT NULL,
    PRIMARY KEY (geneID),
    KEY idx_genes_symbol (Genome, Symbol),
    KEY idx_genes_location (Genome, Chromosome, Start_Pos),
    CONSTRAINT fk_genes_chromosome
        FOREIGN KEY (Genome, Chromosome)
        REFERENCES chromosomes (genomeID, chromosomeName),
    CONSTRAINT ck_genes_span CHECK (Start_Pos < End_Pos)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

CREATE TABLE crms_genes_association (
    crmID INT UNSIGNED NOT NULL,
    geneID VARCHAR(64) NOT NULL,
    PRIMARY KEY (crmID, geneID),
    KEY idx_crms_genes_gene (geneID, crmID),
    CONSTRAINT fk_crms_genes_crm
        FOREIGN KEY (crmID) REFERENCES crms (crmID),
    CONSTRAINT fk_crms_genes_gene
        FOREIGN KEY (geneID) REFERENCES genes (geneID)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- TF names per motif, for the CRM page.

CREATE VIEW umotif_tfs_association_multiple AS
SELECT
    m.motifID,
    m.Genome,
    m.Dataset,
    m.umID,
    GROUP_CONCAT(u.tfID ORDER BY u.tfID SEPARATOR ', ') AS tfNames
FROM umotifs AS m
INNER JOIN umotif_tfs_association AS u ON u.motifID = m.motifID
GROUP BY m.motifID;
