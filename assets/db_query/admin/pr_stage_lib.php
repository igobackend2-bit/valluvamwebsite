<?php
// ============================================================================
// Purchase request live stage (added 2 Oct 2026) — read only.
// One line for the Purchase Requests list: where the request is right now,
// from request → manager → admin → quotations → PO → PO approval → accounts
// payment → paid → loaded → unloaded (warehouse) → QC → added to inventory.
// Reads purchase_requests / purchase_flows / pr_quotes / purchase_orders /
// approval_requests / purchase_payments / sf_consignments / goods_receipts /
// quality_checks. Writes nothing. Missing tables are skipped.
// ============================================================================

const PRS_STEPS = 12;

function prs_has(PDO $pdo, string $table): bool {
    static $c = [];
    if (!isset($c[$table])) { try { $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1"); $c[$table] = true; } catch (Throwable $e) { $c[$table] = false; } }
    return $c[$table];
}
function prs_wh(PDO $pdo, $id): string {
    static $c = [];
    $id = (int)$id; if (!$id) return '';
    if (!isset($c[$id])) $c[$id] = (string)(erp_val($pdo, "SELECT name FROM warehouses WHERE id = ?", [$id]) ?: '');
    return $c[$id];
}
function prs_out(int $step, string $label, string $tone, string $who = ''): array {
    return ['step' => $step, 'of' => PRS_STEPS, 'label' => $label, 'tone' => $tone, 'who' => $who];
}

/** tone: wait (someone must act) · ok (moving) · done · bad (rejected / failed) · muted */
function pr_stage(PDO $pdo, array $pr): ?array {
    try {
        $s = (string)$pr['status'];
        switch ($s) {
            case 'draft': return prs_out(0, 'Draft — not submitted yet', 'muted', 'Requester');
            case 'submitted': return prs_out(1, 'Waiting for Manager approval', 'wait', 'Manager');
            case 'manager_approved': return prs_out(2, 'Manager approved — waiting for Admin approval', 'wait', 'Admin');
            case 'manager_rejected': return prs_out(1, 'Rejected by Manager', 'bad', 'Requester');
            case 'backend_rejected': case 'rejected': return prs_out(2, 'Rejected by Admin', 'bad', 'Requester');
            case 'cancelled': return prs_out(0, 'Cancelled', 'muted');
        }
        if (!in_array($s, ['approved', 'converted'], true)) return null;
        $id = (int)$pr['id'];
        $f = prs_has($pdo, 'purchase_flows') ? erp_row($pdo, "SELECT * FROM purchase_flows WHERE pr_id = ?", [$id]) : null;

        // purchase order: the flow's PO, else the newest live PO made from this request
        $po = null;
        if ($f && $f['po_id']) $po = erp_row($pdo, "SELECT * FROM purchase_orders WHERE id = ?", [$f['po_id']]);
        if (!$po) $po = erp_row($pdo, "SELECT * FROM purchase_orders WHERE pr_id = ? AND status <> 'cancelled' ORDER BY id DESC LIMIT 1", [$id]);

        if (!$po) {
            $qs = $f['quote_status'] ?? 'collecting';
            if ($qs === 'approved') return prs_out(4, 'Quotation approved — purchase order to be created', 'wait', 'Admin');
            if ($qs === 'submitted') {   // FIX (2 Oct 2026): Manager approves the shop first, then Admin
                $mgr = prs_has($pdo, 'approval_requests') && erp_val($pdo, "SELECT id FROM approval_requests WHERE module = 'pr_quotation_mgr' AND request_key = ? AND status IN ('submitted','under_review')", [(string)$id]);
                return $mgr ? prs_out(3, 'Quotation submitted — waiting for Manager approval', 'wait', 'Manager') : (prs_has($pdo, 'approval_requests') && erp_val($pdo, "SELECT id FROM approval_requests WHERE module = 'pr_quotation_mgr' AND request_key = ? AND status = 'approved'", [(string)$id])
                    ? prs_out(4, 'Quotation approved by Manager — waiting for Admin approval', 'wait', 'Admin') : prs_out(3, 'Quotation sent — waiting for Admin approval', 'wait', 'Admin'));
            }
            $n = prs_has($pdo, 'pr_quotes') ? (int)erp_val($pdo, "SELECT COUNT(*) FROM pr_quotes WHERE pr_id = ?", [$id]) : 0;
            if ($qs === 'rejected') return prs_out(3, 'Quotation rejected — change and resend', 'bad', 'Purchase team');
            return prs_out(3, 'Admin approved — waiting for quotation (' . $n . ' of 3)', 'wait', 'Purchase team');
        }

        $poNo = $po['po_number'];
        $poSt = (string)$po['status'];
        if ($poSt === 'cancelled') return prs_out(5, "{$poNo} cancelled", 'bad');
        if (in_array($poSt, ['draft', 'pending_approval'], true)) {
            $ceo = prs_has($pdo, 'approval_requests') && erp_val($pdo, "SELECT id FROM approval_requests WHERE module = 'po_ceo' AND request_key = ? AND status IN ('submitted','under_review')", [(string)$po['id']]);
            if ($poSt === 'draft') return prs_out(5, "{$poNo} created — not yet sent for approval", 'wait', 'Admin');
            return $ceo ? prs_out(6, "{$poNo} approved by Admin — waiting for CEO approval", 'wait', 'CEO')
                        : prs_out(5, "{$poNo} created — waiting for Admin PO approval", 'wait', 'Admin');
        }

        // ---- PO approved or later: goods side (furthest evidence wins)
        $prWh = prs_wh($pdo, $po['warehouse_id'] ?: $pr['warehouse_id']);
        $cons = prs_has($pdo, 'sf_consignments') ? erp_rows($pdo, "SELECT status, destination_warehouse_id, unload_warehouse_id, transport_type, courier_name, tracking_number, vehicle_number
                                                                    FROM sf_consignments WHERE po_id = ? AND status <> 'cancelled'", [$po['id']]) : [];
        if ($cons) {
            $rank = ['ready_for_loading' => 0, 'loaded' => 1, 'in_transit' => 2, 'delivered' => 3, 'received' => 4, 'qc_pending' => 4, 'qc_hold' => 5, 'qc_failed' => 5, 'qc_approved' => 6, 'stocked' => 7];
            usort($cons, fn($a, $b) => ($rank[$a['status']] ?? 0) <=> ($rank[$b['status']] ?? 0));
            $c = $cons[0];   // the least advanced consignment is the honest overall position
            $more = count($cons) > 1 ? ' (' . count($cons) . ' consignments)' : '';
            $wh = prs_wh($pdo, $c['unload_warehouse_id'] ?: $c['destination_warehouse_id']) ?: $prWh;
            switch ($c['status']) {
                case 'stocked': return prs_out(12, "Added to inventory — {$wh}" . $more, 'done');
                case 'qc_approved': return prs_out(11, "QC checked (passed) at {$wh} — adding to inventory" . $more, 'ok', 'Warehouse');
                case 'qc_hold': return prs_out(11, "QC on hold at {$wh}" . $more, 'wait', 'QC');
                case 'qc_failed': return prs_out(11, "QC failed at {$wh}" . $more, 'bad', 'QC');
                case 'received': case 'qc_pending': return prs_out(10, "Unloaded at {$wh} — waiting for quality check" . $more, 'wait', 'Executive (QC)');
                case 'delivered': return prs_out(10, "Delivered — waiting for unloading at {$wh}" . $more, 'wait', 'Warehouse');
                case 'in_transit': case 'loaded':
                    $via = trim($c['transport_type'] === 'courier' ? 'courier ' . trim(($c['courier_name'] ?: '') . ' ' . ($c['tracking_number'] ?: '')) : ($c['vehicle_number'] ? 'vehicle ' . $c['vehicle_number'] : ''));
                    return prs_out(9, 'Loaded — in transit to ' . ($wh ?: 'warehouse') . ($via ? ' by ' . $via : '') . $more, 'ok', 'Transport');
            }
            // ready_for_loading → payment position below decides the wording
        }

        // goods receipts / QC made directly (without a consignment)
        $grn = null;
        if ($f && $f['grn_id']) $grn = erp_row($pdo, "SELECT * FROM goods_receipts WHERE id = ?", [$f['grn_id']]);
        if (!$grn) $grn = erp_row($pdo, "SELECT * FROM goods_receipts WHERE po_id = ? AND status <> 'cancelled' ORDER BY (status = 'posted') DESC, id DESC LIMIT 1", [$po['id']]);
        if (!$cons && ($grn || in_array($poSt, ['partially_received', 'fully_received', 'closed'], true))) {
            $wh = ($grn ? prs_wh($pdo, $grn['warehouse_id']) : '') ?: $prWh;
            if (($grn && $grn['status'] === 'posted') || in_array($poSt, ['partially_received', 'fully_received', 'closed'], true))
                return $poSt === 'partially_received' ? prs_out(12, "Partly added to inventory — {$wh} (balance pending)", 'ok', 'Warehouse') : prs_out(12, "Added to inventory — {$wh}", 'done');
            $qc = ($f && $f['qc_id']) ? erp_row($pdo, "SELECT status FROM quality_checks WHERE id = ?", [$f['qc_id']]) : null;
            if (!$qc) $qc = erp_row($pdo, "SELECT status FROM quality_checks WHERE grn_id = ? AND status <> 'cancelled' ORDER BY id DESC LIMIT 1", [$grn['id']]);
            if ($qc && $qc['status'] === 'rejected') return prs_out(11, "QC failed at {$wh}", 'bad', 'QC');
            if ($qc && in_array($qc['status'], ['passed', 'partially_passed'], true)) return prs_out(11, 'QC checked (' . str_replace('_', ' ', $qc['status']) . ") at {$wh} — adding to inventory", 'ok', 'Warehouse');
            return prs_out(10, "Unloaded at {$wh} — waiting for quality check", 'wait', 'Executive (QC)');
        }
        if (!$cons && $f && ($f['unload_check'] ?? null)) return prs_out(10, "Unloaded at {$prWh} — goods receipt / QC pending", 'wait', 'Warehouse');

        // ---- money: accounts team
        $total = (float)$po['grand_total'];
        $paid = 0.0;
        $ids = [];
        if ($f && $f['payment_id']) $ids[] = (int)$f['payment_id'];
        $paid = (float)erp_val($pdo, "SELECT COALESCE(SUM(pp.amount),0) FROM purchase_payments pp WHERE pp.status = 'completed' AND (pp.id IN (" . ($ids ? implode(',', $ids) : '0') . ")
                                      OR pp.pinv_id IN (SELECT pi.id FROM purchase_invoices pi WHERE pi.po_id = ? AND pi.status <> 'cancelled'))", [$po['id']]);
        $isPaid = $paid > 0 && ($total <= 0 || $paid + 0.5 >= $total);
        if ($f && !$cons && $f['delivery_mode']) {
            // FIX (2 Oct 2026): courier + tracking number / own vehicle + driver and phone, and who is buying
            $via = $f['delivery_mode'] === 'courier' ? 'courier ' . trim(($f['courier_name'] ?: '') . ($f['tracking_number'] ? ' · tracking ' . $f['tracking_number'] : ''))
                                                     : 'own vehicle ' . trim(($f['vehicle_number'] ?: '') . ($f['driver_name'] ? ' · driver ' . $f['driver_name'] . ($f['driver_phone'] ? ' ' . $f['driver_phone'] : '') : ''));
            return prs_out(9, 'Loaded — in transit to ' . ($prWh ?: 'warehouse') . ' by ' . trim($via), 'ok', !empty($f['quote_submitted_by']) ? $f['quote_submitted_by'] . ' (L1) to unload' : 'Transport');
        }
        if ($isPaid) return prs_out(8, 'Paid ₹' . number_format($paid, 2) . ($f && !empty($f['payment_proof_doc_id']) ? ' · proof attached' : '') . " — " . (!empty($f['quote_submitted_by']) ? $f['quote_submitted_by'] . ' (L1) to buy and load' : 'waiting for loading') . ($cons ? ' (ready for loading)' : ''), 'ok', !empty($f['quote_submitted_by']) ? $f['quote_submitted_by'] . ' (L1)' : 'Purchase team');   // FIX (2 Oct 2026): the L1 who got the quotation buys
        if ($paid > 0) return prs_out(7, "{$poNo} approved — part paid ₹" . number_format($paid, 2) . ' of ₹' . number_format($total, 2) . ', balance waiting', 'wait', 'Accounts');
        if (!empty($f['po_checked_at'])) return prs_out(7, "{$poNo} checked by Accounts — waiting for payment", 'wait', 'Accounts');   // FIX (2 Oct 2026)
        return prs_out(7, "{$poNo} approved — sent to Accounts to check the PO", 'wait', 'Accounts');
    } catch (Throwable $e) {
        error_log('pr_stage: ' . $e->getMessage());
        return null;
    }
}
