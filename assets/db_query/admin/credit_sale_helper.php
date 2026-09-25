<?php
// Shared helper for the Credit Sale feature (replaces the old Delivery
// Challans menu entry — customers who take goods now and pay later).
//
// Defensive schema creation only (CREATE TABLE IF NOT EXISTS) — same
// pattern used elsewhere in this codebase (e.g. get_inventory_overview.php
// adding columns). Never DROPs or ALTERs an existing column. The matching
// CREATE TABLE statements are also handed to the admin to run directly, so
// this is just a safety net in case that step is skipped.
function ensure_credit_sale_tables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS credit_sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        credit_number VARCHAR(30) NOT NULL UNIQUE,
        sale_date DATE NOT NULL,
        customer_name VARCHAR(150) NOT NULL,
        customer_mobile VARCHAR(20) NULL,
        customer_address TEXT NULL,
        warehouse_id INT NOT NULL DEFAULT 1,
        subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
        total_tax DECIMAL(12,2) NOT NULL DEFAULT 0,
        grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
        amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
        status ENUM('outstanding','partially_paid','paid') NOT NULL DEFAULT 'outstanding',
        stock_deducted TINYINT(1) NOT NULL DEFAULT 0,
        notes TEXT NULL,
        created_by VARCHAR(100) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS credit_sale_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        credit_sale_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity DECIMAL(10,2) NOT NULL,
        rate DECIMAL(10,2) NOT NULL DEFAULT 0,
        discount DECIMAL(10,2) NOT NULL DEFAULT 0,
        tax DECIMAL(5,2) NOT NULL DEFAULT 0,
        line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
        FOREIGN KEY (credit_sale_id) REFERENCES credit_sales(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS credit_sale_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        credit_sale_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        payment_mode VARCHAR(30) NULL,
        notes VARCHAR(255) NULL,
        created_by VARCHAR(100) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (credit_sale_id) REFERENCES credit_sales(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
