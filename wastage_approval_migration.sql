-- ============================================================================
-- Wastage, damage and expired-stock approvals with proof (3 Oct 2026)
--   Wastage (Waste Records):          Manager  -> Admin
--   Damage (Damage & Returns):        Manager  -> Admin
--   Expired stock disposal:           Manager  -> Admin -> CEO
-- Additive only: new approval rules + "view waste" for the Admin role. Nothing is changed or deleted.
-- Safe to run more than once.
-- ============================================================================
INSERT INTO approval_policies (module, label, inherent, enabled, min_amount, approver_perm)
SELECT 'waste_admin', 'Wastage — Admin approval (after the Manager)', 1, 1, 0.00, 'purchase.backend_approve'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM approval_policies WHERE module = 'waste_admin');

INSERT INTO approval_policies (module, label, inherent, enabled, min_amount, approver_perm)
SELECT 'stock_damage_mgr', 'Damaged stock — Manager approval', 1, 1, 0.00, 'purchase.manager_approve'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM approval_policies WHERE module = 'stock_damage_mgr');
INSERT INTO approval_policies (module, label, inherent, enabled, min_amount, approver_perm)
SELECT 'stock_damage_admin', 'Damaged stock — Admin approval (after the Manager)', 1, 1, 0.00, 'purchase.backend_approve'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM approval_policies WHERE module = 'stock_damage_admin');

INSERT INTO approval_policies (module, label, inherent, enabled, min_amount, approver_perm)
SELECT 'expiry_dispose_mgr', 'Expired stock disposal — Manager approval', 1, 1, 0.00, 'purchase.manager_approve'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM approval_policies WHERE module = 'expiry_dispose_mgr');
INSERT INTO approval_policies (module, label, inherent, enabled, min_amount, approver_perm)
SELECT 'expiry_dispose_admin', 'Expired stock disposal — Admin approval', 1, 1, 0.00, 'purchase.backend_approve'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM approval_policies WHERE module = 'expiry_dispose_admin');
INSERT INTO approval_policies (module, label, inherent, enabled, min_amount, approver_perm)
SELECT 'expiry_dispose_ceo', 'Expired stock disposal — CEO final approval', 1, 1, 0.00, 'po.ceo_approve'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM approval_policies WHERE module = 'expiry_dispose_ceo');

-- the Admin can open Waste Records to see the wastage and its proof
INSERT INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, 'waste.view' FROM admin_roles r
WHERE r.name = 'Admin' AND NOT EXISTS (SELECT 1 FROM admin_role_permissions p WHERE p.role_id = r.id AND p.perm_key = 'waste.view');
