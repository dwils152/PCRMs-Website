-- Replaces the genes and CRM-gene links of a loaded database with those in
-- genes.tsv and crm_genes.tsv, as written by database/build_genes.py. Only
-- the genomes named in genes.tsv change. Run from the folder that holds the
-- files (join the species' files with cat first):
--
--   mysql --local-infile=1 --show-warnings PCRMSV7 < /path/to/database/load_genes.sql

SET NAMES utf8mb4;

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

CREATE TEMPORARY TABLE crm_gene_rows (
    Genome TINYINT UNSIGNED NOT NULL,
    Dataset TINYINT UNSIGNED NOT NULL,
    Chromosome VARCHAR(32) NOT NULL,
    Start_Pos INT UNSIGNED NOT NULL,
    End_Pos INT UNSIGNED NOT NULL,
    geneID VARCHAR(64) NOT NULL
) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;
LOAD DATA LOCAL INFILE 'crm_genes.tsv' INTO TABLE crm_gene_rows;

CREATE TEMPORARY TABLE gene_genomes AS SELECT DISTINCT Genome FROM gene_rows;

START TRANSACTION;

DELETE a
FROM crms_genes_association AS a
INNER JOIN genes AS g ON g.geneID = a.geneID
INNER JOIN gene_genomes AS d ON d.Genome = g.Genome;

DELETE g
FROM genes AS g
INNER JOIN gene_genomes AS d ON d.Genome = g.Genome;

INSERT INTO chromosomes (genomeID, chromosomeName)
SELECT DISTINCT r.Genome, r.Chromosome
FROM gene_rows AS r
WHERE NOT EXISTS (
    SELECT 1 FROM chromosomes AS c
    WHERE c.genomeID = r.Genome AND c.chromosomeName = r.Chromosome
);

INSERT INTO genes SELECT * FROM gene_rows;

INSERT INTO crms_genes_association (crmID, geneID)
SELECT c.crmID, r.geneID
FROM crm_gene_rows AS r
INNER JOIN crms AS c
    ON c.Genome = r.Genome AND c.Dataset = r.Dataset AND c.Chromosome = r.Chromosome
    AND c.Start_Pos = r.Start_Pos AND c.End_Pos = r.End_Pos;

COMMIT;

-- Every "problem" count must be 0.
SELECT 'link rows naming a CRM that does not exist' AS problem, COUNT(*) AS count
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
WHERE c.Genome <> g.Genome;

SELECT g.Genome,
    COUNT(DISTINCT g.geneID) AS genes,
    COUNT(a.crmID) AS crm_gene_links,
    COUNT(DISTINCT a.crmID) AS crms_linked
FROM genes AS g
LEFT JOIN crms_genes_association AS a ON a.geneID = g.geneID
GROUP BY g.Genome;

DROP TEMPORARY TABLE gene_rows, crm_gene_rows, gene_genomes;
