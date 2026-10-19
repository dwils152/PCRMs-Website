-- Least-privilege account for the PCRMs website.
--
-- Run as a MySQL administrator after the data is loaded. Replace PCRMSV7 with
-- the production database name and 'localhost' with the web server's host if
-- MySQL runs elsewhere. Create the account first, with a new strong password
-- that is stored only in config/database.php or the environment:
--
--   CREATE USER 'pcrms_web'@'localhost' IDENTIFIED BY '<new password>';
--
-- The views must keep SQL SECURITY DEFINER with an administrator as definer,
-- so the web account needs no access to the tables behind them.

GRANT SELECT ON PCRMSV7.crms TO 'pcrms_web'@'localhost';
GRANT SELECT ON PCRMSV7.genes TO 'pcrms_web'@'localhost';
GRANT SELECT ON PCRMSV7.tfbss TO 'pcrms_web'@'localhost';
GRANT SELECT ON PCRMSV7.umotif_tfs_association TO 'pcrms_web'@'localhost';
GRANT SELECT ON PCRMSV7.crms_genes TO 'pcrms_web'@'localhost';
GRANT SELECT ON PCRMSV7.genes_crms_association TO 'pcrms_web'@'localhost';
GRANT SELECT ON PCRMSV7.genes_ranges TO 'pcrms_web'@'localhost';
GRANT SELECT ON PCRMSV7.umotif_tfs_association_multiple TO 'pcrms_web'@'localhost';

-- Only if PCRMS_ENABLE_VISITOR_TRACKING is turned on:
-- GRANT INSERT ON PCRMSV7.visitors TO 'pcrms_web'@'localhost';

-- Check the result; it should list only the grants above (plus USAGE).
SHOW GRANTS FOR 'pcrms_web'@'localhost';
