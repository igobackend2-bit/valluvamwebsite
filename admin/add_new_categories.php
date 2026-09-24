<?php
// ONE-TIME SCRIPT (24 Sep 2026): adds the new product categories so they appear
// in the admin Product Form "Category" dropdown.
// Admin login required. Safe to run more than once (skips categories that already exist).
// Delete this file after running it once.
require_once __DIR__ . '/includes/check_admin.php';
require_once __DIR__ . '/../assets/db_query/config.php';

$newCategories = ['Palm Jaggery', 'Seeds', 'Dal', 'Honey', 'Ghee', 'Pulses'];

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Add new categories</h2><ul>";
try {
    $check  = $pdo->prepare("SELECT id FROM product_category WHERE LOWER(category_name) = LOWER(?)");
    // thumbnali / link left empty: these are admin categories only, so they are
    // NOT shown in the homepage category slider (it only lists categories with an image).
    $insert = $pdo->prepare("INSERT INTO product_category (category_name, thumbnali, link) VALUES (?, '', '')");
    foreach ($newCategories as $name) {
        $check->execute([$name]);
        if ($check->fetch()) {
            echo "<li>" . htmlspecialchars($name) . " &mdash; already exists, skipped</li>";
        } else {
            $insert->execute([$name]);
            echo "<li>" . htmlspecialchars($name) . " &mdash; <b>added</b></li>";
        }
    }
    echo "</ul><p>Done. Open Admin &rarr; Products &rarr; New Product: the new categories are now in the Category dropdown.</p>";
    echo "<p>You can delete <code>admin/add_new_categories.php</code> now.</p>";
} catch (PDOException $e) {
    error_log('add_new_categories: ' . $e->getMessage());
    echo "</ul><p style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
