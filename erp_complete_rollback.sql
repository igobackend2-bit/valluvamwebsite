-- ============================================================================
-- ROLLBACK for erp_complete_migration.sql (1 Oct 2026) — ONLY IF YOU MUST UNDO IT.
-- Removes the NEW tables of the complete ERP and the permissions / roles it added.
-- Existing tables and data (products, orders, invoices, stock, transactions,
-- purchase module of 30 Sep) are NOT touched.
--   ⚠ Take a full backup first (HeidiSQL → Tools → Export database as SQL).
--   ⚠ Anything recorded in the new modules (RFQs, QC, transfers, journals,
--     expenses entered in the new Expenses page…) is lost. Expenses that were
--     posted also exist in Accounts → Transactions and stay there.
--   ⚠ Upload the previous code version BEFORE running this, otherwise the new
--     pages will show "tables not installed".
-- ============================================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS credit_notes;
DROP TABLE IF EXISTS fulfilment_logs;
DROP TABLE IF EXISTS sales_channel_map;
DROP TABLE IF EXISTS sales_channels;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS financial_periods;
DROP TABLE IF EXISTS bank_statement_lines;
DROP TABLE IF EXISTS bank_accounts;
DROP TABLE IF EXISTS journal_lines;
DROP TABLE IF EXISTS journal_entries;
DROP TABLE IF EXISTS chart_of_accounts;
DROP TABLE IF EXISTS batch_allocations;
DROP TABLE IF EXISTS item_locations;
DROP TABLE IF EXISTS warehouse_locations;
DROP TABLE IF EXISTS stock_count_items;
DROP TABLE IF EXISTS stock_counts;
DROP TABLE IF EXISTS stock_transfer_items;
DROP TABLE IF EXISTS stock_transfers;
DROP TABLE IF EXISTS erp_sync_state;
DROP TABLE IF EXISTS warehouse_stock_moves;
DROP TABLE IF EXISTS warehouse_stock;
DROP TABLE IF EXISTS erp_document_meta;
DROP TABLE IF EXISTS admin_notification_reads;
DROP TABLE IF EXISTS admin_notifications;
DROP TABLE IF EXISTS approval_actions;
DROP TABLE IF EXISTS approval_requests;
DROP TABLE IF EXISTS approval_policies;
DROP TABLE IF EXISTS supplier_profiles;
DROP TABLE IF EXISTS debit_notes;
DROP TABLE IF EXISTS quality_check_items;
DROP TABLE IF EXISTS quality_checks;
DROP TABLE IF EXISTS inbound_shipment_payments;
DROP TABLE IF EXISTS inbound_shipments;
DROP TABLE IF EXISTS purchase_order_revisions;
DROP TABLE IF EXISTS procurement_links;
DROP TABLE IF EXISTS supplier_quotation_items;
DROP TABLE IF EXISTS supplier_quotations;
DROP TABLE IF EXISTS rfq_suppliers;
DROP TABLE IF EXISTS rfq_items;
DROP TABLE IF EXISTS rfqs;
SET FOREIGN_KEY_CHECKS = 1;

DELETE FROM admin_role_permissions WHERE perm_key IN ('rfq.manage','shipment.manage','qc.manage','supplier.profile','purchase_invoice.approve','purchase_payment.approve','purchase_return.approve',
  'sales_return.approve','expense.approve','approvals.view','approvals.manage','documents.view','documents.upload','documents.archive','notifications.view','warehouse.view','warehouse.transfer',
  'warehouse.locations','stock_count.create','stock_count.approve','accounting.view','accounting.journal','accounting.approve','accounting.manage','bank.reconcile','sales.fulfilment','sales.channels',
  'customer.view','trace.view','reports.erp');
DELETE FROM admin_permissions WHERE perm_key IN ('rfq.manage','shipment.manage','qc.manage','supplier.profile','purchase_invoice.approve','purchase_payment.approve','purchase_return.approve',
  'sales_return.approve','expense.approve','approvals.view','approvals.manage','documents.view','documents.upload','documents.archive','notifications.view','warehouse.view','warehouse.transfer',
  'warehouse.locations','stock_count.create','stock_count.approve','accounting.view','accounting.journal','accounting.approve','accounting.manage','bank.reconcile','sales.fulfilment','sales.channels',
  'customer.view','trace.view','reports.erp');
-- the 5 new roles (only if no admin user was given one of them)
DELETE FROM admin_roles WHERE name IN ('Accounts','Warehouse','Purchase','Sales','Viewer') AND id NOT IN (SELECT role_id FROM admin_users);
DELETE FROM admin_settings WHERE setting_key IN ('erp_gl_start_date','erp_gl_last_sync','erp_ntf_last_run','erp_online_payment_account','erp_default_warehouse','erp_expiry_alert_days','erp_qc_required','erp_doc_max_mb');
