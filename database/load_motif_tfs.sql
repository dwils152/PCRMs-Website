-- Replaces the TF names of the motifs in a loaded database with those in
-- motif_tfs.tsv (genome ID, dataset, motif number, TF name), as written by
-- database/match_motifs.py. Only the genomes and datasets named in the file
-- change. Run from the folder that holds the file:
--
--   cat matches/human/motif_tfs.tsv matches/mouse/motif_tfs.tsv > motif_tfs.tsv
--   mysql --local-infile=1 --show-warnings PCRMSV7 < /path/to/database/load_motif_tfs.sql

SET NAMES utf8mb4;

CREATE TEMPORARY TABLE motif_tf_rows (
    Genome TINYINT UNSIGNED NOT NULL,
    Dataset TINYINT UNSIGNED NOT NULL,
    umID SMALLINT UNSIGNED NOT NULL,
    tfID VARCHAR(64) NOT NULL
) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;
LOAD DATA LOCAL INFILE 'motif_tfs.tsv' INTO TABLE motif_tf_rows;

CREATE TEMPORARY TABLE motif_tf_datasets AS
SELECT DISTINCT Genome, Dataset FROM motif_tf_rows;

START TRANSACTION;

DELETE u
FROM umotif_tfs_association AS u
INNER JOIN umotifs AS m ON m.motifID = u.motifID
INNER JOIN motif_tf_datasets AS d ON d.Genome = m.Genome AND d.Dataset = m.Dataset;

INSERT INTO umotif_tfs_association (motifID, tfID)
SELECT DISTINCT m.motifID, r.tfID
FROM motif_tf_rows AS r
INNER JOIN umotifs AS m ON m.Genome = r.Genome AND m.Dataset = r.Dataset AND m.umID = r.umID;

COMMIT;

-- Rows here name a motif number with no binding sites in the database; their
-- names are not loaded. Expected for motifs that matched nothing in the data.
SELECT r.Genome, r.Dataset, r.umID, COUNT(*) AS tf_names_skipped
FROM motif_tf_rows AS r
LEFT JOIN umotifs AS m ON m.Genome = r.Genome AND m.Dataset = r.Dataset AND m.umID = r.umID
WHERE m.motifID IS NULL
GROUP BY r.Genome, r.Dataset, r.umID;

SELECT m.Genome, m.Dataset,
    COUNT(DISTINCT m.motifID) AS motifs_with_tfs,
    COUNT(*) AS motif_tf_pairs,
    COUNT(DISTINCT u.tfID) AS distinct_tfs
FROM umotif_tfs_association AS u
INNER JOIN umotifs AS m ON m.motifID = u.motifID
GROUP BY m.Genome, m.Dataset;

DROP TEMPORARY TABLE motif_tf_rows, motif_tf_datasets;
