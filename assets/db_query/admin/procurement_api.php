<?php
// ============================================================================
// Procurement completion API (added 1 Oct 2026)
//   RFQ → supplier quotations → comparison → award (creates purchase orders)
//   PO amendments (revisions with approval) · PO reject
//   Inbound transport / logistics (landed cost, payments) · Quality checks on
//   draft goods receipts · Debit notes · Supplier 360 + supplier profile
// Purchase orders, GRNs, bills and payments themselves stay in purchase_api.php.
// ============================================================================
require_once __DIR__ . '/erp_ext.php';
require_once __DIR__ . '/erp_report_lib.php';

$action = (string)erp_input('action', '');
$isWrite = $_SERVER['REQUEST_METHOD'] === 'POST';
$perms = [
    'rfq_save' => 'rfq.manage', 'rfq_send' => 'rfq.manage', 'rfq_cancel' => 'rfq.manage', 'rfq_close' => 'rfq.manage', 'rfq_award' => 'rfq.manage',
    'quo_save' => 'rfq.manage', 'quo_reject' => 'rfq.manage', 'quo_cancel' => 'rfq.manage',
    'po_amend' => 'purchase.create', 'po_amend_approve' => 'purchase.approve', 'po_amend_reject' => 'purchase.approve', 'po_amend_cancel' => 'purchase.create', 'po_reject' => 'purchase.approve',
    'shp_save' => 'shipment.manage', 'shp_status' => 'shipment.manage', 'shp_cancel' => 'shipment.manage', 'shp_apply_cost' => 'shipment.manage',
    'shp_pay' => 'purchase_payment.create', 'shp_pay_cancel' => 'purchase_payment.create', 'shp_to_expense' => 'expense.manage',
    'qc_create' => 'qc.manage', 'qc_save' => 'qc.manage', 'qc_complete' => 'qc.manage', 'qc_cancel' => 'qc.manage',
    'dn_issue' => 'purchase_return.create', 'dn_issue_missing' => 'purchase_return.create', 'dn_status' => 'purchase_return.create',
    'sup_profile_save' => 'supplier.profile',
];
erpx_guard($pdo, $perms[$action] ?? 'purchase.view');
if (isset($perms[$action]) && !$isWrite) erp_fail('Invalid request method.');

/** Validated item lines for RFQ / quotation: [[type, id, qty, unit, rate, discount, tax%, lineCalc, extra]] */
function pc_lines(PDO $pdo, array $items, bool $priced): array {
    $out = [];
    foreach ($items as $it) {
        $type = erp_item_type($it['item_type'] ?? 'product');
        $iid = (int)($it['item_id'] ?? 0);
        if (!$iid) continue;
        $item = erp_item($pdo, $type, $iid);
        if (!$item) erp_invalid('An item in the list no longer exists.');
        $qty = erp_q(erp_num($it['quantity'] ?? 0, 'Quantity', false));
        if ($type === 'product' && floor($qty) != $qty) erp_invalid("{$item['name']}: product packs must be whole numbers.");
        $rate = erp_u(erp_num($it['rate'] ?? 0, 'Rate'));
        $tax = erp_num($it['tax_percent'] ?? 0, 'Tax %');
        $l = erp_line($qty, $rate, erp_num($it['discount_amount'] ?? 0, 'Discount'), $tax);
        if ($priced && $rate <= 0) erp_invalid("{$item['name']}: enter the quoted rate.");
        $out[] = ['type' => $type, 'id' => $iid, 'qty' => $qty, 'unit' => $type === 'product' ? 'pcs' : $item['unit'], 'rate' => $rate, 'tax' => $tax, 'calc' => $l,
                  'rfq_item_id' => (int)($it['rfq_item_id'] ?? 0) ?: null, 'remarks' => mb_substr((string)($it['remarks'] ?? $it['specs'] ?? ''), 0, 255),
                  'target_rate' => ($it['target_rate'] ?? '') === '' ? null : erp_u(erp_num($it['target_rate'], 'Target rate')), 'name' => $item['name'], 'sku' => $item['sku']];
    }
    return $out;
}
/** Creates a purchase order (pending approval) — same columns and math as purchase_api po_save. */
function pc_create_po(PDO $pdo, int $supplierId, int $warehouseId, array $lines, float $other, string $notes, ?int $prId = null): array {
    $sub = 0; $disc = 0; $tax = 0; $net = 0;
    foreach ($lines as $l) { $sub += $l['calc']['gross']; $disc += $l['calc']['discount']; $tax += $l['calc']['tax']; $net += $l['calc']['total']; }
    $grand = erp_m($net + $other);
    $num = next_document_number($pdo, 'purchase_order', 'PO');
    $pdo->prepare("INSERT INTO purchase_orders (po_number, supplier_id, pr_id, po_date, expected_delivery_date, warehouse_id, buyer, status, subtotal, discount_total, tax_total, other_charges, grand_total, notes, created_by)
                   VALUES (?,?,?,CURDATE(),?,?,?, 'pending_approval', ?,?,?,?,?,?,?)")
        ->execute([$num, $supplierId, $prId, $lines[0]['expected'] ?? null, $warehouseId, erp_user(), erp_m($sub), erp_m($disc), erp_m($tax), erp_m($other), $grand, $notes ?: null, erp_user()]);
    $id = (int)$pdo->lastInsertId();
    $ins = $pdo->prepare("INSERT INTO purchase_order_items (po_id, item_type, item_id, sku, quantity, unit, rate, discount_amount, tax_percent, tax_amount, line_total) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($lines as $l) $ins->execute([$id, $l['type'], $l['id'], $l['sku'], $l['qty'], $l['unit'], $l['rate'], $l['calc']['discount'], $l['tax'], $l['calc']['tax'], $l['calc']['total']]);
    apr_open($pdo, 'purchase_order', (string)$id, $id, $num, 'Purchase order ' . $num . ' — ' . erp_supplier_name($pdo, $supplierId), $grand, 'purchase_api.php', ['action' => 'po_approve', 'id' => $id], ['action' => 'po_reject', 'id' => $id, '_endpoint' => 'procurement_api.php']);
    return ['id' => $id, 'number' => $num, 'total' => $grand];
}
/** Snapshot of a PO header + lines (for amendments). */
function pc_po_snapshot(PDO $pdo, int $id): array {
    $po = erp_row($pdo, "SELECT id, po_number, expected_delivery_date, notes, other_charges, subtotal, discount_total, tax_total, grand_total, status FROM purchase_orders WHERE id = ?", [$id]);
    $po['items'] = erp_rows($pdo, "SELECT id, item_type, item_id, sku, quantity, unit, rate, discount_amount, tax_percent, tax_amount, line_total, received_qty FROM purchase_order_items WHERE po_id = ? ORDER BY id", [$id]);
    return $po;
}
function pc_shipment_total(array $s): float {
    return erp_m((float)$s['freight_amount'] + (float)$s['loading_charge'] + (float)$s['unloading_charge'] + (float)$s['handling_charge'] + (float)$s['other_charge']);
}

try {
    switch ($action) {
        // ============================================================ RFQ
        case 'rfq_list':
            $w = []; $p = [];
            if ($s = erp_input('status')) { $w[] = 'r.status = ?'; $p[] = $s; }
            if ($d = erp_date(erp_input('date_from'))) { $w[] = 'r.rfq_date >= ?'; $p[] = $d; }
            if ($d = erp_date(erp_input('date_to'))) { $w[] = 'r.rfq_date <= ?'; $p[] = $d; }
            if ($q = trim((string)erp_input('q', ''))) { $w[] = '(r.rfq_number LIKE ? OR r.notes LIKE ?)'; array_push($p, "%$q%", "%$q%"); }
            $rows = erp_rows($pdo, "SELECT r.*, w.name AS warehouse_name,
                                           (SELECT COUNT(*) FROM rfq_items i WHERE i.rfq_id = r.id) AS item_count,
                                           (SELECT COUNT(*) FROM rfq_suppliers x WHERE x.rfq_id = r.id) AS supplier_count,
                                           (SELECT COUNT(*) FROM supplier_quotations q WHERE q.rfq_id = r.id AND q.status <> 'cancelled') AS quote_count
                                    FROM rfqs r LEFT JOIN warehouses w ON w.id = r.warehouse_id" . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY r.id DESC LIMIT 500", $p);
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'rfq_get':
            $id = (int)erp_input('id');
            $r = erp_row($pdo, "SELECT r.*, w.name AS warehouse_name, pr.pr_number FROM rfqs r LEFT JOIN warehouses w ON w.id = r.warehouse_id LEFT JOIN purchase_requests pr ON pr.id = r.pr_id WHERE r.id = ?", [$id]);
            if (!$r) erp_fail('RFQ not found.');
            $r['items'] = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT * FROM rfq_items WHERE rfq_id = ? ORDER BY id", [$id]));
            $r['suppliers'] = erp_rows($pdo, "SELECT x.*, s.supplier_name, s.mobile, s.email FROM rfq_suppliers x JOIN suppliers s ON s.id = x.supplier_id WHERE x.rfq_id = ? ORDER BY s.supplier_name", [$id]);
            $r['quotations'] = erp_rows($pdo, "SELECT q.*, s.supplier_name FROM supplier_quotations q JOIN suppliers s ON s.id = q.supplier_id WHERE q.rfq_id = ? ORDER BY q.id", [$id]);
            $r['purchase_orders'] = erp_rows($pdo, "SELECT po.id, po.po_number, po.status, po.grand_total, s.supplier_name FROM procurement_links l JOIN purchase_orders po ON po.id = l.po_id JOIN suppliers s ON s.id = po.supplier_id WHERE l.rfq_id = ?", [$id]);
            erp_out(['status' => 'success', 'record' => $r]);

        case 'rfq_from_pr':
            $pr = erp_row($pdo, "SELECT * FROM purchase_requests WHERE id = ?", [(int)erp_input('pr_id')]);
            if (!$pr || $pr['status'] !== 'approved') erp_fail('Only approved purchase requests can be sent for quotation.');
            erp_out(['status' => 'success', 'pr' => $pr, 'items' => erp_attach_item_names($pdo, erp_rows($pdo, "SELECT item_type, item_id, quantity, unit, estimated_rate AS target_rate, notes AS specs FROM purchase_request_items WHERE pr_id = ?", [$pr['id']]))]);

        case 'rfq_save':
            $id = (int)erp_input('id', 0);
            $lines = pc_lines($pdo, erp_json_input('items'), false);
            if (!$lines) erp_invalid('Add at least one item.');
            $sups = array_values(array_unique(array_filter(array_map('intval', erp_json_input('supplier_ids')))));
            foreach ($sups as $sid) if (!erp_supplier_name($pdo, $sid)) erp_invalid('A selected supplier no longer exists.');
            $date = erp_date(erp_input('rfq_date'), true);
            $due = erp_date(erp_input('due_date'));
            if ($due && $due < $date) erp_invalid('Reply-by date cannot be before the RFQ date.');
            $prId = (int)erp_input('pr_id', 0) ?: null;
            $wh = erp_warehouse_ok($pdo, (int)erp_input('warehouse_id', 1));
            $pdo->beginTransaction();
            if ($id) {
                $old = erp_row($pdo, "SELECT * FROM rfqs WHERE id = ? FOR UPDATE", [$id]);
                if (!$old || !in_array($old['status'], ['draft', 'sent'], true)) erp_invalid('Only draft or sent RFQs can be edited.');
                if ((int)erp_val($pdo, "SELECT COUNT(*) FROM supplier_quotations WHERE rfq_id = ? AND status <> 'cancelled'", [$id])) erp_invalid('Quotations are already recorded — items can no longer change.');
                $pdo->prepare("UPDATE rfqs SET rfq_date=?, due_date=?, pr_id=?, warehouse_id=?, terms=?, notes=? WHERE id=?")->execute([$date, $due, $prId, $wh, erp_input('terms') ?: null, erp_input('notes') ?: null, $id]);
                $pdo->prepare("DELETE FROM rfq_items WHERE rfq_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM rfq_suppliers WHERE rfq_id = ? AND status = 'invited'")->execute([$id]);
                $num = $old['rfq_number'];
            } else {
                $num = next_document_number($pdo, 'rfq', 'RFQ');
                $pdo->prepare("INSERT INTO rfqs (rfq_number, rfq_date, due_date, pr_id, warehouse_id, terms, notes, status, created_by) VALUES (?,?,?,?,?,?,?, 'draft', ?)")
                    ->execute([$num, $date, $due, $prId, $wh, erp_input('terms') ?: null, erp_input('notes') ?: null, erp_user()]);
                $id = (int)$pdo->lastInsertId();
                // the request is now handled by this RFQ (prevents a second PO from the same request)
                if ($prId) $pdo->prepare("UPDATE purchase_requests SET status = 'converted' WHERE id = ? AND status = 'approved'")->execute([$prId]);
            }
            $ins = $pdo->prepare("INSERT INTO rfq_items (rfq_id, item_type, item_id, quantity, unit, target_rate, specs) VALUES (?,?,?,?,?,?,?)");
            foreach ($lines as $l) $ins->execute([$id, $l['type'], $l['id'], $l['qty'], $l['unit'], $l['target_rate'], $l['remarks'] ?: null]);
            $sIns = $pdo->prepare("INSERT IGNORE INTO rfq_suppliers (rfq_id, supplier_id) VALUES (?,?)");
            foreach ($sups as $sid) $sIns->execute([$id, $sid]);
            $pdo->commit();
            log_audit($pdo, 'save', 'rfqs', $id, null, ['rfq_number' => $num, 'items' => count($lines), 'suppliers' => $sups]);
            erp_out(['status' => 'success', 'id' => $id, 'message' => "{$num} saved."]);

        case 'rfq_send':
            $id = (int)erp_input('id');
            $r = erp_row($pdo, "SELECT * FROM rfqs WHERE id = ?", [$id]);
            if (!$r || !in_array($r['status'], ['draft', 'sent'], true)) erp_invalid('Only draft RFQs can be sent.');
            if (!(int)erp_val($pdo, "SELECT COUNT(*) FROM rfq_suppliers WHERE rfq_id = ?", [$id])) erp_invalid('Add at least one supplier to ask for a quotation.');
            $pdo->prepare("UPDATE rfqs SET status = 'sent' WHERE id = ?")->execute([$id]);
            $pdo->prepare("UPDATE rfq_suppliers SET sent_at = COALESCE(sent_at, NOW()) WHERE rfq_id = ?")->execute([$id]);
            log_audit($pdo, 'send', 'rfqs', $id, ['status' => $r['status']], ['status' => 'sent']);
            erp_out(['status' => 'success', 'message' => "{$r['rfq_number']} marked as sent. Print it or share it with the suppliers, then record their quotations."]);

        case 'rfq_cancel':
        case 'rfq_close':
            $id = (int)erp_input('id');
            $r = erp_row($pdo, "SELECT * FROM rfqs WHERE id = ?", [$id]);
            if (!$r) erp_invalid('RFQ not found.');
            if (in_array($r['status'], ['cancelled', 'closed'], true)) erp_invalid("This RFQ is already {$r['status']}.");
            if ($action === 'rfq_cancel' && $r['status'] === 'awarded') erp_invalid('Purchase orders were created from this RFQ — close it instead.');
            $to = $action === 'rfq_cancel' ? 'cancelled' : 'closed';
            $pdo->prepare("UPDATE rfqs SET status = ? WHERE id = ?")->execute([$to, $id]);
            log_audit($pdo, $to, 'rfqs', $id, ['status' => $r['status']], ['status' => $to, 'reason' => erp_input('reason')]);
            erp_out(['status' => 'success', 'message' => "{$r['rfq_number']} {$to}."]);

        // ============================================================ QUOTATIONS
        case 'quo_list':
            $w = []; $p = [];
            if ($rid = (int)erp_input('rfq_id', 0)) { $w[] = 'q.rfq_id = ?'; $p[] = $rid; }
            if ($sid = (int)erp_input('supplier_id', 0)) { $w[] = 'q.supplier_id = ?'; $p[] = $sid; }
            if ($s = erp_input('status')) { $w[] = 'q.status = ?'; $p[] = $s; }
            $rows = erp_rows($pdo, "SELECT q.*, s.supplier_name, r.rfq_number FROM supplier_quotations q JOIN suppliers s ON s.id = q.supplier_id LEFT JOIN rfqs r ON r.id = q.rfq_id" .
                                   ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY q.id DESC LIMIT 500", $p);
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'quo_get':
            $id = (int)erp_input('id');
            $q = erp_row($pdo, "SELECT q.*, s.supplier_name, r.rfq_number FROM supplier_quotations q JOIN suppliers s ON s.id = q.supplier_id LEFT JOIN rfqs r ON r.id = q.rfq_id WHERE q.id = ?", [$id]);
            if (!$q) erp_fail('Quotation not found.');
            $q['items'] = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT * FROM supplier_quotation_items WHERE quotation_id = ? ORDER BY id", [$id]));
            erp_out(['status' => 'success', 'record' => $q]);

        case 'quo_save':
            $id = (int)erp_input('id', 0);
            $rfqId = (int)erp_input('rfq_id', 0) ?: null;
            $supplierId = (int)erp_input('supplier_id');
            if (!erp_supplier_name($pdo, $supplierId)) erp_invalid('Choose a supplier.');
            $rfq = $rfqId ? erp_row($pdo, "SELECT * FROM rfqs WHERE id = ?", [$rfqId]) : null;
            if ($rfqId && (!$rfq || !in_array($rfq['status'], ['draft', 'sent', 'quoted'], true))) erp_invalid('Quotations can only be added to an open RFQ.');
            $lines = pc_lines($pdo, erp_json_input('items'), true);
            if (!$lines) erp_invalid('Enter the quoted rate for at least one item.');
            if ($rfq) {
                $rfqItems = [];
                foreach (erp_rows($pdo, "SELECT * FROM rfq_items WHERE rfq_id = ?", [$rfqId]) as $ri) $rfqItems[(int)$ri['id']] = $ri;
                foreach ($lines as $l) if (!$l['rfq_item_id'] || !isset($rfqItems[$l['rfq_item_id']])) erp_invalid('Every quoted line must belong to the RFQ.');
            }
            $date = erp_date(erp_input('quote_date'), true);
            $valid = erp_date(erp_input('valid_until'));
            if ($valid && $valid < $date) erp_invalid('Valid-until date cannot be before the quotation date.');
            $freight = erp_m(erp_num(erp_input('freight_amount', 0), 'Freight'));
            $sub = 0; $disc = 0; $tax = 0; $net = 0;
            foreach ($lines as $l) { $sub += $l['calc']['gross']; $disc += $l['calc']['discount']; $tax += $l['calc']['tax']; $net += $l['calc']['total']; }
            $grand = erp_m($net + $freight);
            $pdo->beginTransaction();
            if ($id) {
                $old = erp_row($pdo, "SELECT * FROM supplier_quotations WHERE id = ? FOR UPDATE", [$id]);
                if (!$old || $old['status'] !== 'received') erp_invalid('Only quotations not yet accepted can be edited.');
                $pdo->prepare("UPDATE supplier_quotations SET supplier_id=?, supplier_quote_ref=?, quote_date=?, valid_until=?, delivery_days=?, payment_terms=?, freight_terms=?, freight_amount=?,
                               subtotal=?, discount_total=?, tax_total=?, grand_total=?, notes=? WHERE id=?")
                    ->execute([$supplierId, erp_input('supplier_quote_ref') ?: null, $date, $valid, (int)erp_input('delivery_days', 0) ?: null, erp_input('payment_terms') ?: null, erp_input('freight_terms') ?: null,
                               $freight, erp_m($sub), erp_m($disc), erp_m($tax), $grand, erp_input('notes') ?: null, $id]);
                $pdo->prepare("DELETE FROM supplier_quotation_items WHERE quotation_id = ?")->execute([$id]);
                $num = $old['quote_number'];
            } else {
                if ($rfqId && erp_val($pdo, "SELECT id FROM supplier_quotations WHERE rfq_id = ? AND supplier_id = ? AND status IN ('received','accepted','partially_accepted')", [$rfqId, $supplierId]))
                    erp_invalid('This supplier already has a quotation on this RFQ — edit that one instead.');
                $num = next_document_number($pdo, 'quotation', 'SQ');
                $pdo->prepare("INSERT INTO supplier_quotations (quote_number, rfq_id, supplier_id, supplier_quote_ref, quote_date, valid_until, delivery_days, payment_terms, freight_terms, freight_amount,
                               subtotal, discount_total, tax_total, grand_total, status, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'received', ?,?)")
                    ->execute([$num, $rfqId, $supplierId, erp_input('supplier_quote_ref') ?: null, $date, $valid, (int)erp_input('delivery_days', 0) ?: null, erp_input('payment_terms') ?: null,
                               erp_input('freight_terms') ?: null, $freight, erp_m($sub), erp_m($disc), erp_m($tax), $grand, erp_input('notes') ?: null, erp_user()]);
                $id = (int)$pdo->lastInsertId();
            }
            $ins = $pdo->prepare("INSERT INTO supplier_quotation_items (quotation_id, rfq_item_id, item_type, item_id, quantity, unit, rate, discount_amount, tax_percent, tax_amount, line_total, remarks) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            foreach ($lines as $l) $ins->execute([$id, $l['rfq_item_id'], $l['type'], $l['id'], $l['qty'], $l['unit'], $l['rate'], $l['calc']['discount'], $l['tax'], $l['calc']['tax'], $l['calc']['total'], $l['remarks'] ?: null]);
            if ($rfqId) {
                $pdo->prepare("INSERT INTO rfq_suppliers (rfq_id, supplier_id, status) VALUES (?,?, 'quoted') ON DUPLICATE KEY UPDATE status = 'quoted'")->execute([$rfqId, $supplierId]);
                $pdo->prepare("UPDATE rfqs SET status = 'quoted' WHERE id = ? AND status IN ('draft','sent')")->execute([$rfqId]);
            }
            $pdo->commit();
            log_audit($pdo, 'save', 'supplier_quotations', $id, null, ['quote_number' => $num, 'supplier_id' => $supplierId, 'grand_total' => $grand]);
            erp_out(['status' => 'success', 'id' => $id, 'message' => "{$num} saved (₹" . number_format($grand, 2) . ').']);

        case 'quo_reject':
        case 'quo_cancel':
            $id = (int)erp_input('id');
            $q = erp_row($pdo, "SELECT * FROM supplier_quotations WHERE id = ?", [$id]);
            if (!$q || $q['status'] !== 'received') erp_invalid('Only open quotations can be rejected or cancelled.');
            $to = $action === 'quo_reject' ? 'rejected' : 'cancelled';
            $pdo->prepare("UPDATE supplier_quotations SET status = ?, notes = CONCAT(COALESCE(notes,''), ?) WHERE id = ?")->execute([$to, "\n[{$to} by " . erp_user() . ': ' . (erp_input('reason') ?: '-') . ']', $id]);
            log_audit($pdo, $to, 'supplier_quotations', $id, ['status' => 'received'], ['status' => $to, 'reason' => erp_input('reason')]);
            erp_out(['status' => 'success', 'message' => "{$q['quote_number']} {$to}."]);

        case 'rfq_compare':
            $id = (int)erp_input('id');
            $r = erp_row($pdo, "SELECT * FROM rfqs WHERE id = ?", [$id]);
            if (!$r) erp_fail('RFQ not found.');
            $items = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT * FROM rfq_items WHERE rfq_id = ? ORDER BY id", [$id]));
            $quotes = erp_rows($pdo, "SELECT q.*, s.supplier_name FROM supplier_quotations q JOIN suppliers s ON s.id = q.supplier_id WHERE q.rfq_id = ? AND q.status IN ('received','accepted','partially_accepted') ORDER BY q.grand_total", [$id]);
            $today = date('Y-m-d');
            foreach ($quotes as &$q) {
                $q['expired'] = $q['valid_until'] && $q['valid_until'] < $today;
                $ql = erp_rows($pdo, "SELECT * FROM supplier_quotation_items WHERE quotation_id = ?", [$q['id']]);
                $netSum = array_sum(array_map(function ($x) { return (float)$x['line_total'] - (float)$x['tax_amount']; }, $ql));
                $q['lines'] = [];
                foreach ($ql as $x) {
                    $net = (float)$x['line_total'] - (float)$x['tax_amount'];
                    $freightShare = $netSum > 0 ? (float)$q['freight_amount'] * $net / $netSum : 0;
                    $qty = max(0.0001, (float)$x['quantity']);
                    $q['lines'][(int)$x['rfq_item_id']] = ['rate' => (float)$x['rate'], 'discount' => (float)$x['discount_amount'], 'tax_percent' => (float)$x['tax_percent'], 'quantity' => (float)$x['quantity'],
                        'net_unit' => erp_u($net / $qty), 'landed_unit' => erp_u(($net + $freightShare) / $qty), 'line_total' => (float)$x['line_total'], 'awarded' => (int)$x['awarded'], 'remarks' => $x['remarks']];
                }
            }
            unset($q);
            $best = []; $supplierBest = [];
            foreach ($items as $it) {
                $b = null;
                foreach ($quotes as $q) {
                    if ($q['expired'] || !isset($q['lines'][(int)$it['id']])) continue;
                    $l = $q['lines'][(int)$it['id']];
                    if ($b === null || $l['landed_unit'] < $b['landed_unit'] - 0.00005 || (abs($l['landed_unit'] - $b['landed_unit']) < 0.00005 && (int)$q['delivery_days'] < (int)$b['delivery_days']))
                        $b = ['quotation_id' => (int)$q['id'], 'supplier_name' => $q['supplier_name'], 'landed_unit' => $l['landed_unit'], 'delivery_days' => $q['delivery_days']];
                }
                $best[(int)$it['id']] = $b;
            }
            $complete = array_filter($quotes, function ($q) use ($items) { return !$q['expired'] && count($q['lines']) >= count($items); });
            usort($complete, function ($a, $b) { return $a['grand_total'] <=> $b['grand_total']; });
            erp_out(['status' => 'success', 'rfq' => $r, 'items' => $items, 'quotations' => $quotes, 'best' => $best, 'cheapest_complete' => $complete ? (int)$complete[0]['id'] : null]);

        case 'rfq_award':
            // awards: [{rfq_item_id, quotation_id}] (per item) or quotation_id (whole quotation)
            $id = (int)erp_input('id');
            $r = erp_row($pdo, "SELECT * FROM rfqs WHERE id = ?", [$id]);
            if (!$r || !in_array($r['status'], ['sent', 'quoted', 'draft'], true)) erp_invalid('Only open RFQs can be awarded.');
            $awards = [];
            if ($qid = (int)erp_input('quotation_id', 0)) {
                foreach (erp_rows($pdo, "SELECT rfq_item_id FROM supplier_quotation_items WHERE quotation_id = ?", [$qid]) as $x) $awards[] = ['rfq_item_id' => (int)$x['rfq_item_id'], 'quotation_id' => $qid];
            } else {
                foreach (erp_json_input('awards') as $a) if ((int)($a['rfq_item_id'] ?? 0) && (int)($a['quotation_id'] ?? 0)) $awards[] = ['rfq_item_id' => (int)$a['rfq_item_id'], 'quotation_id' => (int)$a['quotation_id']];
            }
            if (!$awards) erp_invalid('Choose which supplier gets each item.');
            $byQuote = [];
            $seen = [];
            foreach ($awards as $a) {
                if (isset($seen[$a['rfq_item_id']])) erp_invalid('Each item can be awarded to one supplier only.');
                $seen[$a['rfq_item_id']] = true;
                $q = erp_row($pdo, "SELECT * FROM supplier_quotations WHERE id = ? AND rfq_id = ?", [$a['quotation_id'], $id]);
                if (!$q || !in_array($q['status'], ['received', 'partially_accepted'], true)) erp_invalid('A chosen quotation is not open.');
                if ($q['valid_until'] && $q['valid_until'] < date('Y-m-d')) erp_invalid("Quotation {$q['quote_number']} expired on {$q['valid_until']}.");
                $line = erp_row($pdo, "SELECT * FROM supplier_quotation_items WHERE quotation_id = ? AND rfq_item_id = ?", [$q['id'], $a['rfq_item_id']]);
                if (!$line) erp_invalid("Quotation {$q['quote_number']} does not quote one of the chosen items.");
                $byQuote[(int)$q['id']]['q'] = $q;
                $byQuote[(int)$q['id']]['lines'][] = $line;
            }
            $pdo->beginTransaction();
            $made = [];
            foreach ($byQuote as $qid => $g) {
                $q = $g['q'];
                $lines = []; $netAwarded = 0;
                foreach ($g['lines'] as $x) {
                    $item = erp_item($pdo, $x['item_type'], (int)$x['item_id']);
                    $calc = erp_line((float)$x['quantity'], (float)$x['rate'], (float)$x['discount_amount'], (float)$x['tax_percent']);
                    $netAwarded += $calc['net'];
                    $lines[] = ['type' => $x['item_type'], 'id' => (int)$x['item_id'], 'qty' => (float)$x['quantity'], 'unit' => $x['unit'], 'rate' => (float)$x['rate'], 'tax' => (float)$x['tax_percent'],
                                'calc' => $calc, 'sku' => $item['sku'] ?? null, 'expected' => $q['delivery_days'] ? date('Y-m-d', strtotime('+' . (int)$q['delivery_days'] . ' days')) : null];
                }
                $allNet = (float)erp_val($pdo, "SELECT COALESCE(SUM(line_total - tax_amount),0) FROM supplier_quotation_items WHERE quotation_id = ?", [$qid]);
                $freight = $allNet > 0 ? erp_m((float)$q['freight_amount'] * $netAwarded / $allNet) : (float)$q['freight_amount'];
                $po = pc_create_po($pdo, (int)$q['supplier_id'], (int)$r['warehouse_id'], $lines, $freight, "From {$r['rfq_number']} / quotation {$q['quote_number']}" . ($q['payment_terms'] ? " · terms: {$q['payment_terms']}" : ''), $r['pr_id'] ? (int)$r['pr_id'] : null);
                $pdo->prepare("INSERT INTO procurement_links (po_id, rfq_id, quotation_id) VALUES (?,?,?)")->execute([$po['id'], $id, $qid]);
                foreach ($g['lines'] as $x) $pdo->prepare("UPDATE supplier_quotation_items SET awarded = 1 WHERE id = ?")->execute([$x['id']]);
                $total = (int)erp_val($pdo, "SELECT COUNT(*) FROM supplier_quotation_items WHERE quotation_id = ?", [$qid]);
                $aw = (int)erp_val($pdo, "SELECT COUNT(*) FROM supplier_quotation_items WHERE quotation_id = ? AND awarded = 1", [$qid]);
                $pdo->prepare("UPDATE supplier_quotations SET status = ? WHERE id = ?")->execute([$aw >= $total ? 'accepted' : 'partially_accepted', $qid]);
                $pdo->prepare("UPDATE rfq_suppliers SET status = 'awarded' WHERE rfq_id = ? AND supplier_id = ?")->execute([$id, $q['supplier_id']]);
                $made[] = $po['number'];
            }
            $open = (int)erp_val($pdo, "SELECT COUNT(*) FROM rfq_items i WHERE i.rfq_id = ? AND NOT EXISTS (SELECT 1 FROM supplier_quotation_items x JOIN supplier_quotations q ON q.id = x.quotation_id WHERE x.rfq_item_id = i.id AND x.awarded = 1)", [$id]);
            if (!$open) {
                $pdo->prepare("UPDATE rfqs SET status = 'awarded' WHERE id = ?")->execute([$id]);
                $pdo->prepare("UPDATE supplier_quotations SET status = 'rejected' WHERE rfq_id = ? AND status = 'received'")->execute([$id]);
                $pdo->prepare("UPDATE rfq_suppliers SET status = 'not_awarded' WHERE rfq_id = ? AND status IN ('invited','quoted')")->execute([$id]);
            }
            $pdo->commit();
            log_audit($pdo, 'award', 'rfqs', $id, null, ['purchase_orders' => $made, 'awards' => $awards]);
            erp_out(['status' => 'success', 'purchase_orders' => $made, 'message' => 'Created ' . implode(', ', $made) . ' — sent for approval.']);

        // ============================================================ PO AMENDMENTS
        case 'po_extras':
            $id = (int)erp_input('id');
            $po = erp_row($pdo, "SELECT * FROM purchase_orders WHERE id = ?", [$id]);
            if (!$po) erp_fail('Purchase order not found.');
            $prof = erp_row($pdo, "SELECT credit_limit, credit_days FROM supplier_profiles WHERE supplier_id = ?", [$po['supplier_id']]);
            $tot = rep_supplier_totals($pdo, (int)$po['supplier_id']);
            erp_out(['status' => 'success',
                     'revisions' => erp_rows($pdo, "SELECT id, revision_no, reason, old_total, new_total, status, requested_by, requested_at, decided_by, decided_at, decision_remarks FROM purchase_order_revisions WHERE po_id = ? ORDER BY revision_no DESC", [$id]),
                     'link' => erp_row($pdo, "SELECT l.*, r.rfq_number, q.quote_number FROM procurement_links l LEFT JOIN rfqs r ON r.id = l.rfq_id LEFT JOIN supplier_quotations q ON q.id = l.quotation_id WHERE l.po_id = ?", [$id]),
                     'shipments' => erp_rows($pdo, "SELECT id, shipment_number, status, total_cost, lr_number, vehicle_number FROM inbound_shipments WHERE po_id = ? ORDER BY id", [$id]),
                     'approvals' => erp_rows($pdo, "SELECT request_number, status, submitted_by, submitted_at, decided_by, decided_at, remarks FROM approval_requests WHERE module IN ('purchase_order','po_amendment') AND entity_id = ? ORDER BY id DESC", [$id]),
                     'credit' => ['limit' => $prof['credit_limit'] ?? null, 'outstanding' => $tot['outstanding'],
                                  'over_limit' => $prof && $prof['credit_limit'] !== null && ($tot['outstanding'] + (float)$po['grand_total']) > (float)$prof['credit_limit'] + 0.005]]);

        case 'po_amend':
            $id = (int)erp_input('id');
            $reason = trim((string)erp_input('reason', ''));
            if ($reason === '') erp_invalid('Give the reason for the amendment.');
            $pdo->beginTransaction();
            $po = erp_row($pdo, "SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE", [$id]);
            if (!$po) erp_invalid('Purchase order not found.');
            if (!in_array($po['status'], ['approved', 'partially_received'], true)) erp_invalid('Only approved or partly received purchase orders are amended (drafts can simply be edited).');
            if (erp_val($pdo, "SELECT id FROM purchase_order_revisions WHERE po_id = ? AND status = 'pending'", [$id])) erp_invalid('An amendment is already waiting for approval.');
            $old = pc_po_snapshot($pdo, $id);
            $oldById = []; foreach ($old['items'] as $oi) $oldById[(int)$oi['id']] = $oi;
            $newItems = []; $sub = 0; $disc = 0; $tax = 0; $net = 0; $keep = [];
            foreach (erp_json_input('items') as $it) {
                $lid = (int)($it['id'] ?? 0);
                $o = $lid ? ($oldById[$lid] ?? null) : null;
                if ($lid && !$o) erp_invalid('A line does not belong to this purchase order.');
                $type = $o ? $o['item_type'] : erp_item_type($it['item_type'] ?? 'product');
                $iid = $o ? (int)$o['item_id'] : (int)($it['item_id'] ?? 0);
                $item = erp_item($pdo, $type, $iid);
                if (!$item) erp_invalid('An item in the list no longer exists.');
                $qty = erp_q(erp_num($it['quantity'] ?? 0, 'Quantity'));
                if ($type === 'product' && floor($qty) != $qty) erp_invalid("{$item['name']}: product packs must be whole numbers.");
                if ($o && $qty + 0.0005 < (float)$o['received_qty']) erp_invalid("{$item['name']}: quantity cannot be less than already received (" . erp_q($o['received_qty']) . ').');
                if (!$o && $qty <= 0) continue;
                $rate = erp_u(erp_num($it['rate'] ?? 0, 'Rate'));
                $taxP = erp_num($it['tax_percent'] ?? 0, 'Tax %');
                $l = erp_line($qty, $rate, erp_num($it['discount_amount'] ?? 0, 'Discount'), $taxP);
                $sub += $l['gross']; $disc += $l['discount']; $tax += $l['tax']; $net += $l['total'];
                $newItems[] = ['id' => $lid ?: null, 'item_type' => $type, 'item_id' => $iid, 'sku' => $item['sku'], 'quantity' => $qty, 'unit' => $type === 'product' ? 'pcs' : $item['unit'], 'rate' => $rate,
                               'discount_amount' => $l['discount'], 'tax_percent' => $taxP, 'tax_amount' => $l['tax'], 'line_total' => $l['total'], 'received_qty' => $o ? (float)$o['received_qty'] : 0, 'name' => $item['name']];
                if ($lid) $keep[$lid] = true;
            }
            foreach ($oldById as $lid => $o) if (!isset($keep[$lid]) && (float)$o['received_qty'] > 0) erp_invalid('A line with goods already received cannot be removed — reduce its quantity to the received quantity instead.');
            if (!$newItems) erp_invalid('The purchase order needs at least one line.');
            $other = erp_m(erp_num(erp_input('other_charges', $po['other_charges']), 'Other charges'));
            $exp = erp_date(erp_input('expected_delivery_date'));
            $new = ['expected_delivery_date' => $exp, 'notes' => erp_input('notes', $po['notes']), 'other_charges' => $other, 'subtotal' => erp_m($sub), 'discount_total' => erp_m($disc),
                    'tax_total' => erp_m($tax), 'grand_total' => erp_m($net + $other), 'items' => $newItems];
            $rev = (int)erp_val($pdo, "SELECT COALESCE(MAX(revision_no),0) FROM purchase_order_revisions WHERE po_id = ?", [$id]) + 1;
            $pdo->prepare("INSERT INTO purchase_order_revisions (po_id, revision_no, reason, old_snapshot, new_snapshot, old_total, new_total, prev_status, status, requested_by) VALUES (?,?,?,?,?,?,?,?, 'pending', ?)")
                ->execute([$id, $rev, mb_substr($reason, 0, 255), json_encode($old), json_encode($new), $po['grand_total'], $new['grand_total'], $po['status'], erp_user()]);
            $revId = (int)$pdo->lastInsertId();
            $apr = apr_open($pdo, 'po_amendment', (string)$revId, $id, $po['po_number'] . ' rev ' . $rev, "Amend {$po['po_number']} (rev {$rev}): ₹" . number_format($po['grand_total'], 2) . ' → ₹' . number_format($new['grand_total'], 2) . " — {$reason}",
                            $new['grand_total'], 'procurement_api.php', ['action' => 'po_amend_approve', 'revision_id' => $revId], ['action' => 'po_amend_reject', 'revision_id' => $revId, '_endpoint' => 'procurement_api.php']);
            $pdo->commit();
            log_audit($pdo, 'amend_request', 'purchase_orders', $id, ['grand_total' => $po['grand_total']], ['revision' => $rev, 'grand_total' => $new['grand_total'], 'reason' => $reason]);
            if (apr_is_approver($pdo, 'po_amendment') && erp_input('approve_now') === '1') { $_POST['revision_id'] = $revId; $action = 'po_amend_approve'; }
            else erp_out(['status' => 'success', 'revision_id' => $revId, 'message' => "Amendment rev {$rev} sent for approval ({$apr}). The current PO stays in force until it is approved."]);
            // fall through to approve when the requester is an approver and asked to approve now
        case 'po_amend_approve':
            if (!apr_is_approver($pdo, 'po_amendment')) erp_fail('You do not have permission to approve amendments.', 403);
            $revId = (int)erp_input('revision_id');
            $pdo->beginTransaction();
            $rv = erp_row($pdo, "SELECT * FROM purchase_order_revisions WHERE id = ? FOR UPDATE", [$revId]);
            if (!$rv || $rv['status'] !== 'pending') erp_invalid('This amendment is not waiting for approval.');
            $po = erp_row($pdo, "SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE", [$rv['po_id']]);
            if (!in_array($po['status'], ['approved', 'partially_received'], true)) erp_invalid("The purchase order is now {$po['status']} — the amendment cannot be applied.");
            $new = json_decode($rv['new_snapshot'], true);
            $cur = []; foreach (erp_rows($pdo, "SELECT * FROM purchase_order_items WHERE po_id = ? FOR UPDATE", [$po['id']]) as $ci) $cur[(int)$ci['id']] = $ci;
            $keep = [];
            foreach ($new['items'] as $ni) {
                if ($ni['id'] && isset($cur[(int)$ni['id']])) {
                    if ((float)$ni['quantity'] + 0.0005 < (float)$cur[(int)$ni['id']]['received_qty']) erp_invalid('More goods were received since the amendment was requested — request it again.');
                    $pdo->prepare("UPDATE purchase_order_items SET quantity=?, rate=?, discount_amount=?, tax_percent=?, tax_amount=?, line_total=? WHERE id=?")
                        ->execute([$ni['quantity'], $ni['rate'], $ni['discount_amount'], $ni['tax_percent'], $ni['tax_amount'], $ni['line_total'], $ni['id']]);
                    $keep[(int)$ni['id']] = true;
                } else {
                    $pdo->prepare("INSERT INTO purchase_order_items (po_id, item_type, item_id, sku, quantity, unit, rate, discount_amount, tax_percent, tax_amount, line_total) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                        ->execute([$po['id'], $ni['item_type'], $ni['item_id'], $ni['sku'], $ni['quantity'], $ni['unit'], $ni['rate'], $ni['discount_amount'], $ni['tax_percent'], $ni['tax_amount'], $ni['line_total']]);
                }
            }
            foreach ($cur as $cid => $ci) if (!isset($keep[$cid])) {
                if ((float)$ci['received_qty'] > 0) erp_invalid('A line removed by the amendment has received goods now — request it again.');
                $pdo->prepare("DELETE FROM purchase_order_items WHERE id = ?")->execute([$cid]);
            }
            $open = (float)erp_val($pdo, "SELECT COALESCE(SUM(GREATEST(quantity - received_qty, 0)),0) FROM purchase_order_items WHERE po_id = ?", [$po['id']]);
            $received = (float)erp_val($pdo, "SELECT COALESCE(SUM(received_qty),0) FROM purchase_order_items WHERE po_id = ?", [$po['id']]);
            $status = $open <= 0.0005 ? 'fully_received' : ($received > 0 ? 'partially_received' : 'approved');
            $pdo->prepare("UPDATE purchase_orders SET expected_delivery_date=?, notes=?, other_charges=?, subtotal=?, discount_total=?, tax_total=?, grand_total=?, status=? WHERE id=?")
                ->execute([$new['expected_delivery_date'] ?: $po['expected_delivery_date'], $new['notes'], $new['other_charges'], $new['subtotal'], $new['discount_total'], $new['tax_total'], $new['grand_total'], $status, $po['id']]);
            $pdo->prepare("UPDATE purchase_order_revisions SET status = 'approved', decided_by = ?, decided_at = NOW(), decision_remarks = ? WHERE id = ?")->execute([erp_user(), erp_input('_approval_remarks') ?: null, $revId]);
            apr_close($pdo, 'po_amendment', (string)$revId, 'approved', erp_input('_approval_remarks') ?: null);
            $pdo->commit();
            log_audit($pdo, 'amend_approve', 'purchase_orders', $po['id'], json_decode($rv['old_snapshot'], true), $new);
            erp_out(['status' => 'success', 'message' => "{$po['po_number']} revision {$rv['revision_no']} approved and applied (₹" . number_format($new['grand_total'], 2) . ').']);

        case 'po_amend_reject':
        case 'po_amend_cancel':
            $revId = (int)erp_input('revision_id');
            $rv = erp_row($pdo, "SELECT r.*, po.po_number FROM purchase_order_revisions r JOIN purchase_orders po ON po.id = r.po_id WHERE r.id = ?", [$revId]);
            if (!$rv || $rv['status'] !== 'pending') erp_invalid('This amendment is not pending.');
            $to = $action === 'po_amend_reject' ? 'rejected' : 'cancelled';
            $pdo->prepare("UPDATE purchase_order_revisions SET status = ?, decided_by = ?, decided_at = NOW(), decision_remarks = ? WHERE id = ?")->execute([$to, erp_user(), erp_input('_approval_remarks') ?: erp_input('reason') ?: null, $revId]);
            apr_close($pdo, 'po_amendment', (string)$revId, $to, erp_input('_approval_remarks') ?: erp_input('reason') ?: null);
            log_audit($pdo, 'amend_' . $to, 'purchase_orders', $rv['po_id'], null, ['revision' => $rv['revision_no']]);
            erp_out(['status' => 'success', 'message' => "{$rv['po_number']} revision {$rv['revision_no']} {$to}; the purchase order is unchanged."]);

        case 'po_reject':
            $id = (int)erp_input('id');
            $po = erp_row($pdo, "SELECT * FROM purchase_orders WHERE id = ?", [$id]);
            if (!$po || $po['status'] !== 'pending_approval') erp_invalid('Only purchase orders waiting for approval can be rejected.');
            $why = erp_input('_approval_remarks') ?: erp_input('reason') ?: '';
            $pdo->prepare("UPDATE purchase_orders SET status = 'draft', notes = CONCAT(COALESCE(notes,''), ?) WHERE id = ?")->execute(["\n[Rejected by " . erp_user() . ($why ? ": {$why}" : '') . ']', $id]);
            apr_close($pdo, 'purchase_order', (string)$id, 'rejected', $why ?: null);
            log_audit($pdo, 'reject', 'purchase_orders', $id, ['status' => 'pending_approval'], ['status' => 'draft', 'reason' => $why]);
            erp_out(['status' => 'success', 'message' => "{$po['po_number']} rejected and returned to draft."]);

        // ============================================================ TRANSPORT / LOGISTICS
        case 'shp_list':
            $w = []; $p = [];
            if ($s = erp_input('status')) { $w[] = 's.status = ?'; $p[] = $s; }
            if ($sid = (int)erp_input('supplier_id', 0)) { $w[] = 's.supplier_id = ?'; $p[] = $sid; }
            if ($d = erp_date(erp_input('date_from'))) { $w[] = 'COALESCE(s.dispatch_date, DATE(s.created_at)) >= ?'; $p[] = $d; }
            if ($d = erp_date(erp_input('date_to'))) { $w[] = 'COALESCE(s.dispatch_date, DATE(s.created_at)) <= ?'; $p[] = $d; }
            if ($q = trim((string)erp_input('q', ''))) { $w[] = '(s.shipment_number LIKE ? OR s.lr_number LIKE ? OR s.vehicle_number LIKE ? OR s.transport_company LIKE ? OR po.po_number LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%"); }
            $rows = erp_rows($pdo, "SELECT s.*, sup.supplier_name, po.po_number, g.grn_number FROM inbound_shipments s LEFT JOIN suppliers sup ON sup.id = s.supplier_id
                                    LEFT JOIN purchase_orders po ON po.id = s.po_id LEFT JOIN goods_receipts g ON g.id = s.grn_id" . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY s.id DESC LIMIT 500", $p);
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'shp_get':
            $id = (int)erp_input('id');
            $s = erp_row($pdo, "SELECT s.*, sup.supplier_name, po.po_number, g.grn_number, g.status AS grn_status FROM inbound_shipments s LEFT JOIN suppliers sup ON sup.id = s.supplier_id
                                LEFT JOIN purchase_orders po ON po.id = s.po_id LEFT JOIN goods_receipts g ON g.id = s.grn_id WHERE s.id = ?", [$id]);
            if (!$s) erp_fail('Shipment not found.');
            $s['payments'] = erp_rows($pdo, "SELECT * FROM inbound_shipment_payments WHERE shipment_id = ? ORDER BY id", [$id]);
            $s['cost_entries'] = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT item_type, item_id, value, entry_date, status FROM inventory_cost_entries WHERE reference_type = 'shipment' AND source_id = ?", [$id]));
            $s['expenses'] = erp_rows($pdo, "SELECT id, expense_number, total, status FROM expenses WHERE shipment_id = ?", [$id]);
            $s['bill_charges'] = $s['grn_id'] ? (float)erp_val($pdo, "SELECT COALESCE(SUM(c.amount),0) FROM purchase_invoice_charges c JOIN purchase_invoices pi ON pi.id = c.pinv_id WHERE pi.grn_id = ? AND pi.status = 'posted' AND c.add_to_cost = 1 AND c.charge_type IN ('freight','transport','loading','unloading')", [$s['grn_id']]) : 0;
            erp_out(['status' => 'success', 'record' => $s]);

        case 'shp_save':
            $id = (int)erp_input('id', 0);
            $poId = (int)erp_input('po_id', 0) ?: null;
            $grnId = (int)erp_input('grn_id', 0) ?: null;
            $po = $poId ? erp_row($pdo, "SELECT id, supplier_id FROM purchase_orders WHERE id = ?", [$poId]) : null;
            $grn = $grnId ? erp_row($pdo, "SELECT id, supplier_id, po_id FROM goods_receipts WHERE id = ?", [$grnId]) : null;
            if ($poId && !$po) erp_invalid('Purchase order not found.');
            if ($grnId && !$grn) erp_invalid('Goods receipt not found.');
            if ($grn && $po && (int)$grn['po_id'] !== (int)$po['id']) erp_invalid('The goods receipt belongs to a different purchase order.');
            if ($grn && !$po && $grn['po_id']) $poId = (int)$grn['po_id'];
            $supplierId = $po ? (int)$po['supplier_id'] : ($grn ? (int)$grn['supplier_id'] : ((int)erp_input('supplier_id', 0) ?: null));
            $f = [];
            foreach (['freight_amount', 'loading_charge', 'unloading_charge', 'handling_charge', 'other_charge'] as $k) $f[$k] = erp_m(erp_num(erp_input($k, 0), str_replace('_', ' ', $k)));
            $total = pc_shipment_total($f);
            $treat = in_array(erp_input('cost_treatment'), ['landed', 'expense', 'supplier_paid'], true) ? erp_input('cost_treatment') : 'landed';
            $mode = in_array(erp_input('transport_mode'), ['road', 'rail', 'air', 'sea', 'courier', 'own_vehicle', 'other'], true) ? erp_input('transport_mode') : 'road';
            $dd = erp_date(erp_input('dispatch_date')); $ea = erp_date(erp_input('expected_arrival')); $aa = erp_date(erp_input('actual_arrival'));
            if ($dd && $ea && $ea < $dd) erp_invalid('Expected arrival cannot be before dispatch.');
            $vals = [$poId, $grnId, $supplierId, erp_input('transporter_name') ?: null, erp_input('transport_company') ?: null, $mode, strtoupper(trim((string)erp_input('vehicle_number', ''))) ?: null,
                     erp_input('driver_name') ?: null, erp_input('driver_phone') ?: null, erp_input('lr_number') ?: null, erp_input('consignment_number') ?: null, $dd, $ea, $aa,
                     $f['freight_amount'], $f['loading_charge'], $f['unloading_charge'], $f['handling_charge'], $f['other_charge'], $total, $treat, erp_input('notes') ?: null];
            $pdo->beginTransaction();
            if ($id) {
                $old = erp_row($pdo, "SELECT * FROM inbound_shipments WHERE id = ? FOR UPDATE", [$id]);
                if (!$old || $old['status'] === 'cancelled') erp_invalid('Shipment not found or cancelled.');
                if ((int)$old['cost_applied'] && (abs(pc_shipment_total($old) - $total) >= 0.005 || $old['cost_treatment'] !== $treat || (int)$old['grn_id'] !== (int)$grnId)) erp_invalid('Costs are already added to stock cost — they can no longer change.');
                if ($total + 0.005 < (float)$old['amount_paid']) erp_invalid('Total cost cannot be less than what is already paid.');
                $pdo->prepare("UPDATE inbound_shipments SET po_id=?, grn_id=?, supplier_id=?, transporter_name=?, transport_company=?, transport_mode=?, vehicle_number=?, driver_name=?, driver_phone=?, lr_number=?,
                               consignment_number=?, dispatch_date=?, expected_arrival=?, actual_arrival=?, freight_amount=?, loading_charge=?, unloading_charge=?, handling_charge=?, other_charge=?, total_cost=?,
                               cost_treatment=?, notes=? WHERE id=?")->execute(array_merge($vals, [$id]));
                $num = $old['shipment_number'];
            } else {
                $num = next_document_number($pdo, 'shipment', 'SHP');
                $pdo->prepare("INSERT INTO inbound_shipments (po_id, grn_id, supplier_id, transporter_name, transport_company, transport_mode, vehicle_number, driver_name, driver_phone, lr_number,
                               consignment_number, dispatch_date, expected_arrival, actual_arrival, freight_amount, loading_charge, unloading_charge, handling_charge, other_charge, total_cost,
                               cost_treatment, notes, shipment_number, status, created_by) VALUES (" . implode(',', array_fill(0, 23, '?')) . ", ?, ?)")
                    ->execute(array_merge($vals, [$num, $aa ? 'arrived' : ($dd ? 'in_transit' : 'planned'), erp_user()]));
                $id = (int)$pdo->lastInsertId();
            }
            $pdo->commit();
            log_audit($pdo, 'save', 'inbound_shipments', $id, null, ['shipment_number' => $num, 'total_cost' => $total, 'treatment' => $treat]);
            erp_out(['status' => 'success', 'id' => $id, 'message' => "{$num} saved."]);

        case 'shp_status':
            $id = (int)erp_input('id');
            $to = erp_input('status');
            if (!in_array($to, ['in_transit', 'arrived'], true)) erp_invalid('Invalid status.');
            $s = erp_row($pdo, "SELECT * FROM inbound_shipments WHERE id = ?", [$id]);
            if (!$s || $s['status'] === 'cancelled') erp_invalid('Shipment not found or cancelled.');
            $date = erp_date(erp_input('date')) ?: date('Y-m-d');
            if ($to === 'arrived') $pdo->prepare("UPDATE inbound_shipments SET status = 'arrived', actual_arrival = ? WHERE id = ?")->execute([$date, $id]);
            else $pdo->prepare("UPDATE inbound_shipments SET status = 'in_transit', dispatch_date = COALESCE(dispatch_date, ?) WHERE id = ?")->execute([$date, $id]);
            log_audit($pdo, 'update', 'inbound_shipments', $id, ['status' => $s['status']], ['status' => $to]);
            erp_out(['status' => 'success', 'message' => "{$s['shipment_number']}: {$to}."]);

        case 'shp_cancel':
            $id = (int)erp_input('id');
            $s = erp_row($pdo, "SELECT * FROM inbound_shipments WHERE id = ?", [$id]);
            if (!$s || $s['status'] === 'cancelled') erp_invalid('Already cancelled.');
            if ((int)$s['cost_applied']) erp_invalid('Its cost is already in stock cost — it cannot be cancelled.');
            if ((float)$s['amount_paid'] > 0) erp_invalid('Payments are recorded — cancel them first.');
            $pdo->prepare("UPDATE inbound_shipments SET status = 'cancelled' WHERE id = ?")->execute([$id]);
            log_audit($pdo, 'cancel', 'inbound_shipments', $id, ['status' => $s['status']], ['status' => 'cancelled', 'reason' => erp_input('reason')]);
            erp_out(['status' => 'success', 'message' => "{$s['shipment_number']} cancelled."]);

        case 'shp_apply_cost':
            // Adds the transport cost to the landed cost of the goods of the linked (posted) GRN — once.
            $id = (int)erp_input('id');
            $pdo->beginTransaction();
            $s = erp_row($pdo, "SELECT * FROM inbound_shipments WHERE id = ? FOR UPDATE", [$id]);
            if (!$s || $s['status'] === 'cancelled') erp_invalid('Shipment not found or cancelled.');
            if ((int)$s['cost_applied']) erp_invalid('This transport cost is already added to stock cost (it is never added twice).');
            if ($s['cost_treatment'] !== 'landed') erp_invalid('This shipment is set as ' . str_replace('_', ' ', $s['cost_treatment']) . ', not landed cost.');
            if ((float)$s['total_cost'] <= 0) erp_invalid('Enter the transport costs first.');
            $g = $s['grn_id'] ? erp_row($pdo, "SELECT * FROM goods_receipts WHERE id = ?", [$s['grn_id']]) : null;
            if (!$g || $g['status'] !== 'posted') erp_invalid('Link the shipment to a POSTED goods receipt first.');
            $billCharges = (float)erp_val($pdo, "SELECT COALESCE(SUM(c.amount),0) FROM purchase_invoice_charges c JOIN purchase_invoices pi ON pi.id = c.pinv_id WHERE pi.grn_id = ? AND pi.status = 'posted' AND c.add_to_cost = 1 AND c.charge_type IN ('freight','transport','loading','unloading')", [$g['id']]);
            if ($billCharges > 0 && erp_input('confirm_separate') !== '1')
                erp_out(['status' => 'confirm', 'message' => "The supplier bill for {$g['grn_number']} already includes ₹" . number_format($billCharges, 2) . " freight/loading in stock cost. Add this transport cost too only if it is a SEPARATE charge (not the same freight)."]);
            $lines = erp_rows($pdo, "SELECT id, item_type, item_id, accepted_qty, rate FROM goods_receipt_items WHERE grn_id = ? AND accepted_qty > 0", [$g['id']]);
            if (!$lines) erp_invalid('The goods receipt has no accepted goods.');
            $base = array_sum(array_map(function ($l) { return (float)$l['accepted_qty'] * (float)$l['rate']; }, $lines));
            $total = (float)$s['total_cost']; $done = 0.0;
            foreach ($lines as $i => $l) {
                $share = $i === count($lines) - 1 ? erp_m($total - $done) : erp_m($base > 0 ? $total * (float)$l['accepted_qty'] * (float)$l['rate'] / $base : $total / count($lines));
                $done += $share;
                if ($share != 0) erp_cost_entry($pdo, $l['item_type'], (int)$l['item_id'], 'landed', 0, $share, 'shipment', $s['shipment_number'], $id, "Transport {$s['shipment_number']} for {$g['grn_number']}");
            }
            $pdo->prepare("UPDATE inbound_shipments SET cost_applied = 1, cost_applied_at = NOW() WHERE id = ?")->execute([$id]);
            $pdo->commit();
            log_audit($pdo, 'apply_cost', 'inbound_shipments', $id, null, ['grn' => $g['grn_number'], 'total' => $total]);
            erp_out(['status' => 'success', 'message' => '₹' . number_format($total, 2) . " added to the landed cost of the goods on {$g['grn_number']}."]);

        case 'shp_pay':
            $id = (int)erp_input('id');
            $amount = erp_m(erp_num(erp_input('amount'), 'Amount', false));
            $date = erp_date(erp_input('payment_date'), true);
            $mode = (string)erp_input('payment_mode', 'bank_transfer');
            if (!in_array($mode, ['cash', 'bank_transfer', 'upi', 'cheque', 'card', 'other'], true)) erp_invalid('Choose a payment mode.');
            $ref = trim((string)erp_input('reference_number', ''));
            if (in_array($mode, ['bank_transfer', 'upi', 'cheque'], true) && $ref === '') erp_invalid('Enter the reference / UTR / cheque number.');
            $pdo->beginTransaction();
            $s = erp_row($pdo, "SELECT * FROM inbound_shipments WHERE id = ? FOR UPDATE", [$id]);
            if (!$s || $s['status'] === 'cancelled') erp_invalid('Shipment not found or cancelled.');
            if ($s['cost_treatment'] !== 'landed') erp_invalid($s['cost_treatment'] === 'expense' ? 'This transport is an expense — record it with "Record as expense".' : 'The supplier pays this transport.');
            if ($amount > erp_m($s['total_cost'] - $s['amount_paid']) + 0.005) erp_invalid('Amount is more than the unpaid ₹' . number_format($s['total_cost'] - $s['amount_paid'], 2) . '.');
            if ($ref !== '' && erp_val($pdo, "SELECT id FROM inbound_shipment_payments WHERE reference_number = ? AND status = 'completed'", [$ref])) erp_invalid("Reference {$ref} is already used for a transport payment.");
            $who = $s['transport_company'] ?: $s['transporter_name'];
            $txn = erp_cash_entry($pdo, 'payment_made', 'Transport (inward)', $amount, $date, $mode, $who, 'shipment', $s['shipment_number'], "Transport {$s['shipment_number']}" . ($s['lr_number'] ? " LR {$s['lr_number']}" : ''));
            $pdo->prepare("INSERT INTO inbound_shipment_payments (shipment_id, payment_date, amount, payment_mode, reference_number, accounts_transaction_id, created_by) VALUES (?,?,?,?,?,?,?)")
                ->execute([$id, $date, $amount, $mode, $ref ?: null, $txn, erp_user()]);
            $pdo->prepare("UPDATE inbound_shipments SET amount_paid = amount_paid + ? WHERE id = ?")->execute([$amount, $id]);
            $pdo->commit();
            log_audit($pdo, 'payment', 'inbound_shipments', $id, null, ['amount' => $amount, 'mode' => $mode, 'reference' => $ref]);
            erp_out(['status' => 'success', 'message' => '₹' . number_format($amount, 2) . " paid for {$s['shipment_number']} (also in Transactions)."]);

        case 'shp_pay_cancel':
            $pid = (int)erp_input('payment_id');
            $reason = trim((string)erp_input('reason', ''));
            if ($reason === '') erp_invalid('Give a reason.');
            $pdo->beginTransaction();
            $p = erp_row($pdo, "SELECT * FROM inbound_shipment_payments WHERE id = ? FOR UPDATE", [$pid]);
            if (!$p || $p['status'] !== 'completed') erp_invalid('Payment not found or already cancelled.');
            $pdo->prepare("UPDATE inbound_shipment_payments SET status = 'cancelled' WHERE id = ?")->execute([$pid]);
            $pdo->prepare("UPDATE inbound_shipments SET amount_paid = amount_paid - ? WHERE id = ?")->execute([$p['amount'], $p['shipment_id']]);
            erp_cancel_cash_entry($pdo, $p['accounts_transaction_id'] ? (int)$p['accounts_transaction_id'] : null);
            $pdo->commit();
            log_audit($pdo, 'cancel_payment', 'inbound_shipments', $p['shipment_id'], $p, ['reason' => $reason]);
            erp_out(['status' => 'success', 'message' => 'Transport payment cancelled (Transactions entry cancelled too).']);

        case 'shp_to_expense':
            $id = (int)erp_input('id');
            $s = erp_row($pdo, "SELECT * FROM inbound_shipments WHERE id = ?", [$id]);
            if (!$s || $s['status'] === 'cancelled') erp_invalid('Shipment not found or cancelled.');
            if ($s['cost_treatment'] !== 'expense') erp_invalid('Set the cost treatment to "expense" first.');
            if (erp_val($pdo, "SELECT id FROM expenses WHERE shipment_id = ? AND status <> 'cancelled'", [$id])) erp_invalid('An expense already exists for this shipment.');
            $acc = (int)erp_val($pdo, "SELECT id FROM chart_of_accounts WHERE system_key = 'exp_transport'");
            $num = next_document_number($pdo, 'expense', 'EXP');
            $pdo->prepare("INSERT INTO expenses (expense_number, expense_date, account_id, category, payee, supplier_id, amount, tax_amount, total, payment_status, payment_mode, description, shipment_id, status, created_by)
                           VALUES (?,?,?, 'Transport', ?,?,?, 0, ?, 'unpaid', 'bank_transfer', ?, ?, 'draft', ?)")
                ->execute([$num, $s['actual_arrival'] ?: date('Y-m-d'), $acc, $s['transport_company'] ?: $s['transporter_name'], $s['supplier_id'], $s['total_cost'], $s['total_cost'],
                           "Transport {$s['shipment_number']}" . ($s['lr_number'] ? " LR {$s['lr_number']}" : ''), $id, erp_user()]);
            $eid = (int)$pdo->lastInsertId();
            log_audit($pdo, 'create', 'expenses', $eid, null, ['from_shipment' => $s['shipment_number']]);
            erp_out(['status' => 'success', 'expense_id' => $eid, 'message' => "Draft expense {$num} created — review and post it in Expenses."]);

        // ============================================================ QUALITY CHECK
        case 'qc_list':
            $w = []; $p = [];
            if ($s = erp_input('status')) { $w[] = 'q.status = ?'; $p[] = $s; }
            if ($d = erp_date(erp_input('date_from'))) { $w[] = 'DATE(q.created_at) >= ?'; $p[] = $d; }
            if ($d = erp_date(erp_input('date_to'))) { $w[] = 'DATE(q.created_at) <= ?'; $p[] = $d; }
            if ($qq = trim((string)erp_input('q', ''))) { $w[] = '(q.qc_number LIKE ? OR g.grn_number LIKE ? OR s.supplier_name LIKE ?)'; array_push($p, "%$qq%", "%$qq%", "%$qq%"); }
            $rows = erp_rows($pdo, "SELECT q.*, g.grn_number, g.status AS grn_status, s.supplier_name,
                                           (SELECT COALESCE(SUM(received_qty),0) FROM quality_check_items i WHERE i.qc_id = q.id) AS received_qty,
                                           (SELECT COALESCE(SUM(accepted_qty),0) FROM quality_check_items i WHERE i.qc_id = q.id) AS accepted_qty,
                                           (SELECT COALESCE(SUM(rejected_qty + damaged_qty),0) FROM quality_check_items i WHERE i.qc_id = q.id) AS rejected_qty
                                    FROM quality_checks q JOIN goods_receipts g ON g.id = q.grn_id JOIN suppliers s ON s.id = g.supplier_id" .
                                   ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY q.id DESC LIMIT 500", $p);
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'qc_get':
            $id = (int)erp_input('id');
            $q = erp_row($pdo, "SELECT q.*, g.grn_number, g.status AS grn_status, g.id AS grn_id, s.supplier_name, po.po_number FROM quality_checks q JOIN goods_receipts g ON g.id = q.grn_id
                                JOIN suppliers s ON s.id = g.supplier_id LEFT JOIN purchase_orders po ON po.id = g.po_id WHERE q.id = ?", [$id]);
            if (!$q) erp_fail('Quality check not found.');
            $q['items'] = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT * FROM quality_check_items WHERE qc_id = ? ORDER BY id", [$id]));
            erp_out(['status' => 'success', 'record' => $q]);

        case 'qc_create':
            $grnId = (int)erp_input('grn_id');
            $pdo->beginTransaction();
            $g = erp_row($pdo, "SELECT * FROM goods_receipts WHERE id = ? FOR UPDATE", [$grnId]);
            if (!$g || $g['status'] !== 'draft') erp_invalid('Quality checks are done on a DRAFT goods receipt (before stock is added). Save the GRN as draft first.');
            if (erp_val($pdo, "SELECT id FROM quality_checks WHERE grn_id = ? AND status <> 'cancelled'", [$grnId])) erp_invalid('This goods receipt already has a quality check.');
            $items = erp_rows($pdo, "SELECT * FROM goods_receipt_items WHERE grn_id = ?", [$grnId]);
            if (!$items) erp_invalid('The goods receipt has no lines.');
            $num = next_document_number($pdo, 'qc', 'QC');
            $pdo->prepare("INSERT INTO quality_checks (qc_number, grn_id, status, created_by) VALUES (?,?, 'pending', ?)")->execute([$num, $grnId, erp_user()]);
            $qid = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO quality_check_items (qc_id, grn_item_id, item_type, item_id, batch_number, received_qty, accepted_qty, rejected_qty) VALUES (?,?,?,?,?,?,?,?)");
            foreach ($items as $i) {
                $ins->execute([$qid, $i['id'], $i['item_type'], $i['item_id'], $i['batch_number'], $i['received_qty'], $i['received_qty'], 0]);
                $pdo->prepare("UPDATE goods_receipt_items SET qc_status = 'pending' WHERE id = ?")->execute([$i['id']]);
            }
            $pdo->commit();
            log_audit($pdo, 'create', 'quality_checks', $qid, null, ['qc_number' => $num, 'grn' => $g['grn_number']]);
            erp_out(['status' => 'success', 'id' => $qid, 'message' => "{$num} created — the goods stay in quarantine until the check is completed."]);

        case 'qc_save':
        case 'qc_complete':
            $id = (int)erp_input('id');
            $pdo->beginTransaction();
            $q = erp_row($pdo, "SELECT * FROM quality_checks WHERE id = ? FOR UPDATE", [$id]);
            if (!$q || $q['status'] !== 'pending') erp_invalid('Only pending quality checks can be edited.');
            $g = erp_row($pdo, "SELECT * FROM goods_receipts WHERE id = ? FOR UPDATE", [$q['grn_id']]);
            if (!$g || $g['status'] !== 'draft') erp_invalid('The goods receipt is no longer a draft.');
            $cur = []; foreach (erp_rows($pdo, "SELECT * FROM quality_check_items WHERE qc_id = ?", [$id]) as $ci) $cur[(int)$ci['id']] = $ci;
            $acc = 0; $rejAll = 0; $recAll = 0;
            foreach (erp_json_input('items') as $it) {
                $ci = $cur[(int)($it['id'] ?? 0)] ?? null;
                if (!$ci) continue;
                $item = erp_item($pdo, $ci['item_type'], (int)$ci['item_id']);
                $rec = (float)$ci['received_qty'];
                $a = erp_q(erp_num($it['accepted_qty'] ?? 0, 'Accepted'));
                $r = erp_q(erp_num($it['rejected_qty'] ?? 0, 'Rejected'));
                $d = erp_q(erp_num($it['damaged_qty'] ?? 0, 'Damaged'));
                if ($ci['item_type'] === 'product' && (floor($a) != $a || floor($r) != $r || floor($d) != $d)) erp_invalid("{$item['name']}: product packs must be whole numbers.");
                if (abs($a + $r + $d - $rec) > 0.0005) erp_invalid("{$item['name']}: accepted + rejected + damaged must equal the received {$rec}.");
                $reason = trim((string)($it['rejection_reason'] ?? ''));
                if (($r + $d) > 0 && $reason === '' && $action === 'qc_complete') erp_invalid("{$item['name']}: give the rejection / damage reason.");
                $res = ($r + $d) <= 0 ? 'passed' : ($a > 0 ? 'partial' : 'failed');
                $pdo->prepare("UPDATE quality_check_items SET accepted_qty=?, rejected_qty=?, damaged_qty=?, rejection_reason=?, result=? WHERE id=?")->execute([$a, $r, $d, $reason ?: null, $res, $ci['id']]);
                $cur[(int)$ci['id']] = array_merge($ci, ['accepted_qty' => $a, 'rejected_qty' => $r, 'damaged_qty' => $d, 'rejection_reason' => $reason, 'result' => $res]);
            }
            $pdo->prepare("UPDATE quality_checks SET inspection_date = ?, inspected_by = ?, notes = ? WHERE id = ?")
                ->execute([erp_date(erp_input('inspection_date')) ?: date('Y-m-d'), erp_input('inspected_by') ?: erp_user(), erp_input('notes') ?: null, $id]);
            $msg = "{$q['qc_number']} saved.";
            if ($action === 'qc_complete') {
                foreach ($cur as $ci) {
                    $acc += (float)$ci['accepted_qty']; $rejAll += (float)$ci['rejected_qty'] + (float)$ci['damaged_qty']; $recAll += (float)$ci['received_qty'];
                    // apply the result to the draft GRN line (stock is added only when the GRN is posted)
                    $reason = trim(($ci['rejection_reason'] ?? '') . ((float)$ci['damaged_qty'] > 0 ? ' (damaged ' . erp_q($ci['damaged_qty']) . ')' : ''));
                    $pdo->prepare("UPDATE goods_receipt_items SET accepted_qty = ?, rejected_qty = ?, rejection_reason = ?, qc_status = ? WHERE id = ?")
                        ->execute([$ci['accepted_qty'], erp_q((float)$ci['rejected_qty'] + (float)$ci['damaged_qty']), $reason ?: null, $ci['result'] === 'pending' ? 'passed' : $ci['result'], $ci['grn_item_id']]);
                }
                $status = $rejAll <= 0.0005 ? 'passed' : ($acc > 0.0005 ? 'partially_passed' : 'rejected');
                $pdo->prepare("UPDATE quality_checks SET status = ?, completed_at = NOW() WHERE id = ?")->execute([$status, $id]);
                $msg = "{$q['qc_number']} completed: " . str_replace('_', ' ', $status) . ". Accepted " . erp_q($acc) . ', rejected/damaged ' . erp_q($rejAll) . ". Now post {$g['grn_number']} to add the accepted goods to stock.";
            }
            $pdo->commit();
            log_audit($pdo, $action === 'qc_complete' ? 'complete' : 'update', 'quality_checks', $id, null, ['items' => array_values($cur)]);
            erp_out(['status' => 'success', 'message' => $msg, 'grn_id' => (int)$g['id']]);

        case 'qc_cancel':
            $id = (int)erp_input('id');
            $q = erp_row($pdo, "SELECT q.*, g.status AS grn_status FROM quality_checks q JOIN goods_receipts g ON g.id = q.grn_id WHERE q.id = ?", [$id]);
            if (!$q || $q['status'] === 'cancelled') erp_invalid('Already cancelled.');
            if ($q['grn_status'] !== 'draft') erp_invalid('The goods receipt is already posted — the quality check is final.');
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE quality_checks SET status = 'cancelled' WHERE id = ?")->execute([$id]);
            $pdo->prepare("UPDATE goods_receipt_items SET qc_status = 'passed' WHERE grn_id = ? AND qc_status = 'pending'")->execute([$q['grn_id']]);
            $pdo->commit();
            log_audit($pdo, 'cancel', 'quality_checks', $id, ['status' => $q['status']], ['status' => 'cancelled', 'reason' => erp_input('reason')]);
            erp_out(['status' => 'success', 'message' => "{$q['qc_number']} cancelled."]);

        case 'qc_for_grn':
            $grnId = (int)erp_input('grn_id');
            erp_out(['status' => 'success', 'qc' => erp_row($pdo, "SELECT id, qc_number, status FROM quality_checks WHERE grn_id = ? AND status <> 'cancelled' ORDER BY id DESC LIMIT 1", [$grnId]),
                     'required' => erp_setting($pdo, 'erp_qc_required', '0') === '1',
                     'shipments' => erp_rows($pdo, "SELECT id, shipment_number, status, total_cost, cost_applied FROM inbound_shipments WHERE grn_id = ?", [$grnId])]);

        // ============================================================ DEBIT NOTES
        case 'dn_list':
            $w = []; $p = [];
            if ($sid = (int)erp_input('supplier_id', 0)) { $w[] = 'd.supplier_id = ?'; $p[] = $sid; }
            if ($s = erp_input('status')) { $w[] = 'd.status = ?'; $p[] = $s; }
            $rows = erp_rows($pdo, "SELECT d.*, s.supplier_name, r.return_number, r.settlement, pi.supplier_invoice_no FROM debit_notes d JOIN suppliers s ON s.id = d.supplier_id
                                    JOIN purchase_returns r ON r.id = d.purchase_return_id LEFT JOIN purchase_invoices pi ON pi.id = d.pinv_id" . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY d.id DESC LIMIT 500", $p);
            $missing = (int)erp_val($pdo, "SELECT COUNT(*) FROM purchase_returns r WHERE r.status = 'posted' AND NOT EXISTS (SELECT 1 FROM debit_notes d WHERE d.purchase_return_id = r.id)");
            erp_out(['status' => 'success', 'rows' => $rows, 'returns_without_note' => $missing]);

        case 'dn_get':
            $id = (int)erp_input('id');
            $d = erp_row($pdo, "SELECT d.*, s.supplier_name, s.company_name, s.gst_number, s.address, r.return_number, r.return_date, r.reason, r.settlement, r.credit_note_number, g.grn_number, pi.supplier_invoice_no, pi.pinv_number
                                FROM debit_notes d JOIN suppliers s ON s.id = d.supplier_id JOIN purchase_returns r ON r.id = d.purchase_return_id LEFT JOIN goods_receipts g ON g.id = r.grn_id
                                LEFT JOIN purchase_invoices pi ON pi.id = d.pinv_id WHERE d.id = ?", [$id]);
            if (!$d) erp_fail('Debit note not found.');
            $d['items'] = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT * FROM purchase_return_items WHERE return_id = ?", [$d['purchase_return_id']]));
            erp_out(['status' => 'success', 'record' => $d]);

        case 'dn_issue':
        case 'dn_issue_missing':
            $ids = $action === 'dn_issue' ? [(int)erp_input('return_id')]
                 : array_map('intval', array_column(erp_rows($pdo, "SELECT r.id FROM purchase_returns r WHERE r.status = 'posted' AND NOT EXISTS (SELECT 1 FROM debit_notes d WHERE d.purchase_return_id = r.id) ORDER BY r.id"), 'id'));
            $made = [];
            foreach ($ids as $rid) { $n = dn_issue_for_return($pdo, $rid); if ($n) $made[] = $n; }
            erp_out(['status' => 'success', 'issued' => $made, 'message' => $made ? 'Issued ' . implode(', ', $made) . '.' : 'Nothing to issue — every posted return already has a debit note.']);

        case 'dn_status':
            $id = (int)erp_input('id'); $to = erp_input('status');
            if (!in_array($to, ['adjusted', 'refunded', 'cancelled'], true)) erp_invalid('Invalid status.');
            $d = erp_row($pdo, "SELECT * FROM debit_notes WHERE id = ?", [$id]);
            if (!$d || $d['status'] === 'cancelled') erp_invalid('Debit note not found or cancelled.');
            $pdo->prepare("UPDATE debit_notes SET status = ?, notes = ? WHERE id = ?")->execute([$to, erp_input('notes') ?: $d['notes'], $id]);
            log_audit($pdo, 'update', 'debit_notes', $id, ['status' => $d['status']], ['status' => $to]);
            erp_out(['status' => 'success', 'message' => "{$d['dn_number']}: {$to}."]);

        // ============================================================ SUPPLIER 360
        case 'sup360_list':
            $rows = rep_supplier_summary($pdo);
            foreach ($rows as &$r) {
                $r['last_payment'] = erp_val($pdo, "SELECT MAX(payment_date) FROM purchase_payments WHERE supplier_id = ? AND status = 'completed'", [$r['id']]) ?: null;
                $r['credit_limit'] = erp_val($pdo, "SELECT credit_limit FROM supplier_profiles WHERE supplier_id = ?", [$r['id']]) ?: null;
            }
            unset($r);
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'sup360_get':
            $sid = (int)erp_input('id');
            $L = rep_supplier_ledger($pdo, $sid);
            $prof = erp_row($pdo, "SELECT * FROM supplier_profiles WHERE supplier_id = ?", [$sid]) ?: ['supplier_id' => $sid];
            $qty = erp_row($pdo, "SELECT COALESCE(SUM(gi.received_qty),0) received, COALESCE(SUM(gi.accepted_qty),0) accepted, COALESCE(SUM(gi.rejected_qty),0) rejected
                                  FROM goods_receipt_items gi JOIN goods_receipts g ON g.id = gi.grn_id WHERE g.supplier_id = ? AND g.status = 'posted'", [$sid]);
            $returned = (float)erp_val($pdo, "SELECT COALESCE(SUM(ri.quantity),0) FROM purchase_return_items ri JOIN purchase_returns r ON r.id = ri.return_id WHERE r.supplier_id = ? AND r.status = 'posted'", [$sid]);
            $lead = erp_row($pdo, "SELECT AVG(DATEDIFF(g1.first_grn, po.po_date)) avg_days, COUNT(*) n FROM purchase_orders po
                                   JOIN (SELECT po_id, MIN(received_date) first_grn FROM goods_receipts WHERE status = 'posted' AND po_id IS NOT NULL GROUP BY po_id) g1 ON g1.po_id = po.id WHERE po.supplier_id = ?", [$sid]);
            $freq = (int)erp_val($pdo, "SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = ? AND status NOT IN ('draft','cancelled') AND po_date >= ?", [$sid, date('Y-m-d', strtotime('-365 days'))]);
            $transport = erp_row($pdo, "SELECT COUNT(*) n, COALESCE(SUM(total_cost),0) cost FROM inbound_shipments WHERE supplier_id = ? AND status <> 'cancelled'", [$sid]);
            $payments = erp_rows($pdo, "SELECT pp.*, pi.supplier_invoice_no FROM purchase_payments pp LEFT JOIN purchase_invoices pi ON pi.id = pp.pinv_id WHERE pp.supplier_id = ? ORDER BY pp.payment_date DESC, pp.id DESC", [$sid]);
            $bills = erp_rows($pdo, "SELECT id, pinv_number, supplier_invoice_no, invoice_date, due_date, grand_total, status FROM purchase_invoices WHERE supplier_id = ? ORDER BY invoice_date DESC", [$sid]);
            $returns = erp_rows($pdo, "SELECT id, return_number, return_date, total_value, settlement, status FROM purchase_returns WHERE supplier_id = ? ORDER BY return_date DESC", [$sid]);
            $quotes = erp_rows($pdo, "SELECT q.id, q.quote_number, q.quote_date, q.grand_total, q.status, r.rfq_number FROM supplier_quotations q LEFT JOIN rfqs r ON r.id = q.rfq_id WHERE q.supplier_id = ? ORDER BY q.id DESC LIMIT 50", [$sid]);
            // price changes: first vs last rate per item
            $changes = [];
            foreach ($L['price_history'] as $h) {
                $k = $h['item_type'] . ':' . $h['item_id'];
                if (!isset($changes[$k])) $changes[$k] = ['item_name' => $h['item_name'], 'unit' => $h['unit'], 'last_rate' => (float)$h['rate'], 'last_date' => $h['received_date'], 'first_rate' => (float)$h['rate'], 'first_date' => $h['received_date'], 'purchases' => 0, 'qty' => 0, 'value' => 0];
                $changes[$k]['first_rate'] = (float)$h['rate']; $changes[$k]['first_date'] = $h['received_date'];
                $changes[$k]['purchases']++; $changes[$k]['qty'] += (float)$h['accepted_qty']; $changes[$k]['value'] += (float)$h['accepted_qty'] * (float)$h['rate'];
            }
            foreach ($changes as &$c) { $c['avg_rate'] = $c['qty'] > 0 ? erp_u($c['value'] / $c['qty']) : null; $c['change_pct'] = $c['first_rate'] > 0 ? round(($c['last_rate'] - $c['first_rate']) / $c['first_rate'] * 100, 2) : null; }
            unset($c);
            if (isset($prof['bank_account_last4']) && $prof['bank_account_last4']) $prof['bank_account_masked'] = 'XXXX' . $prof['bank_account_last4'];
            erp_out(['status' => 'success', 'supplier' => $L['supplier'], 'profile' => $prof, 'totals' => $L['totals'], 'ledger' => $L['entries'],
                     'kpis' => ['total_purchases' => $L['totals']['total_invoiced'], 'total_paid' => $L['totals']['total_paid'], 'outstanding' => $L['totals']['outstanding'],
                                'purchase_returns' => $L['totals']['returns_credit'], 'last_purchase' => erp_val($pdo, "SELECT MAX(received_date) FROM goods_receipts WHERE supplier_id = ? AND status = 'posted'", [$sid]) ?: null,
                                'last_payment' => erp_val($pdo, "SELECT MAX(payment_date) FROM purchase_payments WHERE supplier_id = ? AND status = 'completed'", [$sid]) ?: null,
                                'qty_received' => erp_q($qty['received']), 'qty_accepted' => erp_q($qty['accepted']), 'qty_rejected' => erp_q($qty['rejected']), 'qty_returned' => erp_q($returned),
                                'rejection_rate_pct' => (float)$qty['received'] > 0 ? round((float)$qty['rejected'] / (float)$qty['received'] * 100, 2) : null,
                                'avg_lead_days' => $lead && $lead['avg_days'] !== null ? round((float)$lead['avg_days'], 1) : null, 'orders_last_12m' => $freq,
                                'transport_shipments' => (int)$transport['n'], 'transport_cost' => erp_m($transport['cost']),
                                'credit_limit' => $prof['credit_limit'] ?? null, 'credit_available' => isset($prof['credit_limit']) && $prof['credit_limit'] !== null ? erp_m((float)$prof['credit_limit'] - $L['totals']['outstanding']) : null],
                     'purchase_orders' => $L['purchase_orders'], 'grns' => $L['grns'], 'bills' => $bills, 'payments' => $payments, 'returns' => $returns, 'quotations' => $quotes,
                     'price_changes' => array_values($changes), 'documents' => $L['documents']]);

        case 'sup_profile_save':
            $sid = (int)erp_input('supplier_id');
            if (!erp_supplier_name($pdo, $sid)) erp_invalid('Supplier not found.');
            $acct = preg_replace('/\D/', '', (string)erp_input('bank_account_number', ''));
            $old = erp_row($pdo, "SELECT * FROM supplier_profiles WHERE supplier_id = ?", [$sid]);
            $last4 = $acct !== '' ? substr($acct, -4) : ($old['bank_account_last4'] ?? null);
            $ifsc = strtoupper(trim((string)erp_input('bank_ifsc', '')));
            if ($ifsc !== '' && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc)) erp_invalid('IFSC looks wrong (format: ABCD0123456).');
            $pan = strtoupper(trim((string)erp_input('pan_number', '')));
            if ($pan !== '' && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) erp_invalid('PAN looks wrong (format: ABCDE1234F).');
            $limit = trim((string)erp_input('credit_limit', '')) === '' ? null : erp_m(erp_num(erp_input('credit_limit'), 'Credit limit'));
            $days = trim((string)erp_input('credit_days', '')) === '' ? null : (int)erp_input('credit_days');
            $open = erp_m(erp_num(erp_input('opening_balance', 0), 'Opening balance', true, true));
            $vals = [erp_input('contact_person') ?: null, erp_input('contact_phone') ?: null, erp_input('alt_phone') ?: null, $pan ?: null, erp_input('bank_account_name') ?: null, $last4,
                     $ifsc ?: null, erp_input('bank_name') ?: null, erp_input('upi_id') ?: null, $limit, $days, $open, erp_date(erp_input('opening_balance_date')), erp_input('notes') ?: null, erp_user()];
            $pdo->prepare("INSERT INTO supplier_profiles (contact_person, contact_phone, alt_phone, pan_number, bank_account_name, bank_account_last4, bank_ifsc, bank_name, upi_id, credit_limit, credit_days,
                             opening_balance, opening_balance_date, notes, updated_by, supplier_id) VALUES (" . implode(',', array_fill(0, 16, '?')) . ")
                           ON DUPLICATE KEY UPDATE contact_person=VALUES(contact_person), contact_phone=VALUES(contact_phone), alt_phone=VALUES(alt_phone), pan_number=VALUES(pan_number),
                             bank_account_name=VALUES(bank_account_name), bank_account_last4=VALUES(bank_account_last4), bank_ifsc=VALUES(bank_ifsc), bank_name=VALUES(bank_name), upi_id=VALUES(upi_id),
                             credit_limit=VALUES(credit_limit), credit_days=VALUES(credit_days), opening_balance=VALUES(opening_balance), opening_balance_date=VALUES(opening_balance_date),
                             notes=VALUES(notes), updated_by=VALUES(updated_by)")->execute(array_merge($vals, [$sid]));
            $safeOld = $old; if ($safeOld) unset($safeOld['bank_account_last4']);
            log_audit($pdo, 'update', 'supplier_profiles', $sid, $safeOld, ['credit_limit' => $limit, 'credit_days' => $days, 'opening_balance' => $open, 'bank_changed' => $acct !== '']);
            erp_out(['status' => 'success', 'message' => 'Supplier profile saved.' . ($acct !== '' ? ' Only the last 4 digits of the bank account are stored.' : '')]);

        default:
            erp_fail('Unknown action.');
    }
} catch (Throwable $e) {
    erp_db_error($e, $action ?: 'procurement');
}

