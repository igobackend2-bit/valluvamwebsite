<?php
// ONE-TIME SCRIPT (24 Sep 2026): moves specific existing products into the correct
// new category (they were added before Palm Jaggery / Seeds / Honey / Ghee existed
// as categories, so they got filed under the closest old category at the time).
// Admin login required. Safe to run more than once (only updates rows that still
// have the old category; already-fixed rows are skipped automatically).
// Delete this file after running it once.
require_once __DIR__ . '/includes/check_admin.php';
require_once __DIR__ . '/../assets/db_query/config.php';

// [product id => new category] — matched by product name containing the category
// word, verified by hand against the live catalog on 24 Sep 2026.
$moves = [
    185 => 'Palm Jaggery', // PALM JAGGERY-KARUPATTI (500g), was: Dryfruits
    184 => 'Palm Jaggery', // PALM JAGGERY-KARUPATTI (250g), was: Dryfruits
    215 => 'Honey',        // VALLUVAM MULTIFLORAL HONEY (250g), was: Oils
    213 => 'Honey',        // VALLUVAM MULTIFLORAL HONEY (500g), was: Oils
    196 => 'Ghee',         // COW GHEE (250g), was: Oils
    188 => 'Ghee',         // COW GHEE (500g), was: Oils
    98  => 'Seeds',        // SUNFLOWER SEEDS, was: Nuts
    96  => 'Seeds',        // PUMPKIN SEEDS, was: Nuts
    82  => 'Seeds',        // CHIA SEED, was: Nuts
    23  => 'Seeds',        // FLAX SEEDS, was: Nuts
];

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Fix product categories</h2><ul>";
try {
    $select = $pdo->prepare("SELECT product_name, category FROM product_details WHERE id = ?");
    $update = $pdo->prepare("UPDATE product_details SET category = ? WHERE id = ?");
    foreach ($moves as $id => $newCategory) {
        $select->execute([$id]);
        $row = $select->fetch();
        if (!$row) {
            echo "<li>id $id &mdash; not found, skipped</li>";
            continue;
        }
        if (strcasecmp($row['category'], $newCategory) === 0) {
            echo "<li>" . htmlspecialchars($row['product_name']) . " &mdash; already {$newCategory}, skipped</li>";
            continue;
        }
        $update->execute([$newCategory, $id]);
        echo "<li>" . htmlspecialchars($row['product_name']) . " &mdash; moved from " . htmlspecialchars($row['category']) . " to <b>{$newCategory}</b></li>";
    }
    echo "</ul><p>Done. Check the category pages (e.g. /honey, /ghee, /palm-jaggery, /seeds) to confirm.</p>";
    echo "<p>You can delete <code>admin/fix_product_categories.php</code> now.</p>";
} catch (PDOException $e) {
    error_log('fix_product_categories: ' . $e->getMessage());
    echo "</ul><p style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
