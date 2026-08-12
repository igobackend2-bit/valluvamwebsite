$(document).ready(function () {
  const currencySymbol = '₹'; // change to '' if you want no symbol
  // Wishlist add handler removed - now handled globally in header.js to prevent duplicate execution

  // load wishlist
  function loadWishlist() {
    $.ajax({
      url: 'assets/db_query/wishlist/wishlist_query.php?action=get',
      type: 'GET',
      dataType: 'json',
      success: function (res) {
        if (res.status === 'not_logged_in') {
          $('#loginNotice').show();
          $('#wishlistTable').html('');
          $('#grandTotal').text(currencySymbol + '0.00');
          return;
        }
        if (res.status !== 'success') {
          SwalHelper.ecommerce.wishlistError(res.message || 'Unable to load your wishlist. Please refresh the page.');
          return;
        }

        let html = '';
        res.wishlist.forEach(function (item) {
          const price = parseFloat(item.price || 0);
          const qty = parseInt(item.quantity_number || 1);
          const total = (price * qty).toFixed(2);
          // fallback image style if image_url missing
          const imageUrl = item.image_url || '/images/default.jpg';

          html += `
          <tr class="text-center" data-wishlist-id="${item.id}" data-price="${price}">
            <td class="product-remove">
              <a href="#" class="remove-wishlist" data-id="${item.id}">
                <span class="ion-ios-close"></span>
              </a>
            </td>
          
            <td class="image-prod">
              <div class="img" style="background-image:url('${escapeHtml(imageUrl)}'); width:120px; height:80px; background-size:cover; background-position:center;"></div>
            </td>
          
            <td class="product-name">
              <h3>${escapeHtml(item.product_name)}</h3>
              <p>${escapeHtml(item.category || '')}</p>
            </td>
          
            <td class="price">${currencySymbol}${price.toFixed(2)}</td>
          
            <td class="quantity">
                 <span>${item.quantity}</span>
            </td>
          
            <td class="total">${currencySymbol}${price.toFixed(2)}</td>
          
            <td>
              <button class="btn btn-sm btn-success add-to-cart" data-id="${item.product_id}">
                Add to Cart
              </button>
            </td>
          </tr>
          `;

        });

        $('#wishlistTable').html(html);
        recalcGrandTotal();
      },
      error: function () {
        SwalHelper.ecommerce.networkError();
      }
    });
  }

  // recompute grand total from DOM rows
  function recalcGrandTotal() {
    let grand = 0;
    $('#wishlistTable tr').each(function () {
      const price = parseFloat($(this).data('price') || 0);
      let qty = parseInt($(this).find('input.quantity').val() || 1);
      if (!qty || qty < 1) qty = 1;
      grand += price * qty;
      // update this row's total cell
      $(this).find('.total').text(currencySymbol + (price * qty).toFixed(2));
    });
    $('#grandTotal').text(currencySymbol + grand.toFixed(2));
  }

  // quantity change handler (client-side update only)
  $(document).on('input change', 'input.quantity', function () {
    const val = parseInt($(this).val() || 1);
    if (!val || val < 1) $(this).val(1);
    recalcGrandTotal();
  });

  // remove (delete from DB then reload)
  $(document).on('click', '.remove-wishlist', function (e) {
    e.preventDefault();
    const wishId = $(this).data('id');
    
    SwalHelper.confirm('Remove from Wishlist?', 'Are you sure you want to remove this item from your wishlist?', 'Yes, Remove', 'Cancel').then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: 'assets/db_query/wishlist/wishlist_query.php?action=delete',
          type: 'POST',
          data: { wishlist_id: wishId },
          dataType: 'json',
          success: function (res) {
            if (res.status === 'success') {
              SwalHelper.success('Removed!', 'Item has been removed from your wishlist.', 1500);
              // remove row and recompute
              $(`tr[data-wishlist-id="${wishId}"]`).remove();
              recalcGrandTotal();
            } else if (res.status === 'not_logged_in') {
              SwalHelper.ecommerce.notLoggedIn();
            } else {
              SwalHelper.error('Remove Failed', res.message || 'Unable to remove item. Please try again.');
            }
          },
          error: function() {
            SwalHelper.ecommerce.networkError();
          }
        });
      }
    });
  });

  // add to cart handler (example - adjust URL)
  // $(document).on('click', '.add-to-cart', function () {
  //   const productId = $(this).data('id');
  //   // send to your cart API - must implement server-side cart API
  //   $.ajax({
  //     url: 'assets/db_query/cart/cart_query.php?action=add',
  //     type: 'POST',
  //     data: { product_id: productId, quantity: 1 },
  //     dataType: 'json',
  //     success: function (res) {
  //      
  //     },
  //     error: function () {
  //       alert('AJAX error adding to cart');
  //     }
  //   });
  // });

  // small helper
  function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // initial load
  loadWishlist();
});
