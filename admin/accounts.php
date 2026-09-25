<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts — Valluvam Admin</title>
    <link rel="icon" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <a class="adm-skip-link" href="#adm-main-content">Skip to content</a>
    <div class="adm-shell">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>
        <main class="adm-main" id="adm-main-content">
            <div class="adm-topbar">
                <div>
                    <h1>Accounts</h1>
                    <div class="adm-sub">Income, expenses and payments</div>
                </div>
                <button class="adm-btn adm-btn-primary" id="addTxnBtn"><i class="fas fa-plus"></i> New transaction</button>
            </div>

            <section class="adm-card">
                <div class="adm-card-body">
                    <div style="display:flex;flex-wrap:wrap;gap:12px;">
                        <div class="adm-stat"><div class="adm-stat-label">Total income</div><div class="adm-stat-value" id="statIncome">—</div></div>
                        <div class="adm-stat"><div class="adm-stat-label">Total expense</div><div class="adm-stat-value" id="statExpense">—</div></div>
                        <div class="adm-stat"><div class="adm-stat-label">Net balance</div><div class="adm-stat-value" id="statNet">—</div></div>
                        <div class="adm-stat"><div class="adm-stat-label">Total transactions</div><div class="adm-stat-value" id="statCount">—</div></div>
                    </div>
                    <div class="adm-sub" style="margin-top:6px;">
                        "Net balance" is completed income minus completed expense recorded in this module only.
                        "Total transactions" is a plain count — this build does not track receivables/payables aging, so no "Outstanding" figure is shown here.
                    </div>
                </div>
            </section>

            <section class="adm-card">
                <div class="adm-card-head">
                    <h2>Filters</h2>
                </div>
                <div class="adm-card-body">
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                        <button class="adm-btn adm-btn-ghost" id="viewAllBtn">All</button>
                        <button class="adm-btn adm-btn-ghost" id="viewIncomeBtn">Income report</button>
                        <button class="adm-btn adm-btn-ghost" id="viewExpenseBtn">Expense report</button>
                    </div>
                    <div class="adm-field" style="display:flex;flex-wrap:wrap;gap:10px;">
                        <input type="text" id="fltSearch" class="adm-input" placeholder="Search transaction ID / party / description" style="max-width:260px;">
                        <input type="text" id="fltCategory" class="adm-input" placeholder="Category" style="max-width:160px;">
                        <select id="fltPaymentMode" class="adm-select" style="max-width:160px;">
                            <option value="">All payment modes</option>
                            <option value="cash">Cash</option>
                            <option value="upi">UPI</option>
                            <option value="bank_transfer">Bank transfer</option>
                            <option value="card">Card</option>
                            <option value="cheque">Cheque</option>
                            <option value="other">Other</option>
                        </select>
                        <select id="fltStatus" class="adm-select" style="max-width:160px;">
                            <option value="">All statuses</option>
                            <option value="completed">Completed</option>
                            <option value="pending">Pending</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <input type="date" id="fltDateFrom" class="adm-input" style="max-width:160px;">
                        <input type="date" id="fltDateTo" class="adm-input" style="max-width:160px;">
                        <button class="adm-btn adm-btn-ghost" id="fltApplyBtn"><i class="fas fa-filter"></i> Apply</button>
                        <button class="adm-btn adm-btn-ghost" id="fltClearBtn"><i class="fas fa-xmark"></i> Clear</button>
                    </div>
                </div>
            </section>

            <section class="adm-card">
                <div class="adm-card-head"><h2 id="listHeading">All transactions</h2></div>
                <div class="adm-card-body" id="txnTable">
                    <div class="adm-table-wrap"><table class="adm-table"><tbody>
                        <tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr>
                    </tbody></table></div>
                </div>
            </section>
        </main>
    </div>

    <datalist id="categorySuggestions">
        <option value="Sales"><option value="Rent"><option value="Utilities"><option value="Salaries">
        <option value="Supplier Payment"><option value="Transport"><option value="Maintenance">
        <option value="Office Supplies"><option value="Taxes"><option value="Other">
    </datalist>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let currentTypeFilter = '';

        $(document).ready(function() {
            loadTransactions();
            $('#addTxnBtn').on('click', function() { editTransaction(null); });
            $('#fltApplyBtn').on('click', loadTransactions);
            $('#fltClearBtn').on('click', function() {
                $('#fltSearch').val(''); $('#fltCategory').val(''); $('#fltPaymentMode').val('');
                $('#fltStatus').val(''); $('#fltDateFrom').val(''); $('#fltDateTo').val('');
                currentTypeFilter = '';
                $('#listHeading').text('All transactions');
                loadTransactions();
            });
            $('#viewAllBtn').on('click', function() { currentTypeFilter = ''; $('#listHeading').text('All transactions'); loadTransactions(); });
            $('#viewIncomeBtn').on('click', function() { currentTypeFilter = 'income'; $('#listHeading').text('Income report'); loadTransactions(); });
            $('#viewExpenseBtn').on('click', function() { currentTypeFilter = 'expense'; $('#listHeading').text('Expense report'); loadTransactions(); });
        });

        function loadTransactions() {
            $.ajax({
                url: '../assets/db_query/admin/get_accounts_transactions.php',
                type: 'GET',
                data: {
                    q: $('#fltSearch').val(),
                    category: $('#fltCategory').val(),
                    payment_mode: $('#fltPaymentMode').val(),
                    status: $('#fltStatus').val(),
                    date_from: $('#fltDateFrom').val(),
                    date_to: $('#fltDateTo').val(),
                    type: currentTypeFilter
                },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        displayTotals(data.totals);
                        displayTransactions(data.transactions);
                    } else {
                        $('#txnTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not load transactions.') + '</div>');
                    }
                },
                error: function() {
                    $('#txnTable').html('<div class="adm-error">Could not reach the server while loading transactions.</div>');
                }
            });
        }

        function displayTotals(totals) {
            $('#statIncome').text('₹' + Number(totals.income).toFixed(2));
            $('#statExpense').text('₹' + Number(totals.expense).toFixed(2));
            $('#statNet').text('₹' + Number(totals.net_balance).toFixed(2));
            $('#statCount').text(totals.total_transactions);
        }

        function typeBadge(type) {
            const map = {
                income: 'is-green', payment_received: 'is-green',
                expense: 'is-danger', payment_made: 'is-danger',
                refund: 'is-amber', adjustment: 'is-neutral'
            };
            const cls = map[type] || 'is-neutral';
            const label = type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
            return `<span class="adm-badge ${cls}">${label}</span>`;
        }

        function statusBadge(status) {
            const map = { completed: 'is-green', pending: 'is-amber', cancelled: 'is-neutral' };
            const cls = map[status] || 'is-neutral';
            return `<span class="adm-badge ${cls}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
        }

        function displayTransactions(transactions) {
            if (transactions.length === 0) {
                $('#txnTable').html('<div class="adm-empty"><i class="fas fa-file-invoice-dollar"></i><p><strong>No transactions yet</strong></p><p>Record one to get started.</p></div>');
                return;
            }

            let rows = '';
            transactions.forEach(t => {
                rows += `<tr>
                    <td class="adm-cell-title">${escapeHtml(t.transaction_id)}
                        <div class="adm-cell-sub">${formatDate(t.date)}</div></td>
                    <td>${typeBadge(t.type)}</td>
                    <td>${escapeHtml(t.category)}</td>
                    <td>${t.party_name ? escapeHtml(t.party_name) : '<span class="adm-cell-sub">—</span>'}</td>
                    <td class="adm-money">₹${parseFloat(t.amount).toFixed(2)}</td>
                    <td>${escapeHtml(t.payment_mode.replace(/_/g,' '))}</td>
                    <td>${statusBadge(t.status)}</td>
                    <td>
                        ${t.status === 'pending' ? `<button class="adm-icon-btn edit-txn" data-txn='${JSON.stringify(t).replace(/'/g, "&#39;")}' title="Edit"><i class="fas fa-pen"></i></button>` : ''}
                        ${t.status !== 'cancelled' ? `<button class="adm-icon-btn is-danger cancel-txn" data-id="${t.id}" data-code="${escapeHtml(t.transaction_id)}" title="Cancel"><i class="fas fa-ban"></i></button>` : ''}
                    </td>
                </tr>`;
            });

            $('#txnTable').html(`<div class="adm-table-wrap"><table class="adm-table">
                <thead><tr><th>Transaction</th><th>Type</th><th>Category</th><th>Party</th><th>Amount</th><th>Mode</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`);

            $('.edit-txn').on('click', function() { editTransaction($(this).data('txn')); });
            $('.cancel-txn').on('click', function() { cancelTransaction($(this).data('id'), $(this).data('code')); });
        }

        function editTransaction(txn) {
            Swal.fire({
                title: txn ? `Edit ${txn.transaction_id}` : 'New transaction',
                width: 600,
                html: `
                    <input id="swal-date" type="date" class="swal2-input" value="${txn ? txn.date.substring(0,10) : new Date().toISOString().substring(0,10)}">
                    <select id="swal-type" class="swal2-input">
                        <option value="income" ${!txn || txn.type === 'income' ? 'selected' : ''}>Income</option>
                        <option value="expense" ${txn && txn.type === 'expense' ? 'selected' : ''}>Expense</option>
                        <option value="payment_received" ${txn && txn.type === 'payment_received' ? 'selected' : ''}>Payment received</option>
                        <option value="payment_made" ${txn && txn.type === 'payment_made' ? 'selected' : ''}>Payment made</option>
                        <option value="refund" ${txn && txn.type === 'refund' ? 'selected' : ''}>Refund</option>
                        <option value="adjustment" ${txn && txn.type === 'adjustment' ? 'selected' : ''}>Adjustment</option>
                    </select>
                    <input id="swal-category" class="swal2-input" placeholder="Category" list="categorySuggestions" value="${txn ? escapeHtml(txn.category) : ''}">
                    <input id="swal-amount" type="number" step="0.01" class="swal2-input" placeholder="Amount" value="${txn ? txn.amount : ''}">
                    <select id="swal-payment-mode" class="swal2-input">
                        <option value="cash" ${!txn || txn.payment_mode === 'cash' ? 'selected' : ''}>Cash</option>
                        <option value="upi" ${txn && txn.payment_mode === 'upi' ? 'selected' : ''}>UPI</option>
                        <option value="bank_transfer" ${txn && txn.payment_mode === 'bank_transfer' ? 'selected' : ''}>Bank transfer</option>
                        <option value="card" ${txn && txn.payment_mode === 'card' ? 'selected' : ''}>Card</option>
                        <option value="cheque" ${txn && txn.payment_mode === 'cheque' ? 'selected' : ''}>Cheque</option>
                        <option value="other" ${txn && txn.payment_mode === 'other' ? 'selected' : ''}>Other</option>
                    </select>
                    <input id="swal-account" class="swal2-input" placeholder="Account (e.g. Cash, Main Bank Account)" value="${txn && txn.account ? escapeHtml(txn.account) : ''}">
                    <input id="swal-party" class="swal2-input" placeholder="Party name (customer/supplier, optional)" value="${txn && txn.party_name ? escapeHtml(txn.party_name) : ''}">
                    <input id="swal-ref-type" class="swal2-input" placeholder="Reference type (optional, e.g. sales_order)" value="${txn && txn.reference_type ? escapeHtml(txn.reference_type) : ''}">
                    <input id="swal-ref-number" class="swal2-input" placeholder="Reference number (optional)" value="${txn && txn.reference_number ? escapeHtml(txn.reference_number) : ''}">
                    <input id="swal-description" class="swal2-input" placeholder="Description (optional)" value="${txn && txn.description ? escapeHtml(txn.description) : ''}">
                    <select id="swal-status" class="swal2-input">
                        <option value="completed" ${!txn || txn.status === 'completed' ? 'selected' : ''}>Completed</option>
                        <option value="pending" ${txn && txn.status === 'pending' ? 'selected' : ''}>Pending</option>
                    </select>
                `,
                confirmButtonText: txn ? 'Save' : 'Create',
                confirmButtonColor: '#1c5034',
                showCancelButton: true,
                cancelButtonColor: '#6b6459',
                preConfirm: () => {
                    const date = $('#swal-date').val();
                    const category = $('#swal-category').val().trim();
                    const amount = $('#swal-amount').val();
                    if (!date || !category || !amount || parseFloat(amount) <= 0) {
                        Swal.showValidationMessage('Date, category and a positive amount are required');
                        return false;
                    }
                    return {
                        id: txn ? txn.id : null,
                        date: date,
                        type: $('#swal-type').val(),
                        category: category,
                        amount: amount,
                        payment_mode: $('#swal-payment-mode').val(),
                        account: $('#swal-account').val().trim(),
                        party_name: $('#swal-party').val().trim(),
                        reference_type: $('#swal-ref-type').val().trim(),
                        reference_number: $('#swal-ref-number').val().trim(),
                        description: $('#swal-description').val().trim(),
                        status: $('#swal-status').val()
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) saveTransaction(result.value);
            });
        }

        function saveTransaction(data) {
            $.ajax({
                url: '../assets/db_query/admin/save_accounts_transaction.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({ title: 'Saved', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                        loadTransactions();
                    } else {
                        Swal.fire({ title: 'Could not save', text: response.message || 'The transaction was not saved.', icon: 'error', confirmButtonColor: '#1c5034' });
                    }
                },
                error: function() {
                    Swal.fire({ title: 'Could not save', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                }
            });
        }

        function cancelTransaction(id, code) {
            Swal.fire({
                title: 'Cancel this transaction?',
                text: `"${code}" will be marked as cancelled (not deleted).`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#a8442f',
                cancelButtonColor: '#6b6459',
                confirmButtonText: 'Cancel transaction'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../assets/db_query/admin/cancel_accounts_transaction.php',
                        type: 'POST',
                        data: { id: id },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({ title: 'Cancelled', icon: 'success', confirmButtonColor: '#1c5034', timer: 1200, showConfirmButton: false });
                                loadTransactions();
                            } else {
                                Swal.fire({ title: 'Could not cancel', text: response.message || '', icon: 'error', confirmButtonColor: '#1c5034' });
                            }
                        },
                        error: function() {
                            Swal.fire({ title: 'Could not cancel', text: 'The server did not respond.', icon: 'error', confirmButtonColor: '#1c5034' });
                        }
                    });
                }
            });
        }

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
        }

        function formatDate(dateString) {
            if (!dateString) return '—';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-IN');
        }
    </script>
</body>
</html>
