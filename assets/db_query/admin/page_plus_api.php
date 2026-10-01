<?php
// ============================================================================
// Small read-only helpers for the admin page add-ons (added 1 Oct 2026):
//   me             profile chip in the header (name, role, initials)
//   team           active team members (to assign assets)
//   assets_summary asset totals + who is handling what + latest assignments
//   waste_meta     products / warehouses for the waste form + counts + who may approve
//   audit_meta     audit-log counts for the summary cards
// Nothing is written here. Needs no new database change.
// ============================================================================
require_once __DIR__ . '/erp_helper.php';
require_once __DIR__ . '/../../../admin/includes/role_access.php';

erp_guard($pdo, null);
$action = (string)erp_input('action', 'me');
$isSuper = (int)($_SESSION['admin_role_id'] ?? 0) === 1;
$keys = $isSuper ? ['super'] : array_values(array_filter(array_merge([role_access_key((string)($_SESSION['admin_role_name'] ?? ''))], user_dash_keys($pdo, (int)($_SESSION['admin_user_id'] ?? 0)))));
$need = function (string $perm) use ($pdo) { if (!erp_can($pdo, $perm)) erp_fail('You do not have permission to do this.', 403); };

try {
    switch ($action) {
        case 'me':
            $u = null;
            try { $u = erp_row($pdo, "SELECT username, full_name, email, last_login_at FROM admin_users WHERE id = ?", [(int)($_SESSION['admin_user_id'] ?? 0)]); } catch (PDOException $e) {}
            $name = trim((string)($u['full_name'] ?? '')) ?: (string)($_SESSION['admin_full_name'] ?? $_SESSION['admin_username'] ?? 'Admin');
            $parts = preg_split('/\s+/', $name) ?: [$name];
            $ini = strtoupper(mb_substr($parts[0], 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : mb_substr($parts[0], 1, 1)));
            erp_out(['status' => 'success', 'name' => $name, 'username' => $u['username'] ?? ($_SESSION['admin_username'] ?? ''), 'email' => $u['email'] ?? null,
                     'role' => (string)($_SESSION['admin_role_name'] ?? ($isSuper ? 'Super Admin' : '')), 'initials' => $ini, 'last_login' => $u['last_login_at'] ?? null]);

        case 'team':
            $need('assets.view');
            $rows = [];
            try { $rows = erp_rows($pdo, "SELECT u.id, u.username, COALESCE(NULLIF(u.full_name,''), u.username) AS name, r.name AS role FROM admin_users u LEFT JOIN admin_roles r ON r.id = u.role_id
                                          WHERE u.status = 'active' ORDER BY name"); } catch (PDOException $e) {}
            erp_out(['status' => 'success', 'team' => $rows]);

        case 'assets_summary':
            $need('assets.view');
            $tot = erp_row($pdo, "SELECT COUNT(*) n, COALESCE(SUM(purchase_cost),0) cost, COALESCE(SUM(COALESCE(current_value, purchase_cost)),0) val,
                                         SUM(status = 'assigned') assigned, SUM(status = 'active') free, SUM(status = 'under_maintenance') maint, SUM(status IN ('damaged','lost')) bad,
                                         SUM(warranty_end_date IS NOT NULL AND warranty_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) warranty_soon
                                  FROM assets WHERE status NOT IN ('disposed','retired')", []);
            $people = erp_rows($pdo, "SELECT assigned_employee AS person, COUNT(*) n, COALESCE(SUM(COALESCE(current_value, purchase_cost)),0) val,
                                             GROUP_CONCAT(CONCAT(asset_id, ' ', asset_name) ORDER BY asset_id SEPARATOR ' · ') AS list
                                      FROM assets WHERE status = 'assigned' AND assigned_employee IS NOT NULL AND assigned_employee <> '' GROUP BY assigned_employee ORDER BY n DESC, person");
            $recent = [];
            try { $recent = erp_rows($pdo, "SELECT aa.assigned_to, aa.assigned_date, aa.returned_date, aa.notes, aa.created_by, a.id, a.asset_id, a.asset_name FROM asset_assignments aa JOIN assets a ON a.id = aa.asset_id ORDER BY aa.id DESC LIMIT 12"); } catch (PDOException $e) {}
            erp_out(['status' => 'success', 'totals' => $tot, 'people' => $people, 'recent' => $recent, 'me' => $_SESSION['admin_full_name'] ?? $_SESSION['admin_username'] ?? '',
                     'can' => ['create' => erp_can($pdo, 'assets.create'), 'edit' => erp_can($pdo, 'assets.edit'), 'assign_team' => $isSuper || (bool)array_intersect($keys, ['manager', 'admin', 'ceo'])]]);

        case 'waste_meta':
            $need('waste.view');
            $products = erp_rows($pdo, "SELECT id, product_name AS name, stock, price FROM product_details ORDER BY product_name LIMIT 2000");
            $wh = [];
            try { $wh = erp_rows($pdo, "SELECT id, name FROM warehouses ORDER BY id"); } catch (PDOException $e) {}
            $m1 = date('Y-m-01');
            $st = erp_row($pdo, "SELECT SUM(status = 'reported') pending, COALESCE(SUM(CASE WHEN status = 'reported' THEN estimated_value END),0) pending_val,
                                        SUM(status IN ('approved','processed','disposed') AND date >= ?) approved_month, COALESCE(SUM(CASE WHEN status IN ('approved','processed','disposed') AND date >= ? THEN estimated_value END),0) value_month,
                                        SUM(status = 'cancelled') rejected
                                 FROM waste_records", [$m1, $m1]);
            erp_out(['status' => 'success', 'products' => $products, 'warehouses' => $wh, 'stats' => $st, 'me' => erp_user(),
                     'can' => ['report' => erp_can($pdo, 'waste.create'), 'approve' => erp_can($pdo, 'waste.approve')]]);

        case 'audit_meta':
            $need('audit_logs.view');
            erp_out(['status' => 'success',
                     'today' => (int)erp_val($pdo, "SELECT COUNT(*) FROM audit_logs WHERE created_at >= CURDATE()"),
                     'week' => (int)erp_val($pdo, "SELECT COUNT(*) FROM audit_logs WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)"),
                     'users' => erp_rows($pdo, "SELECT COALESCE(username,'—') AS username, COUNT(*) n, MAX(created_at) last FROM audit_logs WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY username ORDER BY n DESC LIMIT 6"),
                     'approvals' => (int)erp_val($pdo, "SELECT COUNT(*) FROM audit_logs WHERE action IN ('approve','reject') AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")]);

        default:
            erp_fail('Unknown action.');
    }
} catch (Throwable $e) {
    erp_db_error($e, 'page add-ons');
}
