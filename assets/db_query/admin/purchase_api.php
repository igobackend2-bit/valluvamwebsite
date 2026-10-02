<?php
// ============================================================================
// Purchase lifecycle API (added 30 Sep 2026)
//   Purchase Requests → Purchase Orders → Goods Receipts (GRN) → Purchase
//   Invoices (landed cost) → Supplier Payments → Purchase Returns
// Single router: ?action=... (GET for reads, POST for writes).
// Stock changes ONLY on GRN post / purchase return post, through the existing
// Stock In / Stock Out tables. All money math goes through erp_line().
// ============================================================================
require_once __DIR__ . '/erp_helper.php';
require_once __DIR__ . '/erp_ext.php';   // complete-ERP hooks: approvals, QC, debit notes (1 Oct 2026)

$action = (string) erp_input('action', '');
$isWrite = $_SERVER['REQUEST_METHOD'] === 'POST';

$perms = [
    'pr_save' => 'purchase.create', 'pr_submit' => 'purchase.create', 'pr_cancel' => 'purchase.create',
    'pr_add_location' => 'purchase.create',   // FIX (2 Oct 2026): "Other" location typed on a purchase request
    'pr_manager_approve' => 'purchase.manager_approve', 'pr_manager_reject' => 'purchase.manager_approve',
    'pr_backend_approve' => 'purchase.backend_approve', 'pr_backend_reject' => 'purchase.backend_approve', 'pr_to_po' => 'purchase.backend_approve',
    'po_save' => 'purchase.backend_approve', 'po_submit' => 'purchase.backend_approve', 'po_approve' => 'purchase.backend_approve',
    'po_cancel' => 'purchase.approve', 'po_close' => 'purchase.approve',
    'grn_save' => 'grn.create', 'grn_post' => 'grn.create', 'grn_cancel' => 'grn.create',
    'pinv_save' => 'purchase_invoice.create', 'pinv_post' => 'purchase_invoice.create', 'pinv_cancel' => 'purchase_invoice.create',
    'pay_save' => 'purchase_payment.create', 'pay_cancel' => 'purchase_payment.create',
    'ret_post' => 'purchase_return.create',
];
erp_guard($pdo, $perms[$action] ?? 'purchase.view');
if (isset($perms[$action]) && !$isWrite) erp_fail('Invalid request method.');

try {
    switch ($action) {
        // ================================================================ PURCHASE REQUESTS
        case 'pr_permissions':
            erp_out(['status' => 'success', 'manager' => erp_can($pdo, 'purchase.manager_approve'), 'backend' => erp_can($pdo, 'purchase.backend_approve')]);

        case 'pr_list':
            $w = []; $p = [];
            if ($s = erp_input('status')) { $w[] = 'pr.status = ?'; $p[] = $s; }
            if ($q = trim((string)erp_input('q', ''))) { $w[] = '(pr.pr_number LIKE ? OR pr.requested_by LIKE ? OR pr.notes LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%"); }
            if (!erp_can($pdo, 'purchase.manager_approve') && !erp_can($pdo, 'purchase.backend_approve')) { $w[] = 'pr.created_by = ?'; $p[] = erp_user(); }
            $rows = erp_rows($pdo, "SELECT pr.*, w.name AS warehouse_name,
                                           (SELECT COUNT(*) FROM purchase_request_items i WHERE i.pr_id = pr.id) AS item_count
                                    FROM purchase_requests pr LEFT JOIN warehouses w ON w.id = pr.warehouse_id" .
                                   ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY pr.id DESC LIMIT 500", $p);
            // FIX (2 Oct 2026): exact live stage of each request (manager → admin → quotation → PO → payment → loaded → unloaded → QC → inventory)
            if (is_file(__DIR__ . '/pr_stage_lib.php')) { require_once __DIR__ . '/pr_stage_lib.php'; foreach ($rows as &$r) $r['stage'] = pr_stage($pdo, $r); unset($r); }
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'pr_get':
            $id = (int)erp_input('id');
            $pr = erp_row($pdo, "SELECT pr.*, w.name AS warehouse_name FROM purchase_requests pr LEFT JOIN warehouses w ON w.id = pr.warehouse_id WHERE pr.id = ?", [$id]);
            if (!$pr) erp_fail('Purchase request not found.');
            if (!erp_can($pdo, 'purchase.manager_approve') && !erp_can($pdo, 'purchase.backend_approve') && $pr['created_by'] !== erp_user()) erp_fail('You can view only your own purchase requests.', 403);
            $pr['items'] = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT * FROM purchase_request_items WHERE pr_id = ? ORDER BY id", [$id]));
            if (is_file(__DIR__ . '/pf_lib.php')) { require_once __DIR__ . '/pf_lib.php'; $pr['items'] = pf_attach_units($pdo, $pr['items']); }   // typed unit, e.g. 50 kg (1 Oct 2026)
            $pr['purchase_orders'] = erp_rows($pdo, "SELECT id, po_number, status, grand_total FROM purchase_orders WHERE pr_id = ?", [$id]);
            if (is_file(__DIR__ . '/pr_stage_lib.php')) { require_once __DIR__ . '/pr_stage_lib.php'; $pr['stage'] = pr_stage($pdo, $pr); }   // FIX (2 Oct 2026)
            erp_out(['status' => 'success', 'record' => $pr]);

        // FIX (2 Oct 2026): locations for the request form (active ones) and "Other" — a typed new location is saved as a warehouse
        case 'pr_warehouses':
            erp_out(['status' => 'success', 'warehouses' => erp_rows($pdo, "SELECT id, name, code, location, status FROM warehouses WHERE status = 'active' OR id = ? ORDER BY name", [(int)erp_input('keep', 0)])]);

        case 'pr_add_location':
            $name = trim(preg_replace('/\s+/', ' ', (string)erp_input('name', '')));
            if (mb_strlen($name) < 2) erp_invalid('Type the location name.');
            $name = mb_substr($name, 0, 100);
            $loc = mb_substr(trim((string)erp_input('location', '')), 0, 150);
            $ex = erp_row($pdo, "SELECT id, name, status FROM warehouses WHERE LOWER(TRIM(name)) = LOWER(?) LIMIT 1", [$name]);
            if ($ex) {
                if ($ex['status'] !== 'active') erp_invalid("Location \"{$ex['name']}\" already exists but is inactive. Ask Admin to activate it in Warehouses.");
                erp_out(['status' => 'success', 'id' => (int)$ex['id'], 'name' => $ex['name'], 'message' => "Location \"{$ex['name']}\" already exists — selected it."]);
            }
            $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 6)) ?: 'LOC';
            $code = $base; $n = 1;
            while (erp_val($pdo, "SELECT id FROM warehouses WHERE code = ?", [$code])) $code = $base . '-' . (++$n);
            $pdo->prepare("INSERT INTO warehouses (name, code, location, status) VALUES (?, ?, ?, 'active')")->execute([$name, $code, $loc ?: null]);
            $wid = (int)$pdo->lastInsertId();
            log_audit($pdo, 'create', 'warehouses', $wid, null, ['name' => $name, 'code' => $code, 'location' => $loc, 'from' => 'purchase request (Other location)']);
            erp_out(['status' => 'success', 'id' => $wid, 'name' => $name, 'message' => "Location \"{$name}\" saved."]);

        case 'pr_save':
        case 'pr_submit':
            $id = (int)erp_input('id', 0);
            $items = erp_json_input('items');
            if (!$items && !in_array(trim((string)($_POST['items'] ?? '')), ['', '[]'], true)) erp_invalid('The item list could not be read. Please refresh the page (Ctrl+F5) and try again.');
            $clean = [];
            $units = [];   // typed quantity + unit per line (1 Oct 2026)
            if (is_file(__DIR__ . '/pf_lib.php')) require_once __DIR__ . '/pf_lib.php';
            foreach ($items as $it) {
                $type = erp_item_type($it['item_type'] ?? 'product');
                $iid = (int)($it['item_id'] ?? 0);
                $qty = erp_q(erp_num($it['quantity'] ?? 0, 'Quantity'));
                if (!$iid || $qty <= 0) continue;
                $item = erp_item($pdo, $type, $iid);
                if (!$item) erp_invalid('An item in the list no longer exists.');
                if (trim((string)($it['input_unit'] ?? '')) !== '' && function_exists('pf_convert')) {   // g / kg / ml / L → packs
                    $conv = pf_convert($type, $item, erp_num($it['input_qty'] ?? 0, 'Quantity'), (string)$it['input_unit']);
                    $qty = erp_q($conv['qty']);
                    $units[count($clean)] = $conv;
                }
                $clean[] = [$type, $iid, $qty, $item['unit'], ($it['estimated_rate'] ?? '') === '' ? null : erp_u(erp_num($it['estimated_rate'], 'Estimated rate')), mb_substr((string)($it['notes'] ?? ''), 0, 255)];
            }
            if (!$clean) erp_invalid('Add at least one item with a quantity.');
            $date = erp_date(erp_input('request_date'), true);
            $status = $action === 'pr_submit' ? 'submitted' : 'draft';
            $pdo->beginTransaction();
            if ($id) {
                $old = erp_row($pdo, "SELECT * FROM purchase_requests WHERE id = ? FOR UPDATE", [$id]);
                if (!$old) erp_invalid('Purchase request not found.');
                if (!erp_can($pdo, 'purchase.manager_approve') && !erp_can($pdo, 'purchase.backend_approve') && $old['created_by'] !== erp_user()) erp_invalid('You can edit only your own purchase requests.');
                if (!in_array($old['status'], ['draft', 'rejected', 'manager_rejected', 'backend_rejected'], true)) erp_invalid('Only draft or rejected requests can be edited.');
                $pdo->prepare("UPDATE purchase_requests SET request_date=?, required_by=?, warehouse_id=?, requested_by=?, notes=?, status=? WHERE id=?")
                    ->execute([$date, erp_date(erp_input('required_by')), (int)erp_input('warehouse_id', 1) ?: 1, mb_substr(trim((string)erp_input('requested_by', '')), 0, 100) ?: erp_user(), erp_input('notes') ?: null, $status, $id]);
                $pdo->prepare("DELETE FROM purchase_request_items WHERE pr_id = ?")->execute([$id]);
            } else {
                $num = next_document_number($pdo, 'purchase_request', 'PR');
                $pdo->prepare("INSERT INTO purchase_requests (pr_number, request_date, required_by, warehouse_id, requested_by, notes, status, created_by) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$num, $date, erp_date(erp_input('required_by')), (int)erp_input('warehouse_id', 1) ?: 1, mb_substr(trim((string)erp_input('requested_by', '')), 0, 100) ?: erp_user(), erp_input('notes') ?: null, $status, erp_user()]);
                $id = (int)$pdo->lastInsertId();
            }
            $ins = $pdo->prepare("INSERT INTO purchase_request_items (pr_id, item_type, item_id, quantity, unit, estimated_rate, notes) VALUES (?,?,?,?,?,?,?)");
            $saveUnits = function_exists('pf_units_installed') && pf_units_installed($pdo);
            if ($saveUnits) $pdo->prepare("DELETE FROM pr_item_units WHERE pr_id = ?")->execute([$id]);
            foreach ($clean as $k => $c) {
                $ins->execute(array_merge([$id], $c));
                if ($saveUnits && isset($units[$k]) && $units[$k]['input_unit'] !== 'pcs')
                    $pdo->prepare("INSERT INTO pr_item_units (pr_id, pr_item_id, input_qty, input_unit, pack_size, pack_unit, packs) VALUES (?,?,?,?,?,?,?)")
                        ->execute([$id, (int)$pdo->lastInsertId(), $units[$k]['input_qty'], $units[$k]['input_unit'], $units[$k]['pack_size'], $units[$k]['pack_unit'], $units[$k]['qty']]);
            }
            $pdo->commit();
            log_audit($pdo, isset($old) ? 'update' : 'create', 'purchase_requests', $id, $old ?? null, ['status' => $status, 'items' => $clean]);
            if ($status === 'submitted' && erpx_installed($pdo)) {   // approvals inbox (1 Oct 2026)
                $prNo = erp_val($pdo, "SELECT pr_number FROM purchase_requests WHERE id = ?", [$id]);
                apr_open($pdo, 'purchase_request', (string)$id, $id, $prNo, "Purchase request {$prNo} (" . count($clean) . ' item(s)) — manager approval', null, 'purchase_api.php', ['action' => 'pr_manager_approve', 'id' => $id], ['action' => 'pr_manager_reject', 'id' => $id]);
            }
            erp_out(['status' => 'success', 'id' => $id, 'message' => $status === 'submitted' ? 'Request submitted for approval.' : 'Request saved.']);

        case 'pr_manager_approve':
        case 'pr_manager_reject':
        case 'pr_backend_approve':
        case 'pr_backend_reject':
        case 'pr_cancel':
            $id = (int)erp_input('id');
            $pr = erp_row($pdo, "SELECT * FROM purchase_requests WHERE id = ?", [$id]);
            if (!$pr) erp_invalid('Purchase request not found.');
            if ($action === 'pr_cancel' && !erp_can($pdo, 'purchase.manager_approve') && !erp_can($pdo, 'purchase.backend_approve') && $pr['created_by'] !== erp_user()) erp_invalid('You can cancel only your own purchase requests.');
            $to = ['pr_manager_approve' => 'manager_approved', 'pr_manager_reject' => 'manager_rejected', 'pr_backend_approve' => 'approved', 'pr_backend_reject' => 'backend_rejected', 'pr_cancel' => 'cancelled'][$action];
            $allowed = ['pr_manager_approve' => ['submitted'], 'pr_manager_reject' => ['submitted'], 'pr_backend_approve' => ['manager_approved'], 'pr_backend_reject' => ['manager_approved'], 'pr_cancel' => ['draft', 'submitted', 'manager_approved', 'approved', 'manager_rejected', 'backend_rejected']][$action];
            if (!in_array($pr['status'], $allowed, true)) erp_invalid("A {$pr['status']} request cannot be {$to}.");
            if ($action === 'pr_manager_approve') $pdo->prepare('UPDATE purchase_requests SET status=?, manager_approved_by=?, manager_approved_at=NOW() WHERE id=?')->execute([$to, erp_user(), $id]);
            elseif ($action === 'pr_backend_approve') $pdo->prepare('UPDATE purchase_requests SET status=?, backend_approved_by=?, backend_approved_at=NOW(), approved_by=?, approved_at=NOW() WHERE id=?')->execute([$to, erp_user(), erp_user(), $id]);
            else $pdo->prepare('UPDATE purchase_requests SET status=? WHERE id=?')->execute([$to, $id]);
            log_audit($pdo, str_contains($action, 'approve') ? 'approve' : 'update', 'purchase_requests', $id, ['status' => $pr['status']], ['status' => $to, 'note' => erp_input('note')]);
            if (erpx_installed($pdo)) {   // approvals inbox mirrors the Manager -> Backend chain (1 Oct 2026)
                $rem = erp_input('_approval_remarks') ?: erp_input('note') ?: null;
                if ($action === 'pr_manager_approve') {
                    apr_close($pdo, 'purchase_request', (string)$id, 'approved', $rem);
                    apr_open($pdo, 'purchase_request_final', (string)$id, $id, $pr['pr_number'], "Purchase request {$pr['pr_number']} — backend / final approval", null, 'purchase_api.php', ['action' => 'pr_backend_approve', 'id' => $id], ['action' => 'pr_backend_reject', 'id' => $id]);
                } elseif ($action === 'pr_manager_reject') apr_close($pdo, 'purchase_request', (string)$id, 'rejected', $rem);
                elseif ($action === 'pr_backend_approve') apr_close($pdo, 'purchase_request_final', (string)$id, 'approved', $rem);
                elseif ($action === 'pr_backend_reject') apr_close($pdo, 'purchase_request_final', (string)$id, 'rejected', $rem);
                else { apr_close($pdo, 'purchase_request', (string)$id, 'cancelled', $rem); apr_close($pdo, 'purchase_request_final', (string)$id, 'cancelled', $rem); }
            }
            erp_out(['status' => 'success', 'message' => "Request {$to}."]);

        case 'pr_to_po':
            $id = (int)erp_input('id');
            $supplierId = (int)erp_input('supplier_id');
            $pr = erp_row($pdo, "SELECT * FROM purchase_requests WHERE id = ?", [$id]);
            if (!$pr) erp_invalid('Purchase request not found.');
            if ($pr['status'] !== 'approved') erp_invalid('Only approved requests can be converted to a purchase order.');
            if (!erp_supplier_name($pdo, $supplierId)) erp_invalid('Choose a supplier.');
            $items = erp_rows($pdo, "SELECT * FROM purchase_request_items WHERE pr_id = ?", [$id]);
            $pdo->beginTransaction();
            $num = next_document_number($pdo, 'purchase_order', 'PO');
            $pdo->prepare("INSERT INTO purchase_orders (po_number, supplier_id, pr_id, po_date, expected_delivery_date, warehouse_id, buyer, status, notes, created_by)
                           VALUES (?,?,?, CURDATE(), ?,?,?, 'draft', ?, ?)")
                ->execute([$num, $supplierId, $id, $pr['required_by'], $pr['warehouse_id'], erp_user(), 'From ' . $pr['pr_number'], erp_user()]);
            $poId = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO purchase_order_items (po_id, item_type, item_id, sku, quantity, unit, rate, line_total) VALUES (?,?,?,?,?,?,?,?)");
            $sub = 0;
            foreach ($items as $it) {
                $item = erp_item($pdo, $it['item_type'], (int)$it['item_id']);
                $l = erp_line((float)$it['quantity'], (float)$it['estimated_rate'], 0, 0);
                $sub += $l['total'];
                $ins->execute([$poId, $it['item_type'], $it['item_id'], $item['sku'] ?? null, $it['quantity'], $it['unit'], erp_u($it['estimated_rate']), $l['total']]);
            }
            $pdo->prepare("UPDATE purchase_orders SET subtotal = ?, grand_total = ? WHERE id = ?")->execute([erp_m($sub), erp_m($sub), $poId]);
            $pdo->prepare("UPDATE purchase_requests SET status = 'converted' WHERE id = ?")->execute([$id]);
            $pdo->commit();
            log_audit($pdo, 'create', 'purchase_orders', $poId, null, ['from_pr' => $pr['pr_number'], 'po_number' => $num]);
            erp_out(['status' => 'success', 'id' => $poId, 'po_number' => $num, 'message' => "Draft {$num} created — review rates and submit it."]);

        // ================================================================ PURCHASE ORDERS
        case 'po_list':
            $w = []; $p = [];
            if ($s = erp_input('status')) {
                if ($s === 'open') $w[] = "po.status IN ('approved','partially_received')";
                else { $w[] = 'po.status = ?'; $p[] = $s; }
            }
            if ($sid = (int)erp_input('supplier_id', 0)) { $w[] = 'po.supplier_id = ?'; $p[] = $sid; }
            if ($d = erp_date(erp_input('date_from'))) { $w[] = 'po.po_date >= ?'; $p[] = $d; }
            if ($d = erp_date(erp_input('date_to'))) { $w[] = 'po.po_date <= ?'; $p[] = $d; }
            if ($q = trim((string)erp_input('q', ''))) { $w[] = '(po.po_number LIKE ? OR s.supplier_name LIKE ?)'; array_push($p, "%$q%", "%$q%"); }
            $rows = erp_rows($pdo, "SELECT po.*, s.supplier_name, w.name AS warehouse_name,
                                           (SELECT COALESCE(SUM(quantity),0) FROM purchase_order_items i WHERE i.po_id = po.id) AS ordered_qty,
                                           (SELECT COALESCE(SUM(received_qty),0) FROM purchase_order_items i WHERE i.po_id = po.id) AS received_qty
                                    FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id LEFT JOIN warehouses w ON w.id = po.warehouse_id" .
                                   ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY po.id DESC LIMIT 500", $p);
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'po_get':
            $id = (int)erp_input('id');
            $po = erp_row($pdo, "SELECT po.*, s.supplier_name, s.mobile AS supplier_mobile, s.gst_number AS supplier_gst, w.name AS warehouse_name
                                 FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id LEFT JOIN warehouses w ON w.id = po.warehouse_id WHERE po.id = ?", [$id]);
            if (!$po) erp_fail('Purchase order not found.');
            $po['items'] = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT * FROM purchase_order_items WHERE po_id = ? ORDER BY id", [$id]));
            $po['grns'] = erp_rows($pdo, "SELECT id, grn_number, received_date, status FROM goods_receipts WHERE po_id = ? ORDER BY id", [$id]);
            $po['invoices'] = erp_rows($pdo, "SELECT id, pinv_number, supplier_invoice_no, invoice_date, grand_total, status FROM purchase_invoices WHERE po_id = ? ORDER BY id", [$id]);
            $po['returns'] = erp_rows($pdo, "SELECT id, return_number, return_date, total_value, status FROM purchase_returns WHERE po_id = ? ORDER BY id", [$id]);
            $po['pr_number'] = $po['pr_id'] ? erp_val($pdo, "SELECT pr_number FROM purchase_requests WHERE id = ?", [$po['pr_id']]) : null;
            erp_out(['status' => 'success', 'record' => $po]);

        case 'po_save':
        case 'po_submit':
            $id = (int)erp_input('id', 0);
            $supplierId = (int)erp_input('supplier_id');
            if (!erp_supplier_name($pdo, $supplierId)) erp_invalid('Choose a supplier.');
            $date = erp_date(erp_input('po_date'), true);
            $expected = erp_date(erp_input('expected_delivery_date'));
            if ($expected && $expected < $date) erp_invalid('Expected delivery date cannot be before the PO date.');
            $lines = []; $sub = 0; $disc = 0; $tax = 0; $net = 0;
            foreach (erp_json_input('items') as $it) {
                $type = erp_item_type($it['item_type'] ?? 'product');
                $iid = (int)($it['item_id'] ?? 0);
                if (!$iid) continue;
                $item = erp_item($pdo, $type, $iid);
                if (!$item) erp_invalid('An item in the list no longer exists.');
                $qty = erp_q(erp_num($it['quantity'] ?? 0, 'Quantity', false));
                if ($type === 'product' && floor($qty) != $qty) erp_invalid("{$item['name']}: product packs must be whole numbers.");
                $rate = erp_u(erp_num($it['rate'] ?? 0, 'Rate'));
                $l = erp_line($qty, $rate, erp_num($it['discount_amount'] ?? 0, 'Discount'), erp_num($it['tax_percent'] ?? 0, 'Tax %'));
                $sub += $l['gross']; $disc += $l['discount']; $tax += $l['tax']; $net += $l['total'];
                $lines[] = [$type, $iid, $item['sku'], $qty, $type === 'product' ? 'pcs' : $item['unit'], $rate, $l['discount'], erp_num($it['tax_percent'] ?? 0, 'Tax %'), $l['tax'], $l['total']];
            }
            if (!$lines) erp_invalid('Add at least one item.');
            $other = erp_m(erp_num(erp_input('other_charges', 0), 'Other charges'));
            $grand = erp_m($net + $other);
            $status = $action === 'po_submit' ? 'pending_approval' : 'draft';
            $pdo->beginTransaction();
            $old = null;
            if ($id) {
                $old = erp_row($pdo, "SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE", [$id]);
                if (!$old) erp_invalid('Purchase order not found.');
                if (!in_array($old['status'], ['draft', 'pending_approval'], true)) erp_invalid('Only draft or pending purchase orders can be edited.');
                $pdo->prepare("UPDATE purchase_orders SET supplier_id=?, po_date=?, expected_delivery_date=?, warehouse_id=?, buyer=?, status=?,
                               subtotal=?, discount_total=?, tax_total=?, other_charges=?, grand_total=?, notes=? WHERE id=?")
                    ->execute([$supplierId, $date, $expected, (int)erp_input('warehouse_id', 1) ?: 1, erp_input('buyer') ?: erp_user(), $status,
                               erp_m($sub), erp_m($disc), erp_m($tax), $other, $grand, erp_input('notes') ?: null, $id]);
                $pdo->prepare("DELETE FROM purchase_order_items WHERE po_id = ?")->execute([$id]);
                $num = $old['po_number'];
            } else {
                $num = next_document_number($pdo, 'purchase_order', 'PO');
                $pdo->prepare("INSERT INTO purchase_orders (po_number, supplier_id, po_date, expected_delivery_date, warehouse_id, buyer, status,
                               subtotal, discount_total, tax_total, other_charges, grand_total, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$num, $supplierId, $date, $expected, (int)erp_input('warehouse_id', 1) ?: 1, erp_input('buyer') ?: erp_user(), $status,
                               erp_m($sub), erp_m($disc), erp_m($tax), $other, $grand, erp_input('notes') ?: null, erp_user()]);
                $id = (int)$pdo->lastInsertId();
            }
            $ins = $pdo->prepare("INSERT INTO purchase_order_items (po_id, item_type, item_id, sku, quantity, unit, rate, discount_amount, tax_percent, tax_amount, line_total)
                                  VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            foreach ($lines as $l) $ins->execute(array_merge([$id], $l));
            $pdo->commit();
            log_audit($pdo, $old ? 'update' : 'create', 'purchase_orders', $id, $old, ['po_number' => $num, 'status' => $status, 'grand_total' => $grand, 'items' => $lines]);
            if ($status === 'pending_approval' && erpx_installed($pdo))   // approvals inbox (1 Oct 2026)
                apr_open($pdo, 'purchase_order', (string)$id, $id, $num, "Purchase order {$num} — " . erp_supplier_name($pdo, $supplierId), $grand, 'purchase_api.php',
                         ['action' => 'po_approve', 'id' => $id], ['action' => 'po_reject', 'id' => $id, '_endpoint' => 'procurement_api.php']);
            erp_out(['status' => 'success', 'id' => $id, 'po_number' => $num, 'message' => $status === 'pending_approval' ? "{$num} sent for approval." : "{$num} saved as draft."]);

        case 'po_approve':
            $id = (int)erp_input('id');
            $po = erp_row($pdo, "SELECT * FROM purchase_orders WHERE id = ?", [$id]);
            if (!$po) erp_invalid('Purchase order not found.');
            if (!in_array($po['status'], ['draft', 'pending_approval'], true)) erp_invalid("A {$po['status']} purchase order cannot be approved.");
            // Big purchase orders also need the CEO (1 Oct 2026): Admin's approval is recorded and the PO goes to the CEO.
            if (erpx_installed($pdo) && !erp_can($pdo, 'po.ceo_approve') && apr_policy($pdo, 'po_ceo')) {
                $ceoLimit = (float)erp_setting($pdo, 'ceo_po_limit', 0);
                if ($ceoLimit > 0 && (float)$po['grand_total'] + 0.0001 >= $ceoLimit) {
                    if (erp_val($pdo, "SELECT id FROM approval_requests WHERE module = 'po_ceo' AND request_key = ? AND status IN ('submitted','under_review')", [(string)$id]))
                        erp_invalid("{$po['po_number']} is already waiting for the CEO.");
                    apr_close($pdo, 'purchase_order', (string)$id, 'approved', erp_input('_approval_remarks') ?: 'Approved by ' . erp_user() . ' — sent to CEO');
                    $ceoNum = apr_open($pdo, 'po_ceo', (string)$id, $id, $po['po_number'], "Purchase order {$po['po_number']} — " . erp_supplier_name($pdo, (int)$po['supplier_id']) . ' (approved by ' . erp_user() . ')',
                                       (float)$po['grand_total'], 'purchase_api.php', ['action' => 'po_approve', 'id' => $id], ['action' => 'po_reject', 'id' => $id, '_endpoint' => 'procurement_api.php']);
                    log_audit($pdo, 'approve', 'purchase_orders', $id, ['status' => $po['status']], ['admin_approved_by' => erp_user(), 'sent_to_ceo' => $ceoNum]);
                    erp_out(['status' => 'success', 'ceo' => true, 'message' => "{$po['po_number']} approved by you and sent to the CEO ({$ceoNum}) — it is ₹" . number_format($ceoLimit, 0) . ' or more.']);
                }
            }
            $pdo->prepare("UPDATE purchase_orders SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?")->execute([erp_user(), $id]);
            log_audit($pdo, 'approve', 'purchase_orders', $id, ['status' => $po['status']], ['status' => 'approved']);
            if (erpx_installed($pdo)) { apr_close($pdo, 'purchase_order', (string)$id, 'approved', erp_input('_approval_remarks') ?: null); apr_close($pdo, 'po_ceo', (string)$id, 'approved', erp_input('_approval_remarks') ?: null); }
            erp_out(['status' => 'success', 'message' => "{$po['po_number']} approved. Goods can now be received against it."]);

        case 'po_cancel':
        case 'po_close':
            $id = (int)erp_input('id');
            $po = erp_row($pdo, "SELECT * FROM purchase_orders WHERE id = ?", [$id]);
            if (!$po) erp_invalid('Purchase order not found.');
            if ($action === 'po_cancel') {
                $posted = (int)erp_val($pdo, "SELECT COUNT(*) FROM goods_receipts WHERE po_id = ? AND status = 'posted'", [$id]);
                if ($posted) erp_invalid('Goods have already been received against this PO. Close it instead of cancelling.');
                if (in_array($po['status'], ['cancelled', 'closed'], true)) erp_invalid("This purchase order is already {$po['status']}.");
                $to = 'cancelled';
            } else {
                if (!in_array($po['status'], ['approved', 'partially_received', 'fully_received'], true)) erp_invalid('Only approved or received purchase orders can be closed.');
                $to = 'closed';
            }
            $pdo->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?")->execute([$to, $id]);
            log_audit($pdo, 'update', 'purchase_orders', $id, ['status' => $po['status']], ['status' => $to, 'reason' => erp_input('reason')]);
            if (erpx_installed($pdo)) apr_close($pdo, 'purchase_order', (string)$id, 'cancelled', erp_input('reason') ?: null);
            erp_out(['status' => 'success', 'message' => "{$po['po_number']} {$to}."]);

        // ================================================================ GOODS RECEIPTS
        case 'grn_list':
            $w = []; $p = [];
            if ($s = erp_input('status')) { $w[] = 'g.status = ?'; $p[] = $s; }
            if ($sid = (int)erp_input('supplier_id', 0)) { $w[] = 'g.supplier_id = ?'; $p[] = $sid; }
            if ($d = erp_date(erp_input('date_from'))) { $w[] = 'g.received_date >= ?'; $p[] = $d; }
            if ($d = erp_date(erp_input('date_to'))) { $w[] = 'g.received_date <= ?'; $p[] = $d; }
            if ($q = trim((string)erp_input('q', ''))) { $w[] = '(g.grn_number LIKE ? OR s.supplier_name LIKE ? OR po.po_number LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%"); }
            $rows = erp_rows($pdo, "SELECT g.*, s.supplier_name, po.po_number, w.name AS warehouse_name, si.stock_in_number,
                                           (SELECT COALESCE(SUM(accepted_qty),0) FROM goods_receipt_items i WHERE i.grn_id = g.id) AS accepted_qty,
                                           (SELECT COALESCE(SUM(rejected_qty),0) FROM goods_receipt_items i WHERE i.grn_id = g.id) AS rejected_qty,
                                           (SELECT COALESCE(SUM(accepted_qty * rate),0) FROM goods_receipt_items i WHERE i.grn_id = g.id) AS accepted_value
                                    FROM goods_receipts g JOIN suppliers s ON s.id = g.supplier_id
                                    LEFT JOIN purchase_orders po ON po.id = g.po_id LEFT JOIN warehouses w ON w.id = g.warehouse_id
                                    LEFT JOIN stock_ins si ON si.id = g.stock_in_id" .
                                   ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY g.id DESC LIMIT 500", $p);
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'grn_prefill':
            $poId = (int)erp_input('po_id');
            $po = erp_row($pdo, "SELECT po.*, s.supplier_name FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id WHERE po.id = ?", [$poId]);
            if (!$po) erp_fail('Purchase order not found.');
            if (!in_array($po['status'], ['approved', 'partially_received'], true)) erp_fail("Goods can only be received against an approved PO (this one is {$po['status']}).");
            $items = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT * FROM purchase_order_items WHERE po_id = ? ORDER BY id", [$poId]));
            foreach ($items as &$it) {
                $it['pending_qty'] = erp_q(max(0, $it['quantity'] - $it['received_qty']));
                $it['net_rate'] = $it['quantity'] > 0 ? erp_u(($it['quantity'] * $it['rate'] - $it['discount_amount']) / $it['quantity']) : erp_u($it['rate']);
            }
            unset($it);
            erp_out(['status' => 'success', 'po' => $po, 'items' => $items]);

        case 'grn_get':
            $id = (int)erp_input('id');
            $g = erp_row($pdo, "SELECT g.*, s.supplier_name, po.po_number, w.name AS warehouse_name, si.stock_in_number
                                FROM goods_receipts g JOIN suppliers s ON s.id = g.supplier_id LEFT JOIN purchase_orders po ON po.id = g.po_id
                                LEFT JOIN warehouses w ON w.id = g.warehouse_id LEFT JOIN stock_ins si ON si.id = g.stock_in_id WHERE g.id = ?", [$id]);
            if (!$g) erp_fail('Goods receipt not found.');
            $g['items'] = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT * FROM goods_receipt_items WHERE grn_id = ? ORDER BY id", [$id]));
            $g['invoices'] = erp_rows($pdo, "SELECT id, pinv_number, supplier_invoice_no, grand_total, status FROM purchase_invoices WHERE grn_id = ?", [$id]);
            $g['returns'] = erp_rows($pdo, "SELECT id, return_number, total_value, status FROM purchase_returns WHERE grn_id = ?", [$id]);
            $g['batches'] = erp_rows($pdo, "SELECT * FROM inventory_batches WHERE source_type = 'grn' AND source_id = ?", [$id]);
            erp_out(['status' => 'success', 'record' => $g]);

        case 'grn_save':
        case 'grn_post':
            $id = (int)erp_input('id', 0);
            qc_grn_hook($pdo, $action, $id);   // quality check: pending blocks, completed result is used (1 Oct 2026)
            $poId = (int)erp_input('po_id', 0) ?: null;
            $po = $poId ? erp_row($pdo, "SELECT * FROM purchase_orders WHERE id = ?", [$poId]) : null;
            if ($poId && !$po) erp_invalid('Purchase order not found.');
            $supplierId = $po ? (int)$po['supplier_id'] : (int)erp_input('supplier_id');
            if (!erp_supplier_name($pdo, $supplierId)) erp_invalid('Choose a supplier.');
            $date = erp_date(erp_input('received_date'), true);
            if ($date > date('Y-m-d')) erp_invalid('Received date cannot be in the future.');
            $warehouseId = (int)erp_input('warehouse_id', $po['warehouse_id'] ?? 1) ?: 1;
            $allowOver = ((string)erp_val($pdo, "SELECT setting_value FROM admin_settings WHERE setting_key = 'erp_allow_over_receipt'")) === '1';

            $poItems = [];
            if ($po) foreach (erp_rows($pdo, "SELECT * FROM purchase_order_items WHERE po_id = ?", [$poId]) as $pi) $poItems[(int)$pi['id']] = $pi;
            $lines = [];
            foreach (erp_json_input('items') as $it) {
                $poItemId = (int)($it['po_item_id'] ?? 0) ?: null;
                if ($po && $poItemId && !isset($poItems[$poItemId])) erp_invalid('A line does not belong to this purchase order.');
                $pi = $poItemId ? $poItems[$poItemId] : null;
                $type = $pi ? $pi['item_type'] : erp_item_type($it['item_type'] ?? 'product');
                $iid = $pi ? (int)$pi['item_id'] : (int)($it['item_id'] ?? 0);
                if (!$iid) continue;
                $item = erp_item($pdo, $type, $iid);
                if (!$item) erp_invalid('An item in the list no longer exists.');
                $received = erp_q(erp_num($it['received_qty'] ?? 0, 'Received quantity'));
                $rejected = erp_q(erp_num($it['rejected_qty'] ?? 0, 'Rejected quantity'));
                if ($received <= 0 && $rejected <= 0) continue;
                if ($rejected > $received) erp_invalid("{$item['name']}: rejected quantity cannot be more than received.");
                $accepted = erp_q($received - $rejected);
                if ($type === 'product' && (floor($received) != $received || floor($rejected) != $rejected)) erp_invalid("{$item['name']}: product packs must be whole numbers.");
                if ($pi && !$allowOver) {
                    $pending = erp_q($pi['quantity'] - $pi['received_qty']);
                    if ($accepted > $pending + 0.0005) erp_invalid("{$item['name']}: accepted {$accepted} is more than the pending PO quantity {$pending}.");
                }
                if ($rejected > 0 && trim((string)($it['rejection_reason'] ?? '')) === '') erp_invalid("{$item['name']}: give a rejection reason.");
                $rate = $pi ? ($pi['quantity'] > 0 ? ($pi['quantity'] * $pi['rate'] - $pi['discount_amount']) / $pi['quantity'] : $pi['rate'])
                             : erp_num($it['rate'] ?? 0, 'Rate');
                $mfg = erp_date($it['manufacturing_date'] ?? ''); $exp = erp_date($it['expiry_date'] ?? '');
                if ($mfg && $exp && $exp < $mfg) erp_invalid("{$item['name']}: expiry date is before the manufacturing date.");
                $qc = in_array($it['qc_status'] ?? '', ['pending', 'passed', 'partial', 'failed'], true) ? $it['qc_status'] : ($rejected > 0 ? ($accepted > 0 ? 'partial' : 'failed') : 'passed');
                $lines[] = ['po_item_id' => $poItemId, 'item_type' => $type, 'item_id' => $iid, 'ordered' => $pi ? erp_q($pi['quantity']) : 0,
                            'received' => $received, 'accepted' => $accepted, 'rejected' => $rejected, 'unit' => $type === 'product' ? 'pcs' : $item['unit'],
                            'rate' => erp_u($rate), 'tax_percent' => $pi ? (float)$pi['tax_percent'] : erp_num($it['tax_percent'] ?? 0, 'Tax %'),
                            'batch' => trim((string)($it['batch_number'] ?? '')) ?: null, 'lot' => trim((string)($it['lot_number'] ?? '')) ?: null,
                            'mfg' => $mfg, 'exp' => $exp, 'qc' => $qc, 'reason' => trim((string)($it['rejection_reason'] ?? '')) ?: null, 'name' => $item['name']];
            }
            if (!$lines) erp_invalid('Enter the received quantity for at least one item.');

            $pdo->beginTransaction();
            $old = null;
            if ($id) {
                $old = erp_row($pdo, "SELECT * FROM goods_receipts WHERE id = ? FOR UPDATE", [$id]);
                if (!$old) erp_invalid('Goods receipt not found.');
                if ($old['status'] !== 'draft') erp_invalid('A posted or cancelled goods receipt cannot be edited.');
                $pdo->prepare("UPDATE goods_receipts SET po_id=?, supplier_id=?, received_date=?, warehouse_id=?, received_by=?, supplier_challan_no=?, vehicle_number=?, notes=? WHERE id=?")
                    ->execute([$poId, $supplierId, $date, $warehouseId, erp_input('received_by') ?: erp_user(), erp_input('supplier_challan_no') ?: null, erp_input('vehicle_number') ?: null, erp_input('notes') ?: null, $id]);
                $pdo->prepare("DELETE FROM goods_receipt_items WHERE grn_id = ?")->execute([$id]);
                $num = $old['grn_number'];
            } else {
                $num = next_document_number($pdo, 'grn', 'GRN');
                $pdo->prepare("INSERT INTO goods_receipts (grn_number, po_id, supplier_id, received_date, warehouse_id, received_by, supplier_challan_no, vehicle_number, notes, status, created_by)
                               VALUES (?,?,?,?,?,?,?,?,?, 'draft', ?)")
                    ->execute([$num, $poId, $supplierId, $date, $warehouseId, erp_input('received_by') ?: erp_user(), erp_input('supplier_challan_no') ?: null, erp_input('vehicle_number') ?: null, erp_input('notes') ?: null, erp_user()]);
                $id = (int)$pdo->lastInsertId();
            }
            $ins = $pdo->prepare("INSERT INTO goods_receipt_items (grn_id, po_item_id, item_type, item_id, ordered_qty, received_qty, accepted_qty, rejected_qty, unit, rate,
                                   batch_number, lot_number, manufacturing_date, expiry_date, qc_status, rejection_reason) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            foreach ($lines as &$l) {
                $ins->execute([$id, $l['po_item_id'], $l['item_type'], $l['item_id'], $l['ordered'], $l['received'], $l['accepted'], $l['rejected'], $l['unit'], $l['rate'],
                               $l['batch'], $l['lot'], $l['mfg'], $l['exp'], $l['qc'], $l['reason']]);
                $l['grn_item_id'] = (int)$pdo->lastInsertId();
            }
            unset($l);

            $message = "{$num} saved as draft (stock not changed yet).";
            if ($action === 'grn_post') {
                if ($po && !in_array($po['status'], ['approved', 'partially_received'], true)) erp_invalid("Goods can only be received against an approved PO (this one is {$po['status']}).");
                $taxInCost = erp_tax_in_cost($pdo);
                $productLines = [];
                foreach ($lines as $l) {
                    if ($l['accepted'] <= 0) continue;
                    $unitCost = erp_u($l['rate'] * ($taxInCost ? (1 + $l['tax_percent'] / 100) : 1));
                    if ($l['item_type'] === 'product') {
                        $productLines[] = ['product_id' => $l['item_id'], 'qty' => (int)$l['accepted'], 'rate' => $unitCost, 'batch' => $l['batch'], 'mfg' => $l['mfg'], 'expiry' => $l['exp'], 'grn_item_id' => $l['grn_item_id']];
                    } else {
                        $mv = erp_raw_movement($pdo, $l['item_id'], $l['accepted'], 'purchase', 'grn', $num, "GRN {$num}", $warehouseId);
                        erp_cost_entry($pdo, 'raw_material', $l['item_id'], 'receipt', $l['accepted'], $l['accepted'] * $unitCost, 'grn', $num, $l['grn_item_id'], "GRN {$num}");
                    }
                    $pdo->prepare("INSERT INTO inventory_batches (item_type, item_id, warehouse_id, batch_number, lot_number, manufacturing_date, expiry_date, supplier_id,
                                     source_type, source_id, source_item_id, received_date, qty_received, unit_cost) VALUES (?,?,?,?,?,?,?,?, 'grn', ?,?,?,?,?)")
                        ->execute([$l['item_type'], $l['item_id'], $warehouseId, $l['batch'], $l['lot'], $l['mfg'], $l['exp'], $supplierId, $id, $l['grn_item_id'], $date, $l['accepted'], $unitCost]);
                    if ($l['po_item_id']) $pdo->prepare("UPDATE purchase_order_items SET received_qty = received_qty + ? WHERE id = ?")->execute([$l['accepted'], $l['po_item_id']]);
                }
                $stockInId = null; $stockInNo = null;
                if ($productLines) {
                    $si = erp_post_stock_in($pdo, ['date' => $date, 'supplier_id' => $supplierId, 'reference' => $num, 'warehouse_id' => $warehouseId,
                                                   'received_by' => erp_input('received_by') ?: erp_user(), 'vehicle_number' => erp_input('vehicle_number') ?: null,
                                                   'remarks' => 'Posted from ' . $num . ($po ? ' / ' . $po['po_number'] : ''), 'reference_type' => 'grn',
                                                   'movement_type' => 'purchase', 'reason' => 'Goods receipt ' . $num], $productLines);
                    $stockInId = $si['id']; $stockInNo = $si['number'];
                    foreach ($productLines as $pl) erp_cost_entry($pdo, 'product', $pl['product_id'], 'receipt', $pl['qty'], $pl['qty'] * $pl['rate'], 'grn', $stockInNo, $pl['grn_item_id'], "GRN {$num}");
                }
                $pdo->prepare("UPDATE goods_receipts SET status = 'posted', stock_in_id = ?, posted_by = ?, posted_at = NOW() WHERE id = ?")->execute([$stockInId, erp_user(), $id]);
                if ($po) {
                    $open = (float)erp_val($pdo, "SELECT COALESCE(SUM(GREATEST(quantity - received_qty, 0)),0) FROM purchase_order_items WHERE po_id = ?", [$poId]);
                    $pdo->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?")->execute([$open <= 0.0005 ? 'fully_received' : 'partially_received', $poId]);
                }
                $acc = array_sum(array_column($lines, 'accepted')); $rej = array_sum(array_column($lines, 'rejected'));
                $message = "{$num} posted. Accepted {$acc} added to stock" . ($stockInNo ? " ({$stockInNo})" : '') . ($rej > 0 ? ", {$rej} rejected (not added)." : '.');
            }
            $pdo->commit();
            log_audit($pdo, $action === 'grn_post' ? 'post' : ($old ? 'update' : 'create'), 'goods_receipts', $id, $old, ['grn_number' => $num, 'po' => $po['po_number'] ?? null, 'lines' => $lines]);
            erp_out(['status' => 'success', 'id' => $id, 'grn_number' => $num, 'message' => $message]);

        case 'grn_cancel':
            $id = (int)erp_input('id');
            $g = erp_row($pdo, "SELECT * FROM goods_receipts WHERE id = ?", [$id]);
            if (!$g) erp_invalid('Goods receipt not found.');
            if ($g['status'] !== 'draft') erp_invalid('Only draft goods receipts can be cancelled. For posted receipts, create a Purchase Return.');
            $pdo->prepare("UPDATE goods_receipts SET status = 'cancelled' WHERE id = ?")->execute([$id]);
            log_audit($pdo, 'cancel', 'goods_receipts', $id, ['status' => 'draft'], ['status' => 'cancelled']);
            erp_out(['status' => 'success', 'message' => "{$g['grn_number']} cancelled."]);

        // ================================================================ PURCHASE INVOICES
        case 'pinv_list':
            $w = []; $p = [];
            if ($s = erp_input('status')) {
                if (in_array($s, ['unpaid', 'partially_paid', 'paid', 'overdue'], true)) { $w[] = "pi.status = 'posted'"; $payFilter = $s; }
                else { $w[] = 'pi.status = ?'; $p[] = $s; }
            }
            if ($sid = (int)erp_input('supplier_id', 0)) { $w[] = 'pi.supplier_id = ?'; $p[] = $sid; }
            if ($d = erp_date(erp_input('date_from'))) { $w[] = 'pi.invoice_date >= ?'; $p[] = $d; }
            if ($d = erp_date(erp_input('date_to'))) { $w[] = 'pi.invoice_date <= ?'; $p[] = $d; }
            if ($q = trim((string)erp_input('q', ''))) { $w[] = '(pi.pinv_number LIKE ? OR pi.supplier_invoice_no LIKE ? OR s.supplier_name LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%"); }
            $rows = erp_rows($pdo, "SELECT pi.*, s.supplier_name, po.po_number, g.grn_number FROM purchase_invoices pi JOIN suppliers s ON s.id = pi.supplier_id
                                    LEFT JOIN purchase_orders po ON po.id = pi.po_id LEFT JOIN goods_receipts g ON g.id = pi.grn_id" .
                                   ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY pi.id DESC LIMIT 500", $p);
            $rows = pinv_with_balances($pdo, $rows);
            if (!empty($payFilter)) $rows = array_values(array_filter($rows, function ($r) use ($payFilter) { return $r['payment_status'] === $payFilter; }));
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'pinv_prefill':
            $grnId = (int)erp_input('grn_id');
            $g = erp_row($pdo, "SELECT g.*, s.supplier_name, po.po_number FROM goods_receipts g JOIN suppliers s ON s.id = g.supplier_id LEFT JOIN purchase_orders po ON po.id = g.po_id WHERE g.id = ?", [$grnId]);
            if (!$g) erp_fail('Goods receipt not found.');
            if ($g['status'] !== 'posted') erp_fail('Only posted goods receipts can be billed.');
            $items = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT gi.*, COALESCE(poi.tax_percent,0) AS tax_percent,
                                  (SELECT COALESCE(SUM(pii.quantity),0) FROM purchase_invoice_items pii JOIN purchase_invoices x ON x.id = pii.pinv_id
                                     WHERE pii.grn_item_id = gi.id AND x.status <> 'cancelled') AS billed_qty
                                  FROM goods_receipt_items gi LEFT JOIN purchase_order_items poi ON poi.id = gi.po_item_id WHERE gi.grn_id = ? AND gi.accepted_qty > 0", [$grnId]));
            foreach ($items as &$it) $it['billable_qty'] = erp_q(max(0, $it['accepted_qty'] - $it['billed_qty']));
            unset($it);
            erp_out(['status' => 'success', 'grn' => $g, 'items' => $items]);

        case 'pinv_get':
            $id = (int)erp_input('id');
            $pi = erp_row($pdo, "SELECT pi.*, s.supplier_name, s.gst_number AS supplier_gst, po.po_number, g.grn_number FROM purchase_invoices pi JOIN suppliers s ON s.id = pi.supplier_id
                                 LEFT JOIN purchase_orders po ON po.id = pi.po_id LEFT JOIN goods_receipts g ON g.id = pi.grn_id WHERE pi.id = ?", [$id]);
            if (!$pi) erp_fail('Purchase invoice not found.');
            $pi = pinv_with_balances($pdo, [$pi])[0];
            $pi['items'] = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT * FROM purchase_invoice_items WHERE pinv_id = ? ORDER BY id", [$id]));
            $pi['charges'] = erp_rows($pdo, "SELECT * FROM purchase_invoice_charges WHERE pinv_id = ? ORDER BY id", [$id]);
            $pi['payments'] = erp_rows($pdo, "SELECT * FROM purchase_payments WHERE pinv_id = ? ORDER BY id", [$id]);
            $pi['returns'] = erp_rows($pdo, "SELECT id, return_number, return_date, total_value, settlement, status FROM purchase_returns WHERE pinv_id = ?", [$id]);
            erp_out(['status' => 'success', 'record' => $pi]);

        case 'pinv_save':
        case 'pinv_post':
            $id = (int)erp_input('id', 0);
            $grnId = (int)erp_input('grn_id', 0) ?: null;
            $grn = $grnId ? erp_row($pdo, "SELECT * FROM goods_receipts WHERE id = ?", [$grnId]) : null;
            if ($grnId && (!$grn || $grn['status'] !== 'posted')) erp_invalid('Link a posted goods receipt.');
            $supplierId = $grn ? (int)$grn['supplier_id'] : (int)erp_input('supplier_id');
            if (!erp_supplier_name($pdo, $supplierId)) erp_invalid('Choose a supplier.');
            $poId = $grn ? ($grn['po_id'] ? (int)$grn['po_id'] : null) : ((int)erp_input('po_id', 0) ?: null);
            $supInv = trim((string)erp_input('supplier_invoice_no', ''));
            if ($supInv === '') erp_invalid('Enter the vendor\'s invoice / bill number.');
            $date = erp_date(erp_input('invoice_date'), true);
            $due = erp_date(erp_input('due_date'));
            if ($due && $due < $date) erp_invalid('Due date cannot be before the invoice date.');
            $dup = erp_val($pdo, "SELECT id FROM purchase_invoices WHERE supplier_id = ? AND supplier_invoice_no = ? AND id <> ? AND status <> 'cancelled'", [$supplierId, $supInv, $id]);
            if ($dup) erp_invalid("This supplier's invoice {$supInv} is already entered.");

            $grnItems = [];
            if ($grn) foreach (erp_rows($pdo, "SELECT * FROM goods_receipt_items WHERE grn_id = ?", [$grnId]) as $gi) $grnItems[(int)$gi['id']] = $gi;
            $lines = []; $sub = 0; $disc = 0; $tax = 0; $net = 0;
            foreach (erp_json_input('items') as $it) {
                $gid = (int)($it['grn_item_id'] ?? 0) ?: null;
                if ($gid && !isset($grnItems[$gid])) erp_invalid('A line does not belong to the linked goods receipt.');
                $gi = $gid ? $grnItems[$gid] : null;
                $type = $gi ? $gi['item_type'] : erp_item_type($it['item_type'] ?? 'product');
                $iid = $gi ? (int)$gi['item_id'] : (int)($it['item_id'] ?? 0);
                if (!$iid) continue;
                $item = erp_item($pdo, $type, $iid);
                if (!$item) erp_invalid('An item in the list no longer exists.');
                $qty = erp_q(erp_num($it['quantity'] ?? 0, 'Quantity'));
                if ($qty <= 0) continue;
                if ($gi) {
                    $billed = (float)erp_val($pdo, "SELECT COALESCE(SUM(pii.quantity),0) FROM purchase_invoice_items pii JOIN purchase_invoices x ON x.id = pii.pinv_id
                                                    WHERE pii.grn_item_id = ? AND x.status <> 'cancelled' AND x.id <> ?", [$gid, $id]);
                    if ($qty > erp_q($gi['accepted_qty'] - $billed) + 0.0005) erp_invalid("{$item['name']}: billed quantity is more than the accepted quantity not yet billed (" . erp_q($gi['accepted_qty'] - $billed) . ').');
                }
                $rate = erp_u(erp_num($it['rate'] ?? 0, 'Rate'));
                $taxPct = erp_num($it['tax_percent'] ?? 0, 'Tax %');
                $l = erp_line($qty, $rate, erp_num($it['discount_amount'] ?? 0, 'Discount'), $taxPct);
                $sub += $l['gross']; $disc += $l['discount']; $tax += $l['tax']; $net += $l['total'];
                $lines[] = ['grn_item_id' => $gid, 'item_type' => $type, 'item_id' => $iid, 'qty' => $qty, 'unit' => $type === 'product' ? 'pcs' : $item['unit'],
                            'rate' => $rate, 'discount' => $l['discount'], 'tax_percent' => $taxPct, 'tax' => $l['tax'], 'total' => $l['total'], 'net' => $l['net'], 'gi' => $gi];
            }
            if (!$lines) erp_invalid('Add at least one item line.');
            $charges = [];
            $chargesTotal = 0; $costCharges = 0;
            foreach (erp_json_input('charges') as $c) {
                $amt = erp_m(erp_num($c['amount'] ?? 0, 'Charge amount'));
                if ($amt <= 0) continue;
                $type = in_array($c['charge_type'] ?? '', ['freight', 'transport', 'loading', 'unloading', 'packaging', 'other'], true) ? $c['charge_type'] : 'other';
                $toCost = !isset($c['add_to_cost']) || (string)$c['add_to_cost'] !== '0';
                $charges[] = [$type, mb_substr((string)($c['description'] ?? ''), 0, 255), $amt, $toCost ? 1 : 0];
                $chargesTotal += $amt; if ($toCost) $costCharges += $amt;
            }
            $grand = erp_m($net + $chargesTotal);
            $billForApproval = false;   // approval rule for bills (1 Oct 2026; off unless enabled in Approvals)
            if ($action === 'pinv_post' && erpx_installed($pdo)
                && apr_intercept($pdo, 'purchase_invoice', 'sup:' . $supplierId . ':' . mb_strtolower($supInv), $grand, 'Purchase bill ' . $supInv . ' — ' . erp_supplier_name($pdo, $supplierId), $supInv, 'purchase_api.php', $id ?: null)) {
                $action = 'pinv_save'; $billForApproval = true;
            }

            // Allocate direct charges to lines by net value → landed unit cost
            $taxInCost = erp_tax_in_cost($pdo);
            $netSum = array_sum(array_column($lines, 'net'));
            $allocated = 0.0;
            foreach ($lines as $i => &$l) {
                $share = $i === count($lines) - 1 ? erp_m($costCharges - $allocated) : erp_m($netSum > 0 ? $costCharges * $l['net'] / $netSum : $costCharges / count($lines));
                $allocated += $share;
                $l['alloc'] = $share;
                $base = $l['net'] + ($taxInCost ? $l['tax'] : 0);
                $l['landed_unit'] = erp_u(($base + $share) / $l['qty']);
            }
            unset($l);

            $pdo->beginTransaction();
            $old = null;
            if ($id) {
                $old = erp_row($pdo, "SELECT * FROM purchase_invoices WHERE id = ? FOR UPDATE", [$id]);
                if (!$old) erp_invalid('Purchase invoice not found.');
                if ($old['status'] !== 'draft') erp_invalid('A posted or cancelled purchase invoice cannot be edited. Cancel it and enter a new one.');
                $pdo->prepare("UPDATE purchase_invoices SET supplier_invoice_no=?, supplier_id=?, invoice_date=?, due_date=?, po_id=?, grn_id=?, subtotal=?, discount_total=?, tax_total=?,
                               charges_total=?, grand_total=?, notes=? WHERE id=?")
                    ->execute([$supInv, $supplierId, $date, $due, $poId, $grnId, erp_m($sub), erp_m($disc), erp_m($tax), erp_m($chargesTotal), $grand, erp_input('notes') ?: null, $id]);
                $pdo->prepare("DELETE FROM purchase_invoice_items WHERE pinv_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM purchase_invoice_charges WHERE pinv_id = ?")->execute([$id]);
                $num = $old['pinv_number'];
            } else {
                $num = next_document_number($pdo, 'purchase_invoice', 'PINV');
                $pdo->prepare("INSERT INTO purchase_invoices (pinv_number, supplier_invoice_no, supplier_id, invoice_date, due_date, po_id, grn_id, subtotal, discount_total, tax_total,
                               charges_total, grand_total, notes, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, 'draft', ?)")
                    ->execute([$num, $supInv, $supplierId, $date, $due, $poId, $grnId, erp_m($sub), erp_m($disc), erp_m($tax), erp_m($chargesTotal), $grand, erp_input('notes') ?: null, erp_user()]);
                $id = (int)$pdo->lastInsertId();
            }
            $ins = $pdo->prepare("INSERT INTO purchase_invoice_items (pinv_id, grn_item_id, item_type, item_id, quantity, unit, rate, discount_amount, tax_percent, tax_amount, line_total,
                                   allocated_charges, landed_unit_cost) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            foreach ($lines as $l) $ins->execute([$id, $l['grn_item_id'], $l['item_type'], $l['item_id'], $l['qty'], $l['unit'], $l['rate'], $l['discount'], $l['tax_percent'], $l['tax'], $l['total'], $l['alloc'], $l['landed_unit']]);
            $cIns = $pdo->prepare("INSERT INTO purchase_invoice_charges (pinv_id, charge_type, description, amount, add_to_cost) VALUES (?,?,?,?,?)");
            foreach ($charges as $c) $cIns->execute(array_merge([$id], $c));

            $message = "{$num} saved as draft.";
            if ($action === 'pinv_post') {
                // Landed cost + price difference vs the GRN rate go into inventory value (value-only cost rows).
                foreach ($lines as $l) {
                    if (!$l['gi']) continue;
                    $gi = $l['gi'];
                    $grnUnit = (float)$gi['rate'] * ($taxInCost ? (1 + $l['tax_percent'] / 100) : 1);
                    $diff = erp_m(($l['landed_unit'] - $grnUnit) * $l['qty']);
                    if (abs($diff) >= 0.01) erp_cost_entry($pdo, $l['item_type'], $l['item_id'], 'landed', 0, $diff, 'purchase_invoice', $num, $id,
                                                           "Landed cost / price difference {$num} (" . ($l['alloc'] > 0 ? 'charges ₹' . $l['alloc'] : 'rate') . ')');
                }
                $pdo->prepare("UPDATE purchase_invoices SET status = 'posted', posted_by = ?, posted_at = NOW() WHERE id = ?")->execute([erp_user(), $id]);
                $message = "{$num} posted. Payable ₹" . number_format($grand, 2) . '.';
                // (1 Oct 2026) the PO was paid in advance (purchase flow pays before the shop bill): use that advance for this bill,
                // so the bill shows as paid and nobody pays twice. Only the link changes — no new money entry, no new journal.
                if ($poId) {
                    $poNo = (string)erp_val($pdo, "SELECT po_number FROM purchase_orders WHERE id = ?", [$poId]);
                    $flowPay = [];
                    try { $flowPay = array_map('intval', array_column(erp_rows($pdo, "SELECT payment_id FROM purchase_flows WHERE po_id = ? AND payment_id IS NOT NULL", [$poId]), 'payment_id')); } catch (PDOException $e) {}
                    $left = erp_m($grand - (float)erp_val($pdo, "SELECT COALESCE(SUM(amount),0) FROM purchase_payments WHERE pinv_id = ? AND status = 'completed'", [$id]));
                    $used = 0.0;
                    foreach (erp_rows($pdo, "SELECT id, payment_number, amount, notes FROM purchase_payments WHERE supplier_id = ? AND pinv_id IS NULL AND status = 'completed' ORDER BY payment_date, id FOR UPDATE", [$supplierId]) as $adv) {
                        $mine = in_array((int)$adv['id'], $flowPay, true) || ($poNo !== '' && strpos((string)$adv['notes'], 'For ' . $poNo) === 0);
                        if (!$mine || (float)$adv['amount'] > $left + 0.005) continue;
                        $pdo->prepare("UPDATE purchase_payments SET pinv_id = ?, notes = CONCAT(COALESCE(notes,''), ?) WHERE id = ? AND pinv_id IS NULL")
                            ->execute([$id, " [advance applied to {$num}]", $adv['id']]);
                        $left = erp_m($left - (float)$adv['amount']); $used += (float)$adv['amount'];
                        log_audit($pdo, 'update', 'purchase_payments', $adv['id'], ['pinv_id' => null], ['pinv_id' => $id, 'advance_applied_to' => $num]);
                    }
                    if ($used > 0) $message .= ' Advance ₹' . number_format($used, 2) . ' already paid for ' . $poNo . ' is set against this bill' . ($left > 0.005 ? ' — balance ₹' . number_format($left, 2) . '.' : ' — fully paid.');
                }
            }
            $pdo->commit();
            log_audit($pdo, $action === 'pinv_post' ? 'post' : ($old ? 'update' : 'create'), 'purchase_invoices', $id, $old,
                      ['pinv_number' => $num, 'supplier_invoice_no' => $supInv, 'grand_total' => $grand, 'charges' => $charges]);
            if ($billForApproval) {
                $apr = apr_open($pdo, 'purchase_invoice', 'sup:' . $supplierId . ':' . mb_strtolower($supInv), $id, $supInv, 'Purchase bill ' . $supInv . ' — ' . erp_supplier_name($pdo, $supplierId), $grand,
                                'purchase_api.php', array_merge(apr_payload(), ['action' => 'pinv_post', 'id' => $id]));
                $message = "{$num} saved as draft and sent for approval ({$apr}). The payable is recorded when it is approved.";
            }
            erp_out(['status' => 'success', 'id' => $id, 'pinv_number' => $num, 'message' => $message]);

        case 'pinv_cancel':
            $id = (int)erp_input('id');
            $pi = erp_row($pdo, "SELECT * FROM purchase_invoices WHERE id = ?", [$id]);
            if (!$pi) erp_invalid('Purchase invoice not found.');
            if ($pi['status'] === 'cancelled') erp_invalid('Already cancelled.');
            // an advance that was only set against this bill goes back to being an advance (1 Oct 2026)
            $pdo->prepare("UPDATE purchase_payments SET pinv_id = NULL, notes = REPLACE(notes, ?, '') WHERE pinv_id = ? AND notes LIKE ?")
                ->execute([" [advance applied to {$pi['pinv_number']}]", $id, '%[advance applied to ' . $pi['pinv_number'] . ']%']);
            $paid = (float)erp_val($pdo, "SELECT COALESCE(SUM(amount),0) FROM purchase_payments WHERE pinv_id = ? AND status = 'completed'", [$id]);
            if ($paid > 0) erp_invalid('Payments are recorded against this invoice. Cancel those payments first.');
            $reason = trim((string)erp_input('reason', ''));
            if ($reason === '') erp_invalid('Give a reason for cancelling.');
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE purchase_invoices SET status = 'cancelled', notes = CONCAT(COALESCE(notes,''), ?) WHERE id = ?")->execute(["\n[Cancelled by " . erp_user() . ": {$reason}]", $id]);
            $pdo->prepare("UPDATE inventory_cost_entries SET status = 'cancelled' WHERE reference_type = 'purchase_invoice' AND source_id = ?")->execute([$id]);
            $pdo->commit();
            log_audit($pdo, 'cancel', 'purchase_invoices', $id, ['status' => $pi['status']], ['status' => 'cancelled', 'reason' => $reason]);
            erp_out(['status' => 'success', 'message' => "{$pi['pinv_number']} cancelled (landed-cost entries reversed)."]);

        // ================================================================ SUPPLIER PAYMENTS
        case 'pay_list':
            $w = []; $p = [];
            if ($sid = (int)erp_input('supplier_id', 0)) { $w[] = 'pp.supplier_id = ?'; $p[] = $sid; }
            if ($s = erp_input('status')) { $w[] = 'pp.status = ?'; $p[] = $s; }
            if ($d = erp_date(erp_input('date_from'))) { $w[] = 'pp.payment_date >= ?'; $p[] = $d; }
            if ($d = erp_date(erp_input('date_to'))) { $w[] = 'pp.payment_date <= ?'; $p[] = $d; }
            if ($q = trim((string)erp_input('q', ''))) { $w[] = '(pp.payment_number LIKE ? OR pp.reference_number LIKE ? OR s.supplier_name LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%"); }
            $rows = erp_rows($pdo, "SELECT pp.*, s.supplier_name, pi.pinv_number, pi.supplier_invoice_no, t.transaction_id AS txn_number
                                    FROM purchase_payments pp JOIN suppliers s ON s.id = pp.supplier_id LEFT JOIN purchase_invoices pi ON pi.id = pp.pinv_id
                                    LEFT JOIN accounts_transactions t ON t.id = pp.accounts_transaction_id" .
                                   ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY pp.id DESC LIMIT 500", $p);
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'pay_save':
            $supplierId = (int)erp_input('supplier_id');
            $sname = erp_supplier_name($pdo, $supplierId);
            if (!$sname) erp_invalid('Choose a supplier.');
            $pinvId = (int)erp_input('pinv_id', 0) ?: null;
            $amount = erp_m(erp_num(erp_input('amount'), 'Amount', false));
            $date = erp_date(erp_input('payment_date'), true);
            $mode = (string)erp_input('payment_mode', 'bank_transfer');
            if (!in_array($mode, ['cash', 'bank_transfer', 'upi', 'cheque', 'card', 'other'], true)) erp_invalid('Choose a payment mode.');
            if (in_array($mode, ['bank_transfer', 'upi', 'cheque'], true) && trim((string)erp_input('reference_number', '')) === '') erp_invalid('Enter the reference / UTR / cheque number.');
            // never post the same payment twice (1 Oct 2026): same supplier + same reference / UTR / cheque no.
            $payRef = trim((string)erp_input('reference_number', ''));
            if ($payRef !== '' && erp_val($pdo, "SELECT id FROM purchase_payments WHERE supplier_id = ? AND reference_number = ? AND status = 'completed'", [$supplierId, $payRef]))
                erp_invalid("A payment with reference {$payRef} is already recorded for this supplier.");
            if (erpx_installed($pdo))
                apr_gate($pdo, 'purchase_payment', sha1($supplierId . '|' . $pinvId . '|' . $amount . '|' . $date . '|' . $payRef), $amount, 'Pay ₹' . number_format($amount, 2) . ' to ' . $sname,
                         $payRef ?: null, 'purchase_api.php', apr_payload());
            $pdo->beginTransaction();
            $pinv = null;
            if ($pinvId) {
                $pinv = erp_row($pdo, "SELECT * FROM purchase_invoices WHERE id = ? FOR UPDATE", [$pinvId]);
                if (!$pinv || (int)$pinv['supplier_id'] !== $supplierId) erp_invalid('The invoice does not belong to this supplier.');
                if ($pinv['status'] !== 'posted') erp_invalid('Payments can only be made against posted invoices.');
                $bal = pinv_with_balances($pdo, [$pinv])[0];
                if ($amount > $bal['balance'] + 0.005) erp_invalid('Amount is more than the outstanding ₹' . number_format($bal['balance'], 2) . ' on this invoice. Record the extra as an advance (no invoice).');
            }
            $num = next_document_number($pdo, 'purchase_payment', 'PPAY');
            $txnId = erp_cash_entry($pdo, 'payment_made', 'Supplier Payment', $amount, $date, $mode === 'bank_transfer' ? 'bank_transfer' : $mode, $sname,
                                    'purchase_payment', $num, 'Payment to ' . $sname . ($pinv ? ' for ' . $pinv['supplier_invoice_no'] : ' (advance)'), erp_input('account') ?: null);
            $pdo->prepare("INSERT INTO purchase_payments (payment_number, supplier_id, pinv_id, payment_date, amount, payment_mode, account, reference_number, notes, accounts_transaction_id, created_by)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$num, $supplierId, $pinvId, $date, $amount, $mode, erp_input('account') ?: null, erp_input('reference_number') ?: null, erp_input('notes') ?: null, $txnId, erp_user()]);
            $pid = (int)$pdo->lastInsertId();
            $pdo->commit();
            log_audit($pdo, 'create', 'purchase_payments', $pid, null, ['payment_number' => $num, 'supplier' => $sname, 'amount' => $amount, 'invoice' => $pinv['pinv_number'] ?? null]);
            erp_out(['status' => 'success', 'id' => $pid, 'payment_number' => $num, 'message' => "{$num}: ₹" . number_format($amount, 2) . " paid to {$sname}. Also recorded in Transactions."]);

        case 'pay_cancel':
            $id = (int)erp_input('id');
            $reason = trim((string)erp_input('reason', ''));
            if ($reason === '') erp_invalid('Give a reason for cancelling this payment.');
            $pay = erp_row($pdo, "SELECT * FROM purchase_payments WHERE id = ?", [$id]);
            if (!$pay) erp_invalid('Payment not found.');
            if ($pay['status'] === 'cancelled') erp_invalid('Already cancelled.');
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE purchase_payments SET status = 'cancelled', cancelled_by = ?, cancel_reason = ? WHERE id = ?")->execute([erp_user(), $reason, $id]);
            erp_cancel_cash_entry($pdo, $pay['accounts_transaction_id'] ? (int)$pay['accounts_transaction_id'] : null);
            $pdo->commit();
            log_audit($pdo, 'cancel', 'purchase_payments', $id, $pay, ['status' => 'cancelled', 'reason' => $reason]);
            erp_out(['status' => 'success', 'message' => "{$pay['payment_number']} cancelled; the linked Transactions entry was cancelled too."]);

        // ================================================================ PURCHASE RETURNS
        case 'ret_list':
            $w = []; $p = [];
            if ($sid = (int)erp_input('supplier_id', 0)) { $w[] = 'r.supplier_id = ?'; $p[] = $sid; }
            if ($d = erp_date(erp_input('date_from'))) { $w[] = 'r.return_date >= ?'; $p[] = $d; }
            if ($d = erp_date(erp_input('date_to'))) { $w[] = 'r.return_date <= ?'; $p[] = $d; }
            $rows = erp_rows($pdo, "SELECT r.*, s.supplier_name, g.grn_number, pi.pinv_number, po.po_number, so.stock_out_number
                                    FROM purchase_returns r JOIN suppliers s ON s.id = r.supplier_id LEFT JOIN goods_receipts g ON g.id = r.grn_id
                                    LEFT JOIN purchase_invoices pi ON pi.id = r.pinv_id LEFT JOIN purchase_orders po ON po.id = r.po_id
                                    LEFT JOIN stock_outs so ON so.id = r.stock_out_id" .
                                   ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY r.id DESC LIMIT 500", $p);
            erp_out(['status' => 'success', 'rows' => $rows]);

        case 'ret_get':
            $id = (int)erp_input('id');
            $r = erp_row($pdo, "SELECT r.*, s.supplier_name, g.grn_number, pi.pinv_number, po.po_number FROM purchase_returns r JOIN suppliers s ON s.id = r.supplier_id
                                LEFT JOIN goods_receipts g ON g.id = r.grn_id LEFT JOIN purchase_invoices pi ON pi.id = r.pinv_id LEFT JOIN purchase_orders po ON po.id = r.po_id WHERE r.id = ?", [$id]);
            if (!$r) erp_fail('Purchase return not found.');
            $r['items'] = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT * FROM purchase_return_items WHERE return_id = ?", [$id]));
            erp_out(['status' => 'success', 'record' => $r]);

        case 'ret_prefill':
            $grnId = (int)erp_input('grn_id');
            $g = erp_row($pdo, "SELECT g.*, s.supplier_name FROM goods_receipts g JOIN suppliers s ON s.id = g.supplier_id WHERE g.id = ? AND g.status = 'posted'", [$grnId]);
            if (!$g) erp_fail('Choose a posted goods receipt.');
            $items = erp_attach_item_names($pdo, erp_rows($pdo, "SELECT gi.*,
                        (SELECT COALESCE(SUM(ri.quantity),0) FROM purchase_return_items ri JOIN purchase_returns r ON r.id = ri.return_id WHERE ri.grn_item_id = gi.id AND r.status = 'posted') AS returned_qty,
                        (SELECT pii.landed_unit_cost FROM purchase_invoice_items pii JOIN purchase_invoices x ON x.id = pii.pinv_id WHERE pii.grn_item_id = gi.id AND x.status = 'posted' ORDER BY pii.id DESC LIMIT 1) AS invoiced_unit
                     FROM goods_receipt_items gi WHERE gi.grn_id = ? AND gi.accepted_qty > 0", [$grnId]));
            foreach ($items as &$it) {
                $it['returnable_qty'] = erp_q(max(0, $it['accepted_qty'] - $it['returned_qty']));
                $it['return_rate'] = erp_u($it['invoiced_unit'] ?: $it['rate']);
                $it['current_stock'] = erp_item($pdo, $it['item_type'], (int)$it['item_id'])['stock'] ?? 0;
            }
            unset($it);
            $inv = erp_row($pdo, "SELECT id, pinv_number FROM purchase_invoices WHERE grn_id = ? AND status = 'posted' ORDER BY id DESC LIMIT 1", [$grnId]);
            erp_out(['status' => 'success', 'grn' => $g, 'items' => $items, 'invoice' => $inv]);

        case 'ret_post':
            $grnId = (int)erp_input('grn_id');
            $g = erp_row($pdo, "SELECT * FROM goods_receipts WHERE id = ? AND status = 'posted'", [$grnId]);
            if (!$g) erp_invalid('Choose a posted goods receipt to return against.');
            $reason = trim((string)erp_input('reason', ''));
            if ($reason === '') erp_invalid('Give a return reason.');
            $date = erp_date(erp_input('return_date'), true);
            $settlement = in_array(erp_input('settlement'), ['credit_note', 'refund', 'replacement'], true) ? erp_input('settlement') : 'credit_note';
            $pinvId = (int)erp_input('pinv_id', 0) ?: (int)erp_val($pdo, "SELECT id FROM purchase_invoices WHERE grn_id = ? AND status = 'posted' ORDER BY id DESC LIMIT 1", [$grnId]) ?: null;
            $grnItems = [];
            foreach (erp_rows($pdo, "SELECT * FROM goods_receipt_items WHERE grn_id = ?", [$grnId]) as $gi) $grnItems[(int)$gi['id']] = $gi;
            $lines = []; $total = 0;
            foreach (erp_json_input('items') as $it) {
                $gid = (int)($it['grn_item_id'] ?? 0);
                if (!isset($grnItems[$gid])) continue;
                $gi = $grnItems[$gid];
                $qty = erp_q(erp_num($it['quantity'] ?? 0, 'Return quantity'));
                if ($qty <= 0) continue;
                $item = erp_item($pdo, $gi['item_type'], (int)$gi['item_id']);
                if ($gi['item_type'] === 'product' && floor($qty) != $qty) erp_invalid("{$item['name']}: product packs must be whole numbers.");
                $returned = (float)erp_val($pdo, "SELECT COALESCE(SUM(ri.quantity),0) FROM purchase_return_items ri JOIN purchase_returns r ON r.id = ri.return_id WHERE ri.grn_item_id = ? AND r.status = 'posted'", [$gid]);
                if ($qty > erp_q($gi['accepted_qty'] - $returned) + 0.0005) erp_invalid("{$item['name']}: can return at most " . erp_q($gi['accepted_qty'] - $returned) . '.');
                if ($qty > $item['stock'] + 0.0005) erp_invalid("{$item['name']}: only {$item['stock']} in stock now.");
                $rate = erp_u(erp_num($it['rate'] ?? $gi['rate'], 'Return rate'));
                $val = erp_m($qty * $rate);
                $total += $val;
                $lines[] = ['gi' => $gi, 'qty' => $qty, 'rate' => $rate, 'value' => $val, 'name' => $item['name']];
            }
            if (!$lines) erp_invalid('Enter a return quantity for at least one item.');
            $refund = $settlement === 'refund' ? erp_m(erp_num(erp_input('refund_received', 0), 'Refund received')) : 0.0;
            if ($refund > $total + 0.005) erp_invalid('Refund received cannot be more than the return value.');
            if (erpx_installed($pdo))   // approval rule for purchase returns (1 Oct 2026; off unless enabled)
                apr_gate($pdo, 'purchase_return', sha1($grnId . '|' . json_encode(erp_json_input('items')) . '|' . $date), $total, 'Purchase return on ' . $g['grn_number'] . ' (₹' . number_format($total, 2) . ')',
                         $g['grn_number'], 'purchase_api.php', apr_payload());

            $pdo->beginTransaction();
            $num = next_document_number($pdo, 'purchase_return', 'PRET');
            $pdo->prepare("INSERT INTO purchase_returns (return_number, supplier_id, po_id, grn_id, pinv_id, return_date, warehouse_id, reason, settlement, credit_note_number,
                             total_value, refund_received, status, notes, posted_by, posted_at, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'posted', ?,?, NOW(), ?)")
                ->execute([$num, $g['supplier_id'], $g['po_id'], $grnId, $pinvId, $date, $g['warehouse_id'], $reason, $settlement, erp_input('credit_note_number') ?: null,
                           erp_m($total), $refund, erp_input('notes') ?: null, erp_user(), erp_user()]);
            $rid = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO purchase_return_items (return_id, grn_item_id, batch_id, item_type, item_id, quantity, unit, rate, line_value) VALUES (?,?,?,?,?,?,?,?,?)");
            $productOut = [];
            foreach ($lines as $l) {
                $gi = $l['gi'];
                $batchId = erp_val($pdo, "SELECT id FROM inventory_batches WHERE source_type = 'grn' AND source_item_id = ?", [$gi['id']]) ?: null;
                $ins->execute([$rid, $gi['id'], $batchId, $gi['item_type'], $gi['item_id'], $l['qty'], $gi['unit'], $l['rate'], $l['value']]);
                if ($batchId) $pdo->prepare("UPDATE inventory_batches SET qty_returned = qty_returned + ? WHERE id = ?")->execute([$l['qty'], $batchId]);
                if ($gi['item_type'] === 'product') $productOut[] = ['product_id' => (int)$gi['item_id'], 'qty' => (int)$l['qty']];
                else erp_raw_movement($pdo, (int)$gi['item_id'], -$l['qty'], 'purchase_return', 'purchase_return', $num, "Purchase return {$num}", (int)$g['warehouse_id']);
            }
            $soId = null;
            if ($productOut) {
                $so = erp_post_stock_out($pdo, ['date' => $date, 'reference' => $num, 'warehouse_id' => $g['warehouse_id'], 'party' => erp_supplier_name($pdo, (int)$g['supplier_id']),
                                                'reason' => "Purchase return {$num}: {$reason}", 'movement_type' => 'return', 'reference_type' => 'purchase_return'], $productOut);
                $soId = $so['id'];
                $pdo->prepare("UPDATE purchase_returns SET stock_out_id = ? WHERE id = ?")->execute([$soId, $rid]);
            }
            if ($refund > 0) erp_cash_entry($pdo, 'payment_received', 'Supplier Refund', $refund, $date, (string)erp_input('refund_mode', 'bank_transfer'),
                                            erp_supplier_name($pdo, (int)$g['supplier_id']), 'purchase_return', $num, "Refund for purchase return {$num}");
            $pdo->commit();
            log_audit($pdo, 'create', 'purchase_returns', $rid, null, ['return_number' => $num, 'grn' => $g['grn_number'], 'value' => $total, 'settlement' => $settlement, 'refund' => $refund]);
            if (erpx_installed($pdo)) dn_issue_for_return($pdo, $rid);   // debit note (1 Oct 2026)
            erp_out(['status' => 'success', 'id' => $rid, 'return_number' => $num,
                     'message' => "{$num} posted: stock reduced, ₹" . number_format($total, 2) . ($settlement === 'replacement' ? ' to be replaced by the supplier.' : ' credited against the supplier.')]);

        default:
            erp_fail('Unknown action.');
    }
} catch (Throwable $e) {
    erp_db_error($e, $action ?: 'purchase');
}

/**
 * Adds paid / credit / balance / payment_status to posted purchase invoices.
 * Balance = grand total − completed payments − credit notes (posted returns
 * settled as credit_note or refund) + refunds received.
 */
function pinv_with_balances(PDO $pdo, array $rows): array {
    foreach ($rows as &$r) {
        $paid = (float)erp_val($pdo, "SELECT COALESCE(SUM(amount),0) FROM purchase_payments WHERE pinv_id = ? AND status = 'completed'", [$r['id']]);
        $ret = erp_row($pdo, "SELECT COALESCE(SUM(total_value),0) v, COALESCE(SUM(refund_received),0) rf FROM purchase_returns
                              WHERE pinv_id = ? AND status = 'posted' AND settlement IN ('credit_note','refund')", [$r['id']]);
        $r['amount_paid'] = erp_m($paid);
        $r['credit_amount'] = erp_m($ret['v'] - $ret['rf']);
        $r['balance'] = $r['status'] === 'posted' ? erp_m(max(0, $r['grand_total'] - $paid - ($ret['v'] - $ret['rf']))) : 0.0;
        if ($r['status'] !== 'posted') $r['payment_status'] = $r['status'];
        elseif ($r['balance'] <= 0.005) $r['payment_status'] = 'paid';
        elseif ($r['due_date'] && $r['due_date'] < date('Y-m-d')) $r['payment_status'] = 'overdue';
        elseif ($paid > 0 || $r['credit_amount'] > 0) $r['payment_status'] = 'partially_paid';
        else $r['payment_status'] = 'unpaid';
    }
    unset($r);
    return $rows;
}
