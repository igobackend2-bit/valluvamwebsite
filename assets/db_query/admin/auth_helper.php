<?php
// Shared session + permission helper for all admin JSON endpoints.
// Include this (not the old inline session check) at the top of every
// assets/db_query/admin/*.php endpoint going forward.
//
// Usage:
//   require_once __DIR__ . '/auth_helper.php';
//   require_once __DIR__ . '/../config.php';
//   require_admin_session();               // any logged-in admin
//   require_permission($pdo, 'inventory.adjust'); // + specific permission
//   log_audit($pdo, 'update', 'inventory', $productId, $old, $new);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_admin_session() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
}

/**
 * Checks the logged-in admin's role has $permKey. Exits with a JSON 403
 * if not. Super Admin (role_id 1) always passes.
 */
function require_permission(PDO $pdo, string $permKey) {
    require_admin_session();

    // Backward-compat: sessions created before the multi-user upgrade have
    // no role_id yet (shouldn't happen after login.php is redeployed, but
    // fail safe rather than fatal-erroring old sessions).
    if (!isset($_SESSION['admin_role_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Session expired — please log in again.']);
        exit;
    }

    if ((int)$_SESSION['admin_role_id'] === 1) {
        return; // Super Admin
    }

    try {
        $stmt = $pdo->prepare("SELECT 1 FROM admin_role_permissions WHERE role_id = ? AND perm_key = ?");
        $stmt->execute([$_SESSION['admin_role_id'], $permKey]);
        if (!$stmt->fetchColumn()) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission to do this.']);
            exit;
        }
    } catch (PDOException $e) {
        // If admin_role_permissions doesn't exist yet (foundation SQL not run),
        // fail open for Super Admin only, closed for everyone else, rather than
        // fatal-erroring every admin page.
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Permission system not set up yet. Run the foundation migration.']);
        exit;
    }
}

/**
 * Records an audit log entry. Never throws — a logging failure must not
 * block the actual admin action.
 */
function log_audit(PDO $pdo, string $action, string $module, $recordId = null, $oldValue = null, $newValue = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (admin_user_id, username, action, module, record_id, old_value, new_value, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $_SESSION['admin_user_id'] ?? null,
            $_SESSION['admin_username'] ?? null,
            $action,
            $module,
            $recordId !== null ? (string)$recordId : null,
            $oldValue !== null ? (is_string($oldValue) ? $oldValue : json_encode($oldValue)) : null,
            $newValue !== null ? (is_string($newValue) ? $newValue : json_encode($newValue)) : null,
        ]);
    } catch (PDOException $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }
}

/**
 * Generates the next number in a PREFIX-YYYY-NNNNNN sequence using the
 * document_sequences table, inside the caller's existing transaction when
 * one is open. Safe under concurrency via SELECT ... FOR UPDATE.
 */
function next_document_number(PDO $pdo, string $docType, string $prefix): string {
    $year = (int)date('Y');
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT last_number, year FROM document_sequences WHERE doc_type = ? FOR UPDATE");
        $stmt->execute([$docType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $next = 1;
            $ins = $pdo->prepare("INSERT INTO document_sequences (doc_type, year, last_number) VALUES (?, ?, ?)");
            $ins->execute([$docType, $year, $next]);
        } elseif ((int)$row['year'] !== $year) {
            $next = 1;
            $upd = $pdo->prepare("UPDATE document_sequences SET year = ?, last_number = ? WHERE doc_type = ?");
            $upd->execute([$year, $next, $docType]);
        } else {
            $next = (int)$row['last_number'] + 1;
            $upd = $pdo->prepare("UPDATE document_sequences SET last_number = ? WHERE doc_type = ?");
            $upd->execute([$next, $docType]);
        }

        if ($ownTransaction) $pdo->commit();
        return sprintf('%s-%d-%06d', $prefix, $year, $next);
    } catch (Exception $e) {
        if ($ownTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
