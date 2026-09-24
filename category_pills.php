<?php
// Shared category filter pills for shop.php and every category page.
// Previously each page had its own hand-typed copy and they drifted apart
// (7 vs 12 categories, "Dryfruits" vs "Dry Fruits", Pulses/Ghee missing).
// Keep this list in the same order as the header's Shop mega-menu.
$pillCategories = [
    'shop.php'         => 'All',
    'dryfruits.php'    => 'Dry Fruits',
    'nuts.php'         => 'Nuts',
    'spices.php'       => 'Spices',
    'oils.php'         => 'Oils',
    'millets.php'      => 'Millets',
    'rice.php'         => 'Rice',
    'combo.php'        => 'Combos',
    'palm-jaggery.php' => 'Palm Jaggery',
    'seeds.php'        => 'Seeds',
    'dal.php'          => 'Dal',
    'honey.php'        => 'Honey',
    'ghee.php'         => 'Ghee',
    'pulses.php'       => 'Pulses',
];
$pillCurrent = basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($pillCurrent === 'milets.php') {
    $pillCurrent = 'millets.php';
}
?>
<ul class="product-category">
<?php foreach ($pillCategories as $pillHref => $pillLabel): ?>
    <li><a href="<?= $pillHref ?>"<?= $pillHref === $pillCurrent ? ' class="active" aria-current="page"' : '' ?>><?= $pillLabel ?></a></li>
<?php endforeach; ?>
</ul>
<script>
// On phones the pills are one swipeable row; bring the current category into view.
(function () {
    var list = document.currentScript.previousElementSibling;
    var active = list && list.querySelector('a.active');
    if (active && list.scrollWidth > list.clientWidth) {
        var li = active.parentNode.getBoundingClientRect(), box = list.getBoundingClientRect();
        list.scrollLeft += (li.left - box.left) - (box.width - li.width) / 2;
    }
})();
</script>
