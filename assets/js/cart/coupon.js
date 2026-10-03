// FIX (3 Oct 2026): the cart's "Have a coupon code? Apply" box now works.
// The code is checked on the server (assets/db_query/cart/coupon_query.php) against the cart; the same discount is
// used on the checkout page and when the order / online payment is created. The cart total shown here also takes
// off the standing ₹3 discount (it was listed under Discount but not subtracted from the total before).
(function ($) {
    'use strict';
    if (!$) return;
    var STANDING = 3.00, coupon = { code: null, discount: 0 };
    var $wrap = $('.v-coupon-wrap'), $input = $wrap.find('input'), $btn = $wrap.find('.v-btn-apply');
    var $msg = $('<div class="v-coupon-msg" style="font-size:.85rem;margin-top:8px"></div>').appendTo($wrap);
    var $row = $('<div class="v-summary-row" id="couponRow" style="display:none"><span>Coupon <strong id="couponCode"></strong> <a href="#" id="couponRemove" style="font-size:.8rem;margin-left:6px">remove</a></span><span class="v-discount-text" id="couponAmt"></span></div>');
    $('.v-discount-text').first().closest('.v-summary-row').after($row);
    var num = function (t) { var n = parseFloat(String(t || '').replace(/[^0-9.\-]/g, '')); return isNaN(n) ? 0 : n; };
    var busy = false;
    function total() {
        if (busy) return;
        busy = true;
        var sub = num($('#subtotal').text());
        var t = Math.max(0, sub - (sub > 0 ? STANDING : 0) - coupon.discount);
        var want = '₹' + t.toFixed(2);
        if ($('#total').text() !== want) $('#total').text(want);
        busy = false;
    }
    function show(r, quiet) {
        coupon = { code: r.code || null, discount: num(r.discount) };
        if (coupon.code) {
            $('#couponCode').text(coupon.code); $('#couponAmt').text('-₹' + coupon.discount.toFixed(2)); $row.show();
            $input.val(coupon.code); $btn.text('Applied');
        } else { $row.hide(); $btn.text('Apply'); }
        if (!quiet || r.message) $msg.text(r.message || '').css('color', r.status === 'success' && coupon.code ? '#2d6a4f' : '#c0392b');
        total();
    }
    function status(quiet) { $.getJSON('assets/db_query/cart/coupon_query.php', { action: 'status' }).done(function (r) { if (r.status === 'success') show(r, quiet); }); }
    $btn.on('click', function () {
        var code = $.trim($input.val());
        if (!code) { $msg.text('Enter a coupon code.').css('color', '#c0392b'); return; }
        $btn.prop('disabled', true);
        $.post('assets/db_query/cart/coupon_query.php', { action: 'apply', code: code }, null, 'json')
            .done(function (r) { if (r.status === 'success') show(r); else { show({ code: null, discount: 0, message: r.message, status: 'error' }); } })
            .fail(function () { $msg.text('Could not check the coupon. Please try again.').css('color', '#c0392b'); })
            .always(function () { $btn.prop('disabled', false); });
    });
    $input.on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $btn.trigger('click'); } });
    $(document).on('click', '#couponRemove', function (e) {
        e.preventDefault();
        $.post('assets/db_query/cart/coupon_query.php', { action: 'remove' }, null, 'json').done(function (r) { $input.val(''); show(r); });
    });
    // the cart script rewrites the subtotal / total when quantities change → re-check the coupon (minimum order) and the total
    var timer = null;
    var obs = new MutationObserver(function () { total(); clearTimeout(timer); timer = setTimeout(function () { if (coupon.code) status(true); }, 400); });
    ['subtotal', 'total'].forEach(function (id) { var el = document.getElementById(id); if (el) obs.observe(el, { childList: true, characterData: true, subtree: true }); });
    status(true);
})(window.jQuery);
