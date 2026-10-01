<?php
// ============================================================================
// Purchase order — competitor quotations (added 1 Oct 2026)
//   Up to 3 supplier quotations stored with a purchase order: supplier contact,
//   bank / UPI details, delivery days, payment terms, freight and item prices.
//   Read-only for the PO itself: saving quotations never changes the PO, stock
//   or accounts. Needs po_competitor_quotes_migration.sql.
//   GET  ?action=get&po_id=     POST action=save&po_id=&quotes=[...]&selected_slot=
// ============================================================================
require_once __DIR__ . '/erp_helper.php';
require_once __DIR__ . '/pq_lib.php';

$action = (string)erp_input('action', '');
$isWrite = $_SERVER['REQUEST_METHOD'] === 'POST';
// Same permission as creating / editing a purchase order in purchase_api.php.
$perms = ['save' => 'purchase.backend_approve'];
erp_guard($pdo, $perms[$action] ?? 'purchase.view');
if (isset($perms[$action]) && !$isWrite) erp_fail('Invalid request method.');
const PQ_PO = ['q' => 'po_competitor_quotes', 'i' => 'po_competitor_quote_items', 'fk' => 'po_id'];

try {
    switch ($action) {
        case 'get':
            if (!pq_table_exists($pdo, 'po_competitor_quotes')) erp_out(['status' => 'success', 'installed' => false, 'quotes' => []]);
            erp_out(['status' => 'success', 'installed' => true, 'quotes' => pq_load($pdo, PQ_PO, (int)erp_input('po_id'))]);

        case 'save':
            if (!pq_table_exists($pdo, 'po_competitor_quotes')) erp_invalid('Competitor quotations are not installed yet. Run po_competitor_quotes_migration.sql once.');
            $poId = (int)erp_input('po_id');
            $po = erp_row($pdo, "SELECT id, po_number, status FROM purchase_orders WHERE id = ?", [$poId]);
            if (!$po) erp_invalid('Purchase order not found.');
            if (!in_array($po['status'], ['draft', 'pending_approval'], true)) erp_invalid('Quotations can be changed only while the PO is a draft or waiting for approval.');
            $clean = pq_clean($pdo, erp_json_input('quotes'), (int)erp_input('selected_slot', 0));
            $pdo->beginTransaction();
            $old = pq_store($pdo, PQ_PO, $poId, $clean);
            $pdo->commit();
            log_audit($pdo, 'update', 'po_competitor_quotes', $poId, ['quotes' => $old],
                ['po' => $po['po_number'], 'quotes' => array_map(fn($c) => ['slot' => $c['slot'], 'supplier' => $c['supplier_name'], 'total' => $c['grand_total'], 'selected' => $c['is_selected']], $clean)]);
            erp_out(['status' => 'success', 'count' => count($clean), 'message' => count($clean) ? count($clean) . ' quotation(s) saved with ' . $po['po_number'] . '.' : 'Quotations cleared.']);

        default:
            erp_fail('Unknown action.');
    }
} catch (Throwable $e) {
    erp_db_error($e, $action ?: 'po quotations');
}
