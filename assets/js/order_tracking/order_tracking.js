$(document).ready(function () {
    loadOrders();
});

function loadOrders() {
    $.ajax({
        url: 'assets/db_query/order/get_user_orders.php',
        type: 'GET',
        dataType: 'json',
        success: function (data) {
            if (data.status === 'success') {
                displayOrders(data.orders);
            } else {
                SwalHelper.ecommerce.serverError(data.message || 'Failed to load orders');
            }
        },
        error: function () {
            SwalHelper.close();
            SwalHelper.ecommerce.networkError();
        }
    });
}

function displayOrders(orders) {
    const container = $('#ordersContainer');

    if (orders.length === 0) {
        container.html(`
            <div class="text-center py-5">
                <i class="fa fa-shopping-bag" style="font-size: 64px; color: #ccc;"></i>
                <h3 class="mt-3">No Orders Yet</h3>
                <p class="text-muted">You haven't placed any orders yet.</p>
                <a href="shop.php" class="btn btn-primary mt-3">Start Shopping</a>
            </div>
        `);
        return;
    }

    let html = '';

    orders.forEach(order => {
        const orderDate = new Date(order.created_at).toLocaleDateString('en-IN', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });

        const status = order.order_status || 'ordered';
        const statusConfig = getStatusConfig(status);

        html += `
            <div class="order-card mb-4" style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; background: #fff;">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h4 class="mb-1">Order #${order.receipt}</h4>
                        <p class="text-muted mb-0"><small>Placed on ${orderDate}</small></p>
                    </div>
                    <div class="col-md-6 text-right">
                        <span class="badge badge-${statusConfig.badgeClass}" style="font-size: 14px; padding: 8px 16px;">
                            ${statusConfig.label}
                        </span>
                    </div>
                </div>
                
                <!-- Order Status Progress -->
                <div class="order-status-progress mb-4">
                    ${getStatusProgress(status)}
                </div>
                
                <!-- Order Items -->
                <div class="cart-list">
                    <table class="table">
                        <thead class="thead-primary">
                            <tr class="text-center">
                                <th>Image</th>
                                <th>Product Name</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${order.items.map(item => `
                                <tr class="text-center">
                                    <td class="image-prod">
                                        <img src="assets/uploads/${item.image}" alt="${item.product_name}" 
                                             style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px;">
                                    </td>
                                    <td class="product-name">
                                        <h3>${item.product_name}</h3>
                                        <p class="text-muted">${item.category || ''}</p>
                                    </td>
                                    <td class="price">₹${parseFloat(item.price).toFixed(2)}</td>
                                    <td class="quantity">${item.quantity}</td>
                                    <td class="total">₹${(parseFloat(item.price) * parseInt(item.quantity)).toFixed(2)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                
                <!-- Order Summary -->
                <div class="row mt-3">
                    <div class="col-md-6">
                        <h5>Delivery Address</h5>
                        <p class="mb-0">
                            ${order.first_name} ${order.last_name}<br>
                            ${order.street_address}${order.apartment ? ', ' + order.apartment : ''}<br>
                            ${order.city}, ${order.state} - ${order.postcode}<br>
                            Phone: ${order.phone}
                        </p>
                    </div>
                    <div class="col-md-6 text-right">
                        <div class="order-summary">
                            <p class="d-flex justify-content-end">
                                <span class="mr-3"><strong>Payment Method:</strong></span>
                                <span>${order.payment_method === 'COD' ? 'Cash on Delivery' : 'Razorpay'}</span>
                            </p>
                            <p class="d-flex justify-content-end">
                                <span class="mr-3"><strong>Payment Status:</strong></span>
                                <span class="badge badge-${order.payment_status === 'paid' ? 'success' : 'warning'}">
                                    ${order.payment_status === 'paid' ? 'Paid' : 'Pending'}
                                </span>
                            </p>
                            <hr>
                            <p class="d-flex justify-content-end total-price">
                                <span class="mr-3"><strong>Total Amount:</strong></span>
                                <span><strong>₹${parseFloat(order.amount).toFixed(2)}</strong></span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });

    container.html(html);
}

function getStatusConfig(status) {
    const configs = {
        'ordered': { label: 'Ordered', badgeClass: 'primary' },
        'packed': { label: 'Packed', badgeClass: 'info' },
        'couriered': { label: 'Out for Delivery', badgeClass: 'warning' },
        'delivered': { label: 'Delivered', badgeClass: 'success' }
    };
    return configs[status] || configs['ordered'];
}

function getStatusProgress(status) {
    const stages = [
        {
            key: 'ordered',
            label: 'Ordered',
            icon: `
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M7 4h-2l-1 2h2l3.6 7.59-1.35 2.44c-.16.28-.25.61-.25.97 0 1.1.9 2 2 2h12v-2h-12l1.1-2h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1h-14.31l-.94-2zm1 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/>
          </svg>`
        },
        {
            key: 'packed',
            label: 'Packed',
            icon: `
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M21 16v-8l-9-5-9 5v8l9 5 9-5zm-9-3l-6-3.33v-4l6 3.33 6-3.33v4l-6 3.33z"/>
          </svg>`
        },
        {
            key: 'couriered',
            label: 'Out for Delivery',
            icon: `
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M3 3h13v10h5l-2-4h-3v-6h-13v14h2a3 3 0 006 0h6a3 3 0 006 0h1v-4l-3-6h-4v-4h-13v14h1"/>
          </svg>`
        },
        {
            key: 'delivered',
            label: 'Delivered',
            icon: `
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M9 16.17l-3.88-3.88-1.41 1.41 5.29 5.29 12-12-1.41-1.41z"/>
          </svg>`
        }
    ];

    const statusIndex = stages.findIndex(s => s.key === status);
    const currentIndex = statusIndex >= 0 ? statusIndex : 0;

    let html = `<div class="order-status-tracker"><div class="status-steps-container">`;

    stages.forEach((stage, index) => {
        const isCompleted = index < currentIndex;
        const isActive = index === currentIndex;

        let stepClass = 'status-step';
        if (isCompleted) stepClass += ' completed';
        else if (isActive) stepClass += ' active';
        else stepClass += ' pending';

        html += `
        <div class="${stepClass}">
          <div class="status-icon-wrapper">
            <div class="status-icon">
              ${stage.icon}
            </div>
          </div>
          <div class="status-label">
            <span class="${(isCompleted || isActive) ? 'text-dark' : 'text-muted'}"><strong>${stage.label}</strong></span>
          </div>
        </div>
      `;

        if (index < stages.length - 1) {
            html += `<div class="status-line ${isCompleted ? 'completed' : ''}"></div>`;
        }
    });

    html += `</div></div>`;

    // Inject CSS once
    if (!document.getElementById('orderTrackingStyles')) {
        const style = document.createElement('style');
        style.id = 'orderTrackingStyles';
        style.textContent = `
        .order-status-tracker{margin:30px 0;padding:20px;background:#f8f9fa;border-radius:8px;}
        .status-steps-container{display:flex;align-items:center;justify-content:space-between;position:relative;}
        .status-step{flex:1;display:flex;flex-direction:column;align-items:center;position:relative;z-index:2;}
        .status-icon-wrapper{width:50px;height:50px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;border:3px solid #e0e0e0;margin-bottom:10px;}
        .status-step.completed .status-icon-wrapper{border-color:#82AE46;background:#82AE46;}
        .status-step.active .status-icon-wrapper{border-color:#82AE46;background:#fff;box-shadow:0 0 0 4px rgba(130,174,70,.2);}
        .status-line{flex:1;height:3px;background:#e0e0e0;margin:0 10px;margin-top:-35px;z-index:1;}
        .status-line.completed{background:#82AE46;}
  
        /* ✅ critical mobile-safe SVG rules */
        .status-icon{display:flex;align-items:center;justify-content:center;color:#9e9e9e;}
        .status-icon svg{width:22px;height:22px;display:block;fill:currentColor;}
        .status-step.active .status-icon{color:#82AE46;}
        .status-step.completed .status-icon{color:#ffffff;}
  
        @media (max-width:768px){
          .status-steps-container{flex-wrap:wrap}
          .status-step{flex:0 0 50%;margin-bottom:20px}
          .status-line{display:none}
        }
      `;
        document.head.appendChild(style);
    }

    return html;
}

