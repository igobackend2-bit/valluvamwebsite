<?php
// FIX (3 Oct 2026): coupons from Admin → Coupons now work on the website.
// One place decides the coupon discount, so the cart, the checkout page, COD orders and online (Razorpay) payments
// always charge the same amount. The applied code is kept in the session ($_SESSION['coupon_code']) and is checked
// again every time (active, not expired, minimum order, max uses) — a code that stops being valid is simply dropped.

if (!function_exists('vp_coupon_check')) {
    function vp_coupon_cols(PDO $pdo): array {
        static $cols = null;
        if ($cols === null) {
            try { $cols = array_column($pdo->query("SHOW COLUMNS FROM coupons")->fetchAll(PDO::FETCH_ASSOC), 'Field'); }
            catch (PDOException $e) { $cols = []; }
        }
        return $cols;
    }
    /** [ok, message, discount, coupon row] for this code on a cart subtotal. */
    function vp_coupon_check(PDO $pdo, string $code, float $subtotal): array {
        $code = strtoupper(trim($code));
        if ($code === '') return [false, 'Enter a coupon code.', 0.0, null];
        $cols = vp_coupon_cols($pdo);
        if (!$cols) return [false, 'Coupons are not available right now.', 0.0, null];
        $st = $pdo->prepare("SELECT * FROM coupons WHERE UPPER(code) = ? LIMIT 1");
        $st->execute([$code]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        if (!$c) return [false, 'This coupon code is not valid.', 0.0, null];
        if (in_array('is_active', $cols, true) && (int)$c['is_active'] !== 1) return [false, 'This coupon is not active.', 0.0, null];
        if (!empty($c['expires_at']) && substr((string)$c['expires_at'], 0, 10) < date('Y-m-d')) return [false, 'This coupon has expired.', 0.0, null];
        if ((float)($c['min_order_amount'] ?? 0) > $subtotal + 0.0001) return [false, 'Add items worth ₹' . number_format((float)$c['min_order_amount'] - $subtotal, 2) . ' more to use this coupon (minimum order ₹' . number_format((float)$c['min_order_amount'], 2) . ').', 0.0, null];
        if ($c['max_uses'] !== null && $c['max_uses'] !== '' && in_array('used_count', $cols, true) && (int)$c['used_count'] >= (int)$c['max_uses']) return [false, 'This coupon has been fully used.', 0.0, null];
        $val = (float)$c['discount_value'];
        $disc = ($c['discount_type'] ?? 'percent') === 'flat' ? $val : $subtotal * $val / 100;
        $disc = round(max(0, min($disc, $subtotal)), 2);
        if ($disc <= 0) return [false, 'This coupon gives no discount on this order.', 0.0, null];
        return [true, 'Coupon ' . $c['code'] . ' applied — you save ₹' . number_format($disc, 2) . '.', $disc, $c];
    }
    /** The coupon in the session, checked again for this subtotal: [code|null, discount, message]. Drops it when no longer valid. */
    function vp_coupon_session(PDO $pdo, float $subtotal): array {
        $code = (string)($_SESSION['coupon_code'] ?? '');
        if ($code === '') return [null, 0.0, ''];
        [$ok, $msg, $disc, $c] = vp_coupon_check($pdo, $code, $subtotal);
        if (!$ok) { unset($_SESSION['coupon_code']); return [null, 0.0, $msg]; }
        return [$c['code'], $disc, $msg];
    }
    /** After an order is placed: count the use, forget the code. */
    function vp_coupon_used(PDO $pdo, ?string $code): void {
        unset($_SESSION['coupon_code']);
        if (!$code) return;
        try { if (in_array('used_count', vp_coupon_cols($pdo), true)) $pdo->prepare("UPDATE coupons SET used_count = COALESCE(used_count, 0) + 1 WHERE UPPER(code) = ?")->execute([strtoupper($code)]); }
        catch (PDOException $e) { error_log('[coupon used] ' . $e->getMessage()); }
    }
    /** Save the coupon on the order when the orders table has the columns (coupon_migration.sql). */
    function vp_coupon_save_on_order(PDO $pdo, int $orderId, ?string $code, float $disc): void {
        if (!$code || $orderId <= 0) return;
        try { $pdo->prepare("UPDATE orders SET coupon_code = ?, coupon_discount = ? WHERE id = ?")->execute([$code, $disc, $orderId]); }
        catch (PDOException $e) { error_log('[coupon on order] ' . $e->getMessage()); }
    }
}
