-- ============================================================================
-- VERIFY erp_complete_migration.sql (read-only — safe to run any time)
-- ============================================================================
-- 1) all 40 new tables exist (expect 40)
SELECT COUNT(*) AS new_tables FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (
  'credit_notes',  'fulfilment_logs',  'sales_channel_map',  'sales_channels',  'expenses',  'financial_periods',  'bank_statement_lines',  'bank_accounts',  'journal_lines',  'journal_entries',  'chart_of_accounts',  'batch_allocations',  'item_locations',  'warehouse_locations',  'stock_count_items',  'stock_counts',  'stock_transfer_items',  'stock_transfers',  'erp_sync_state',  'warehouse_stock_moves',  'warehouse_stock',  'erp_document_meta',  'admin_notification_reads',  'admin_notifications',  'approval_actions',  'approval_requests',  'approval_policies',  'supplier_profiles',  'debit_notes',  'quality_check_items',  'quality_checks',  'inbound_shipment_payments',  'inbound_shipments',  'purchase_order_revisions',  'procurement_links',  'supplier_quotation_items',  'supplier_quotations',  'rfq_suppliers',  'rfq_items',  'rfqs'
);
-- 2) seed data (expect 43 accounts, 2 bank/cash accounts, 3 channels, 12 approval rules)
SELECT (SELECT COUNT(*) FROM chart_of_accounts) AS accounts, (SELECT COUNT(*) FROM bank_accounts) AS bank_cash,
       (SELECT COUNT(*) FROM sales_channels) AS channels, (SELECT COUNT(*) FROM approval_policies) AS approval_rules;
-- 3) new permissions (expect 30) and roles
SELECT COUNT(*) AS new_permissions FROM admin_permissions WHERE perm_key IN ('rfq.manage','shipment.manage','qc.manage','supplier.profile','purchase_invoice.approve','purchase_payment.approve',
  'purchase_return.approve','sales_return.approve','expense.approve','approvals.view','approvals.manage','documents.view','documents.upload','documents.archive','notifications.view','warehouse.view',
  'warehouse.transfer','warehouse.locations','stock_count.create','stock_count.approve','accounting.view','accounting.journal','accounting.approve','accounting.manage','bank.reconcile','sales.fulfilment',
  'sales.channels','customer.view','trace.view','reports.erp');
SELECT r.id, r.name, COUNT(p.perm_key) AS permissions FROM admin_roles r LEFT JOIN admin_role_permissions p ON p.role_id = r.id GROUP BY r.id, r.name ORDER BY r.id;
-- 4) existing data untouched (compare with your numbers before the migration)
SELECT (SELECT COUNT(*) FROM product_details) products, (SELECT SUM(stock) FROM product_details) total_stock, (SELECT COUNT(*) FROM orders) website_orders,
       (SELECT COUNT(*) FROM invoices) invoices, (SELECT COUNT(*) FROM accounts_transactions) transactions, (SELECT COUNT(*) FROM stock_movements) stock_movements, (SELECT COUNT(*) FROM admin_users) admin_users;
