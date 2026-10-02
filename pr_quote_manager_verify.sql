-- should return 1 row: pr_quotation_mgr | purchase.manager_approve | 1
SELECT module, approver_perm, enabled FROM approval_policies WHERE module = 'pr_quotation_mgr';
