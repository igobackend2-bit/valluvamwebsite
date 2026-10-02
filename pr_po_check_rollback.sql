-- Rollback is not needed: the columns are empty extras and the website works without them.
-- (The "Mark PO checked" step simply switches off if the columns are missing.)
-- To stop using the step without dropping anything, nothing has to be run.
SELECT 'no rollback needed' AS info;
