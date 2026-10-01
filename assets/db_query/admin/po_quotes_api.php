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
            // + the PO's own shop with contact and bank details, for the "Purchasing from" card (1 Oct 2026)
            $sup = erp_row($pdo, "SELECT s.* FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id WHERE po.id = ?", [(int)erp_input('po_id')]);
            $sup = $sup ? array_intersect_key($sup, array_flip(['id', 'supplier_name', 'owner_name', 'mobile', 'email', 'gst_number', 'address', 'city', 'account_holder_name', 'bank_name', 'bank_account_number', 'bank_ifsc', 'upi_id', 'payment_terms', 'bank_details'])) : null;
            erp_out(['status' => 'success', 'installed' => true, 'quotes' => pq_load($pdo, PQ_PO, (int)erp_input('po_id')), 'supplier' => $sup]);

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

        // every set of shop quotations, for the RFQ & Quotations page (1 Oct 2026): purchase-flow requests + POs with their own quotations
        case 'compare_list':
            $groups = [];
            if (pq_table_exists($pdo, 'pr_quotes')) {
                $PR = ['q' => 'pr_quotes', 'i' => 'pr_quote_items', 'fk' => 'pr_id'];
                foreach (erp_rows($pdo, "SELECT pr.id, pr.pr_number, pr.request_date, pr.requested_by, f.quote_status, f.po_id, po.po_number, po.status AS po_status, s.supplier_name AS po_supplier
                                         FROM purchase_requests pr JOIN (SELECT DISTINCT pr_id FROM pr_quotes) q ON q.pr_id = pr.id
                                         LEFT JOIN purchase_flows f ON f.pr_id = pr.id LEFT JOIN purchase_orders po ON po.id = f.po_id LEFT JOIN suppliers s ON s.id = po.supplier_id
                                         ORDER BY pr.id DESC LIMIT 60") as $g)
                    $groups[] = ['kind' => 'pr', 'id' => (int)$g['id'], 'ref' => $g['pr_number'], 'date' => $g['request_date'], 'by' => $g['requested_by'], 'status' => $g['quote_status'] ?: 'collecting',
                                 'po_id' => $g['po_id'], 'po_number' => $g['po_number'], 'po_status' => $g['po_status'], 'po_supplier' => $g['po_supplier'], 'quotes' => pq_load($pdo, $PR, (int)$g['id'])];
            }
            if (pq_table_exists($pdo, 'po_competitor_quotes')) {
                $flowPo = pq_table_exists($pdo, 'purchase_flows') ? array_map('intval', array_column(erp_rows($pdo, "SELECT po_id FROM purchase_flows WHERE po_id IS NOT NULL"), 'po_id')) : [];   // already listed under their request
                foreach (erp_rows($pdo, "SELECT po.id, po.po_number, po.po_date, po.status, po.created_by, s.supplier_name FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id
                                         JOIN (SELECT DISTINCT po_id FROM po_competitor_quotes) q ON q.po_id = po.id ORDER BY po.id DESC LIMIT 60") as $g)
                    if (!in_array((int)$g['id'], $flowPo, true))
                        $groups[] = ['kind' => 'po', 'id' => (int)$g['id'], 'ref' => $g['po_number'], 'date' => $g['po_date'], 'by' => $g['created_by'], 'status' => $g['status'],
                                     'po_id' => (int)$g['id'], 'po_number' => $g['po_number'], 'po_status' => $g['status'], 'po_supplier' => $g['supplier_name'], 'quotes' => pq_load($pdo, PQ_PO, (int)$g['id'])];
            }
            usort($groups, fn($a, $b) => strcmp((string)$b['date'], (string)$a['date']));
            erp_out(['status' => 'success', 'groups' => $groups]);

        default:
            erp_fail('Unknown action.');
    }
} catch (Throwable $e) {
    erp_db_error($e, $action ?: 'po quotations');
}
