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
        { key: 'ordered', label: 'Ordered', icon: 'fa-shopping-cart' },
        { key: 'packed', label: 'Packed', icon: 'fa-box' },
        { key: 'couriered', label: 'Out for Delivery', icon: 'fa-truck' },
        { key: 'delivered', label: 'Delivered', icon: 'fa-check-circle' }
    ];
    
    const statusIndex = stages.findIndex(s => s.key === status);
    const currentIndex = statusIndex >= 0 ? statusIndex : 0;
    
    let progressHtml = '<div class="order-status-tracker">';
    progressHtml += '<div class="status-steps-container">';
    
    stages.forEach((stage, index) => {
        const isCompleted = index < currentIndex;
        const isActive = index === currentIndex;
        const isPending = index > currentIndex;
        
        let stepClass = 'status-step';
        if (isCompleted) stepClass += ' completed';
        if (isActive) stepClass += ' active';
        if (isPending) stepClass += ' pending';
        
        let iconClass = '';
        if (isCompleted) iconClass = 'text-success';
        else if (isActive) iconClass = 'text-primary';
        else iconClass = 'text-muted';
        
        progressHtml += `
            <div class="${stepClass}">
                <div class="status-icon-wrapper">
                    <div class="status-icon ${iconClass}">
                        <i class="fa ${stage.icon}"></i>
                    </div>
                </div>
                <div class="status-label">
                    <span class="${isCompleted || isActive ? 'text-dark' : 'text-muted'}"><strong>${stage.label}</strong></span>
                </div>
            </div>
        `;
        
        // Add connecting line after each step (except the last one)
        if (index < stages.length - 1) {
            const lineClass = isCompleted ? 'status-line completed' : 'status-line';
            progressHtml += `<div class="${lineClass}"></div>`;
        }
    });
    
    progressHtml += '</div></div>';
    
    // Add CSS for status progress
    if (!$('#orderTrackingStyles').length) {
        $('head').append(`
            <style id="orderTrackingStyles">
                .order-status-tracker {
                    margin: 30px 0;
                    padding: 20px;
                    background: #f8f9fa;
                    border-radius: 8px;
                }
                .status-steps-container {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    position: relative;
                }
                .status-step {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    position: relative;
                    z-index: 2;
                }
                .status-icon-wrapper {
                    width: 50px;
                    height: 50px;
                    border-radius: 50%;
                    background: #fff;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border: 3px solid #e0e0e0;
                    margin-bottom: 10px;
                    transition: all 0.3s;
                }
                .status-step.completed .status-icon-wrapper {
                    border-color: #82AE46;
                    background: #82AE46;
                }
                .status-step.active .status-icon-wrapper {
                    border-color: #82AE46;
                    background: #fff;
                    box-shadow: 0 0 0 4px rgba(130, 174, 70, 0.2);
                }
                .status-step.completed .status-icon {
                    color: #fff !important;
                }
                .status-step.active .status-icon {
                    color: #82AE46 !important;
                    font-size: 20px;
                }
                .status-icon {
                    font-size: 18px;
                    transition: all 0.3s;
                }
                .status-label {
                    font-size: 12px;
                    text-align: center;
                }
                .status-line {
                    flex: 1;
                    height: 3px;
                    background: #e0e0e0;
                    margin: 0 10px;
                    margin-top: -35px;
                    position: relative;
                    z-index: 1;
                }
                .status-line.completed {
                    background: #82AE46;
                }
                .order-card {
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    transition: box-shadow 0.3s;
                }
                .order-card:hover {
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                }
                @media (max-width: 768px) {
                    .status-steps-container {
                        flex-wrap: wrap;
                    }
                    .status-step {
                        flex: 0 0 50%;
                        margin-bottom: 20px;
                    }
                    .status-line {
                        display: none;
                    }
                }
            </style>
        `);
    }
    
    return progressHtml;
}

