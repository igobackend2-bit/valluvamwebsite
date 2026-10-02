-- ============================================================================
-- External Auditor role + dashboard (2 Oct 2026)
-- Additive only: adds ONE permission and ONE role and links them. Nothing is changed or deleted.
-- Safe to run more than once. Then create the auditor's login in Admin Users with role "External Auditor".
-- ============================================================================
INSERT INTO admin_permissions (perm_key, description)
SELECT 'stockflow.audit_sign', 'Stock lifecycle: external auditor — monthly audit QC check, report and signature'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM admin_permissions WHERE perm_key = 'stockflow.audit_sign');

INSERT INTO admin_roles (name, description)
SELECT 'External Auditor', 'Monthly external stock audit + quality check: report, name and digital signature only'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM admin_roles WHERE name = 'External Auditor');

INSERT IGNORE INTO admin_role_permissions (role_id, perm_key)
SELECT r.id, p.perm_key FROM admin_roles r JOIN admin_permissions p ON p.perm_key IN ('stockflow.view', 'stockflow.audit_sign', 'documents.view', 'documents.upload', 'notifications.view')
WHERE r.name = 'External Auditor';
