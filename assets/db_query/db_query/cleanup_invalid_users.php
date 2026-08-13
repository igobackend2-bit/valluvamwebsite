<?php
/**
 * Cleanup Script for Invalid User Entries
 * 
 * This script removes user entries that have empty username, email, or phone_number
 * Run this script once to clean up existing invalid entries.
 * 
 * WARNING: This will permanently delete invalid user records.
 * Make sure to backup your database before running this script.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>User Cleanup Script</h2>";
echo "<p>This script will find and optionally delete users with empty username, email, or phone_number.</p>";

try {
    // First, find all invalid users
    $stmt = $pdo->query("
        SELECT id, username, email, phone_number, created_at 
        FROM users 
        WHERE username IS NULL OR username = '' 
           OR email IS NULL OR email = '' 
           OR phone_number IS NULL OR phone_number = ''
        ORDER BY id
    ");
    
    $invalidUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = count($invalidUsers);
    
    echo "<h3>Found {$count} invalid user entries:</h3>";
    
    if ($count > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Phone</th><th>Created At</th></tr>";
        
        foreach ($invalidUsers as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['username'] ?? 'NULL/EMPTY') . "</td>";
            echo "<td>" . htmlspecialchars($user['email'] ?? 'NULL/EMPTY') . "</td>";
            echo "<td>" . htmlspecialchars($user['phone_number'] ?? 'NULL/EMPTY') . "</td>";
            echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check if delete action is requested
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
            // Delete invalid users
            $deleteStmt = $pdo->prepare("
                DELETE FROM users 
                WHERE username IS NULL OR username = '' 
                   OR email IS NULL OR email = '' 
                   OR phone_number IS NULL OR phone_number = ''
            ");
            
            $deleteStmt->execute();
            $deletedCount = $deleteStmt->rowCount();
            
            echo "<h3 style='color: green;'>✓ Successfully deleted {$deletedCount} invalid user entries!</h3>";
            echo "<p><a href='cleanup_invalid_users.php'>Refresh to see updated count</a></p>";
        } else {
            echo "<br/><h3>To delete these entries, click the button below:</h3>";
            echo "<p style='color: red;'><strong>WARNING: This action cannot be undone!</strong></p>";
            echo "<a href='?action=delete&confirm=yes' style='background: red; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>DELETE ALL INVALID USERS</a>";
        }
    } else {
        echo "<p style='color: green;'><strong>✓ No invalid users found. Your database is clean!</strong></p>";
    }
    
    // Show total user count
    $totalStmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<br/><p><strong>Total users in database: {$total}</strong></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

