<?php
require_once __DIR__ . '/includes/check_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports — Valluvam Admin</title>
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
                    <h1>Reports</h1>
                    <div class="adm-sub">Run a report by type and date range</div>
                </div>
            </div>

            <section class="adm-card">
                <div class="adm-card-head"><h2>Report options</h2></div>
                <div class="adm-card-body">
                    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
                        <div class="adm-field">
                            <label>Report type</label>
                            <select id="reportType" class="adm-select">
                                <option value="sales_report">Sales Report</option>
                                <option value="sales_order_report">Sales Order Report</option>
                                <option value="invoice_report">Invoice Report</option>
                                <option value="payment_report">Payment Report</option>
                                <option value="inventory_report">Inventory Report</option>
                                <option value="stock_in_report">Stock In Report</option>
                                <option value="stock_out_report">Stock Out Report</option>
                                <option value="stock_movement_report">Stock Movement Report</option>
                                <option value="warehouse_report">Warehouse Report</option>
                                <option value="customer_outstanding_report">Customer Outstanding Report</option>
                                <option value="supplier_payable_report">Supplier Payable Report</option>
                                <option value="expense_report">Expense Report</option>
                                <option value="income_report">Income Report</option>
                                <option value="asset_report">Asset Report</option>
                                <option value="waste_report">Waste Report</option>
                            </select>
                        </div>
                        <div class="adm-field">
                            <label>From</label>
                            <input type="date" id="dateFrom" class="adm-input">
                        </div>
                        <div class="adm-field">
                            <label>To</label>
                            <input type="date" id="dateTo" class="adm-input">
                        </div>
                        <button class="adm-btn adm-btn-ghost" id="quickToday">Today</button>
                        <button class="adm-btn adm-btn-ghost" id="quickWeek">This Week</button>
                        <button class="adm-btn adm-btn-ghost" id="quickMonth">This Month</button>
                        <button class="adm-btn adm-btn-ghost" id="quickYear">This Year</button>
                        <button class="adm-btn adm-btn-primary" id="runReportBtn"><i class="fas fa-play"></i> Run report</button>
                        <button class="adm-btn adm-btn-ghost" id="exportCsvBtn" style="display:none;"><i class="fas fa-download"></i> Export CSV</button>
                    </div>
                </div>
            </section>

            <section class="adm-card">
                <div class="adm-card-head"><h2 id="resultTitle">Results</h2></div>
                <div class="adm-card-body" id="reportTable">
                    <div class="adm-empty"><i class="fas fa-chart-bar"></i><p><strong>No report run yet</strong></p><p>Pick a type and date range, then click Run report.</p></div>
                </div>
            </section>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let lastRows = [];
        let lastType = '';

        const columnLabels = {
            id: 'ID', so_number: 'SO Number', invoice_number: 'Invoice Number',
            customer_name: 'Customer', customer_mobile: 'Mobile', status: 'Status',
            total_amount: 'Total Amount', order_date: 'Order Date', invoice_date: 'Invoice Date',
            created_at: 'Created', product_name: 'Product', stock: 'Stock', product_id: 'Product ID',
            quantity: 'Quantity', warehouse_id: 'Warehouse ID', reason: 'Reason', created_by: 'By',
            change_type: 'Change Type', quantity_change: 'Quantity Change', resulting_stock: 'Resulting Stock',
            name: 'Name', code: 'Code', location: 'Location', manager_name: 'Manager',
            category: 'Category', amount: 'Amount', description: 'Description', transaction_date: 'Date',
            transaction_type: 'Type', method: 'Method', reference: 'Reference',
            asset_name: 'Asset', purchase_date: 'Purchase Date', purchase_value: 'Purchase Value'
        };

        $(document).ready(function() {
            $('#runReportBtn').on('click', runReport);
            $('#exportCsvBtn').on('click', exportCsv);
            $('#quickToday').on('click', () => setRange('today'));
            $('#quickWeek').on('click', () => setRange('week'));
            $('#quickMonth').on('click', () => setRange('month'));
            $('#quickYear').on('click', () => setRange('year'));
        });

        function pad(n) { return n < 10 ? '0' + n : '' + n; }
        function toDateStr(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }

        function setRange(kind) {
            const now = new Date();
            let from, to;
            if (kind === 'today') {
                from = to = now;
            } else if (kind === 'week') {
                const day = now.getDay();
                const diffToMonday = (day === 0 ? 6 : day - 1);
                from = new Date(now); from.setDate(now.getDate() - diffToMonday);
                to = now;
            } else if (kind === 'month') {
                from = new Date(now.getFullYear(), now.getMonth(), 1);
                to = now;
            } else if (kind === 'year') {
                from = new Date(now.getFullYear(), 0, 1);
                to = now;
            }
            $('#dateFrom').val(toDateStr(from));
            $('#dateTo').val(toDateStr(to));
        }

        function runReport() {
            const type = $('#reportType').val();
            lastType = type;
            $('#reportTable').html('<div class="adm-table-wrap"><table class="adm-table"><tbody><tr><td><span class="adm-skel" style="width:100%;height:18px;"></span></td></tr></tbody></table></div>');
            $('#exportCsvBtn').hide();

            $.ajax({
                url: '../assets/db_query/admin/get_report.php',
                type: 'GET',
                data: { type: type, date_from: $('#dateFrom').val() || '', date_to: $('#dateTo').val() || '' },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        lastRows = data.rows || [];
                        displayReport(lastRows, data.note || '');
                    } else {
                        $('#reportTable').html('<div class="adm-error">' + escapeHtml(data.message || 'Could not run report.') + '</div>');
                    }
                },
                error: function() {
                    $('#reportTable').html('<div class="adm-error">Could not reach the server while running the report.</div>');
                }
            });
        }

        function displayReport(rows, note) {
            $('#resultTitle').text('Results (' + rows.length + ')');

            if (note) {
                $('#reportTable').html('<div class="adm-empty"><i class="fas fa-info-circle"></i><p><strong>' + escapeHtml(note) + '</strong></p></div>');
                $('#exportCsvBtn').hide();
                return;
            }

            if (rows.length === 0) {
                $('#reportTable').html('<div class="adm-empty"><i class="fas fa-chart-bar"></i><p><strong>No data</strong></p><p>No records found for this type and date range.</p></div>');
                $('#exportCsvBtn').hide();
                return;
            }

            const cols = Object.keys(rows[0]);
            let thead = '<tr>' + cols.map(c => `<th>${escapeHtml(columnLabels[c] || c)}</th>`).join('') + '</tr>';
            let tbody = '';
            rows.forEach(r => {
                tbody += '<tr>' + cols.map(c => `<td>${escapeHtml(r[c])}</td>`).join('') + '</tr>';
            });

            $('#reportTable').html(`<div class="adm-table-wrap"><table class="adm-table"><thead>${thead}</thead><tbody>${tbody}</tbody></table></div>`);
            $('#exportCsvBtn').show();
        }

        function exportCsv() {
            if (!lastRows.length) return;
            const cols = Object.keys(lastRows[0]);
            let csv = cols.map(csvEscape).join(',') + '\n';
            lastRows.forEach(r => {
                csv += cols.map(c => csvEscape(r[c])).join(',') + '\n';
            });
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = lastType + '_' + toDateStr(new Date()) + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        function csvEscape(val) {
            const s = String(val ?? '');
            if (/[",\n]/.test(s)) {
                return '"' + s.replace(/"/g, '""') + '"';
            }
            return s;
        }

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
        }
    </script>
</body>
</html>
