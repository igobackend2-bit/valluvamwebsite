<?php
// List accounts transactions with filters: type, category, payment_mode,
// status, date_from, date_to, q (search transaction_id/party_name/description).
// Also returns aggregate totals for the summary bar.
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../config.php';

require_permission($pdo, 'accounts.view');

$type         = trim($_GET['type'] ?? '');
$category     = trim($_GET['category'] ?? '');
$payment_mode = trim($_GET['payment_mode'] ?? '');
$status       = trim($_GET['status'] ?? '');
$date_from    = trim($_GET['date_from'] ?? '');
$date_to      = trim($_GET['date_to'] ?? '');
$q            = trim($_GET['q'] ?? '');

try {
    $sql = "SELECT * FROM accounts_transactions WHERE 1=1";
    $params = [];

    if ($type !== '') {
        $sql .= " AND type = ?";
        $params[] = $type;
    }
    if ($category !== '') {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    if ($payment_mode !== '') {
        $sql .= " AND payment_mode = ?";
        $params[] = $payment_mode;
    }
    if ($status !== '') {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    if ($date_from !== '') {
        $sql .= " AND date >= ?";
        $params[] = $date_from;
    }
    if ($date_to !== '') {
        $sql .= " AND date <= ?";
        $params[] = $date_to;
    }
    if ($q !== '') {
        $sql .= " AND (transaction_id LIKE ? OR party_name LIKE ? OR description LIKE ? OR reference_number LIKE ?)";
        $like = "%$q%";
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    }
    $sql .= " ORDER BY id DESC LIMIT 1000";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Aggregate totals (over completed transactions only, ignoring active filters
    // except we keep it simple and total whatever the current filtered set is not
    // required by the brief — compute overall completed income/expense totals).
    $totIncome = 0;
    $totExpense = 0;
    $totalCount = 0;
    $totalsStmt = $pdo->query("SELECT type, SUM(amount) AS total FROM accounts_transactions WHERE status = 'completed' GROUP BY type");
    foreach ($totalsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (in_array($row['type'], ['income', 'payment_received'])) {
            $totIncome += (float)$row['total'];
        } elseif (in_array($row['type'], ['expense', 'payment_made'])) {
            $totExpense += (float)$row['total'];
        }
    }
    $countStmt = $pdo->query("SELECT COUNT(*) FROM accounts_transactions");
    $totalCount = (int)$countStmt->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'transactions' => $transactions,
        'totals' => [
            'income' => round($totIncome, 2),
            'expense' => round($totExpense, 2),
            'net_balance' => round($totIncome - $totExpense, 2),
            'total_transactions' => $totalCount,
        ],
    ]);
} catch (PDOException $e) {
    error_log("Error listing accounts transactions: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'transactions' => [], 'totals' => ['income' => 0, 'expense' => 0, 'net_balance' => 0, 'total_transactions' => 0]]);
}
