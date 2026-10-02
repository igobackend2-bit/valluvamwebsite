-- Rollback (only if needed): quotations then go straight to the Admin again, as before.
-- Run only when no shop choice is waiting for the Manager:
--   SELECT COUNT(*) FROM approval_requests WHERE module = 'pr_quotation_mgr' AND status IN ('submitted','under_review');  -- must be 0
UPDATE approval_policies SET enabled = 0 WHERE module = 'pr_quotation_mgr';
