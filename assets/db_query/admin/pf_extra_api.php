<?php
// ============================================================================
// Purchase flow extras (added 1 Oct 2026) — used by the Purchase Flow page:
//   loading photo · quantity on the shop bill · unloading photo ·
//   unloading checker name + digital signature · quality-check person + report
//   + "All proofs" (every file attached in the flow, for the Manager / Admin).
// Files are the normal private Documents of the purchase order; this file only
// records which document is which. Needs purchase_flow_extras_migration.sql.
//   GET  ?action=get&pr_id=
//   POST action=save&pr_id=&part=loading_photo|unloading_photo|bill_qty|unload_sign|qc_report …
// ============================================================================
require_once __DIR__ . '/erp_helper.php';
require_once __DIR__ . '/erp_ext.php';
require_once __DIR__ . '/../../../admin/includes/role_access.php';

erp_guard($pdo, 'purchase.view');
$action = (string)erp_input('action', 'get');

function pfx_installed(PDO $pdo): bool { try { $pdo->query("SELECT 1 FROM purchase_flow_extras LIMIT 1"); return true; } catch (PDOException $e) { return false; } }
/** Executive / Manager / L1 / Admin team may add the delivery proofs. */
function pfx_can(PDO $pdo): bool {
    if ((int)($_SESSION['admin_role_id'] ?? 0) === 1) return true;
    if (erp_can($pdo, 'flow.source')) return true;
    $keys = array_merge([role_access_key((string)($_SESSION['admin_role_name'] ?? ''))], user_dash_keys($pdo, (int)($_SESSION['admin_user_id'] ?? 0)));
    return (bool)array_intersect($keys, ['executive', 'manager', 'l1', 'admin']);
}
function pfx_doc(PDO $pdo, $id): ?array {
    if (!$id) return null;
    return erp_row($pdo, "SELECT id, original_name, mime_type, created_at, uploaded_by FROM erp_documents WHERE id = ?", [(int)$id]);
}

try {
    $prId = (int)erp_input('pr_id');
    $pr = erp_row($pdo, "SELECT id, pr_number, created_by FROM purchase_requests WHERE id = ?", [$prId]);
    if (!$pr) erp_fail('Purchase request not found.');
    $seesAll = false;
    foreach (['purchase.manager_approve', 'purchase.backend_approve', 'flow.source', 'purchase_payment.create', 'po.ceo_approve'] as $p) if (erp_can($pdo, $p)) $seesAll = true;
    if (!$seesAll && $pr['created_by'] !== erp_user()) erp_fail('You can view only your own purchase requests.', 403);
    $f = erp_row($pdo, "SELECT * FROM purchase_flows WHERE pr_id = ?", [$prId]);
    $po = $f && $f['po_id'] ? erp_row($pdo, "SELECT id, po_number, status FROM purchase_orders WHERE id = ?", [$f['po_id']]) : null;
    $x = pfx_installed($pdo) ? (erp_row($pdo, "SELECT * FROM purchase_flow_extras WHERE pr_id = ?", [$prId]) ?: []) : null;

    switch ($action) {
        case 'get':
            $items = $po ? erp_attach_item_names($pdo, erp_rows($pdo, "SELECT id, item_type, item_id, quantity, unit, rate FROM purchase_order_items WHERE po_id = ? ORDER BY id", [$po['id']])) : [];
            $proofs = [];
            $add = function ($label, $step, $docId) use ($pdo, &$proofs) { if ($d = pfx_doc($pdo, $docId)) $proofs[] = $d + ['label' => $label, 'step' => $step]; };
            if ($f) {
                $add('Payment proof', 'Payment', $f['payment_proof_doc_id']); $add('Courier receipt', 'Transport', $f['courier_proof_doc_id']);
                $add('Loading DC', 'Loading', $f['loading_dc_doc_id']); $add('Shop bill' . ($f['shop_bill_type'] ? ' (' . $f['shop_bill_type'] . ')' : ''), 'Loading', $f['shop_bill_doc_id']);
                $add('Unloading DC', 'Unloading', $f['unloading_dc_doc_id']);
            }
            if ($x) {
                $add('Loading photo', 'Loading', $x['loading_photo_doc_id'] ?? null); $add('Unloading photo', 'Unloading', $x['unloading_photo_doc_id'] ?? null);
                $add('Unloading checker signature — ' . ($x['unload_checker_name'] ?? ''), 'Unloading', $x['unload_signature_doc_id'] ?? null);
                $add('Quality check report — ' . ($x['qc_inspector_name'] ?? ''), 'Quality check', $x['qc_report_doc_id'] ?? null);
            }
            // anything else attached to the PO (extra photos etc.)
            if ($po) {
                $known = array_column($proofs, 'id');
                foreach (erp_rows($pdo, "SELECT d.id, d.original_name, d.mime_type, d.created_at, d.uploaded_by, m.description FROM erp_documents d LEFT JOIN erp_document_meta m ON m.document_id = d.id
                                         WHERE d.entity_type = 'purchase_order' AND d.entity_id = ? AND COALESCE(m.status, 'active') = 'active' ORDER BY d.id", [$po['id']]) as $d)
                    if (!in_array($d['id'], $known, false)) $proofs[] = $d + ['label' => $d['description'] ?: 'Other document', 'step' => 'Also attached'];
            }
            $qc = $f && $f['grn_id'] ? erp_row($pdo, "SELECT id, qc_number, status, inspected_by, notes, completed_at FROM quality_checks WHERE grn_id = ? AND status <> 'cancelled' ORDER BY id DESC LIMIT 1", [$f['grn_id']]) : null;
            erp_out(['status' => 'success', 'installed' => $x !== null, 'extras' => $x ?: new stdClass(), 'po' => $po, 'items' => $items, 'proofs' => $proofs, 'qc' => $qc,
                     'docs' => ['loading_photo' => pfx_doc($pdo, $x['loading_photo_doc_id'] ?? null), 'unloading_photo' => pfx_doc($pdo, $x['unloading_photo_doc_id'] ?? null),
                                'signature' => pfx_doc($pdo, $x['unload_signature_doc_id'] ?? null), 'qc_report' => pfx_doc($pdo, $x['qc_report_doc_id'] ?? null)],
                     'can' => pfx_can($pdo) && erp_can($pdo, 'documents.upload'), 'me' => $_SESSION['admin_full_name'] ?? erp_user()]);

        case 'save':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') erp_fail('Invalid request method.');
            if ($x === null) erp_invalid('Run purchase_flow_extras_migration.sql once in HeidiSQL to switch this on.');
            if (!pfx_can($pdo)) erp_fail('You do not have permission to add delivery proofs.', 403);
            if (!$po) erp_invalid('Create the purchase order first.');
            $docOk = function ($id) use ($pdo, $po) {
                $id = (int)$id;
                if (!$id || !erp_val($pdo, "SELECT id FROM erp_documents WHERE id = ? AND entity_type = 'purchase_order' AND entity_id = ?", [$id, $po['id']])) erp_invalid('The file is not attached to this purchase order.');
                return $id;
            };
            $part = (string)erp_input('part'); $cols = [];
            if ($part === 'loading_photo' || $part === 'unloading_photo') $cols[$part . '_doc_id'] = $docOk(erp_input('doc_id'));
            elseif ($part === 'bill_qty') {
                $valid = array_flip(array_map('intval', array_column(erp_rows($pdo, "SELECT id FROM purchase_order_items WHERE po_id = ?", [$po['id']]), 'id')));
                $lines = [];
                foreach (erp_json_input('lines') as $l) {
                    $pid = (int)($l['po_item_id'] ?? 0);
                    if (!isset($valid[$pid])) erp_invalid('A line does not belong to this purchase order.');
                    $lines[] = ['po_item_id' => $pid, 'bill_qty' => erp_q(erp_num($l['bill_qty'] ?? 0, 'Bill quantity'))];
                }
                if (!$lines) erp_invalid('Enter the quantity written on the shop bill.');
                $cols['shop_bill_lines'] = json_encode($lines);
                $tot = trim((string)erp_input('bill_total', ''));
                $cols['shop_bill_total'] = $tot === '' ? null : erp_m(erp_num($tot, 'Bill total'));
            } elseif ($part === 'unload_sign') {
                $name = mb_substr(trim((string)erp_input('name')), 0, 150);
                if ($name === '') erp_invalid('Enter the name of the person who checked the quantity.');
                $cols += ['unload_checker_name' => $name, 'unload_signature_doc_id' => $docOk(erp_input('doc_id')), 'unload_signed_at' => date('Y-m-d H:i:s')];
            } elseif ($part === 'qc_report') {
                $name = mb_substr(trim((string)erp_input('name')), 0, 150);
                $rep = mb_substr(trim((string)erp_input('report')), 0, 4000);
                if ($name === '' || $rep === '') erp_invalid('Enter who checked the quality and the report.');
                $cols += ['qc_inspector_name' => $name, 'qc_report' => $rep, 'qc_reported_at' => date('Y-m-d H:i:s')];
                if ((int)erp_input('doc_id', 0)) $cols['qc_report_doc_id'] = $docOk(erp_input('doc_id'));
            } else erp_invalid('Unknown part.');
            $cols['updated_by'] = erp_user();
            $pdo->prepare("INSERT INTO purchase_flow_extras (pr_id, " . implode(', ', array_keys($cols)) . ") VALUES (?" . str_repeat(',?', count($cols)) . ")
                           ON DUPLICATE KEY UPDATE " . implode(', ', array_map(fn($k) => "{$k} = VALUES({$k})", array_keys($cols))))
                ->execute(array_merge([$prId], array_values($cols)));
            log_audit($pdo, 'update', 'purchase_flow_extras', $prId, null, ['part' => $part, 'pr' => $pr['pr_number']] + array_diff_key($cols, ['updated_by' => 1]));
            $msg = ['loading_photo' => 'Loading photo saved.', 'unloading_photo' => 'Unloading photo saved.', 'bill_qty' => 'Shop bill quantities saved.', 'unload_sign' => 'Signed by ' . ($cols['unload_checker_name'] ?? '') . '.', 'qc_report' => 'Quality report saved.'][$part];
            erp_out(['status' => 'success', 'message' => $msg]);

        default:
            erp_fail('Unknown action.');
    }
} catch (Throwable $e) {
    erp_db_error($e, 'purchase flow extras');
}
