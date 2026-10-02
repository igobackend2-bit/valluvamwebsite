-- ============================================================================
-- Shop quotation approval: Manager first, then Admin (2 Oct 2026)
-- Additive only: adds ONE approval rule. Nothing is changed or deleted.
-- Safe to run more than once.
-- ============================================================================
INSERT INTO approval_policies (module, label, inherent, enabled, min_amount, approver_perm)
SELECT 'pr_quotation_mgr', 'Shop quotation — Manager approval (before Admin)', 1, 1, 0.00, 'purchase.manager_approve'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM approval_policies WHERE module = 'pr_quotation_mgr');
