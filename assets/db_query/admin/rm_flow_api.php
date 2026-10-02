<?php
// ============================================================================
// Raw materials — how much came in and how much was repacked (added 2 Oct 2026). Read only.
//   per raw material: received (posted goods receipts, accepted qty), used in repacking,
//   packs made, process loss · and the latest repacking jobs with their packs.
// ============================================================================
require_once __DIR__ . '/erp_helper.php';
erp_guard($pdo, null);
if (!erp_can($pdo, 'raw_materials.manage') && !erp_can($pdo, 'inventory.view')) erp_fail('You do not have permission to do this.', 403);
try {
    $sum = [];
    foreach (erp_rows($pdo, "SELECT i.item_id, SUM(i.accepted_qty) AS received, COUNT(DISTINCT g.id) AS receipts, MAX(g.received_date) AS last_received
                             FROM goods_receipt_items i JOIN goods_receipts g ON g.id = i.grn_id WHERE i.item_type = 'raw_material' AND g.status = 'posted' GROUP BY i.item_id") as $r)
        $sum[(int)$r['item_id']] = ['received' => (float)$r['received'], 'receipts' => (int)$r['receipts'], 'last_received' => $r['last_received']];
    $rp = [];
    try {
        foreach (erp_rows($pdo, "SELECT j.raw_material_id AS id, SUM(j.consumed_qty) AS used, SUM(j.process_loss_qty) AS loss, COUNT(*) AS jobs, MAX(j.repack_date) AS last_repack,
                                        SUM((SELECT COALESCE(SUM(o.packs),0) FROM repack_job_outputs o WHERE o.repack_id = j.id)) AS packs
                                 FROM repack_jobs j WHERE j.status = 'posted' GROUP BY j.raw_material_id") as $r)
            $rp[(int)$r['id']] = ['used' => (float)$r['used'], 'loss' => (float)$r['loss'], 'jobs' => (int)$r['jobs'], 'packs' => (int)$r['packs'], 'last_repack' => $r['last_repack']];
        $jobs = erp_rows($pdo, "SELECT j.id, j.repack_number, j.repack_date, j.consumed_qty, j.process_loss_qty, j.output_qty_total, j.material_cost, j.packing_cost, j.created_by, m.name AS material, m.unit, w.name AS warehouse_name
                                FROM repack_jobs j JOIN raw_materials m ON m.id = j.raw_material_id LEFT JOIN warehouses w ON w.id = j.warehouse_id WHERE j.status = 'posted' ORDER BY j.repack_date DESC, j.id DESC LIMIT 30");
        foreach ($jobs as &$j) {
            $j['outputs'] = erp_rows($pdo, "SELECT o.packs, o.pack_size_qty, p.product_name, p.quantity AS pack FROM repack_job_outputs o LEFT JOIN product_details p ON p.id = o.product_id WHERE o.repack_id = ?", [$j['id']]);
        }
        unset($j);
    } catch (PDOException $e) { $jobs = []; }
    $out = [];
    foreach (erp_rows($pdo, "SELECT id, name, unit, stock FROM raw_materials") as $m) {
        $a = $sum[(int)$m['id']] ?? ['received' => 0, 'receipts' => 0, 'last_received' => null];
        $b = $rp[(int)$m['id']] ?? ['used' => 0, 'loss' => 0, 'jobs' => 0, 'packs' => 0, 'last_repack' => null];
        $out[(int)$m['id']] = $a + $b;
    }
    erp_out(['status' => 'success', 'by_material' => $out, 'jobs' => $jobs]);
} catch (Throwable $e) {
    erp_db_error($e, 'raw material flow');
}
