-- ============================================================================
-- Courier / transport charges with proof → Manager → Admin → Accounts check + pay (2 Oct 2026)
-- Additive only: ONE new table + TWO approval rules. Nothing is changed or deleted.
-- Safe to run more than once.
-- ============================================================================
CREATE TABLE IF NOT EXISTS pf_transport_charges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    charge_number VARCHAR(40) NOT NULL,
    pr_id INT NOT NULL,
    po_id INT NULL,
    charge_type ENUM('courier','transport','loading','unloading','other') NOT NULL DEFAULT 'courier',
    amount DECIMAL(14,2) NOT NULL,
    payee_name VARCHAR(150) NOT NULL,
    payee_phone VARCHAR(20) NULL,
    account_holder VARCHAR(150) NULL,
    bank_name VARCHAR(150) NULL,
    account_number VARCHAR(40) NULL,
    ifsc VARCHAR(15) NULL,
    upi_id VARCHAR(80) NULL,
    notes VARCHAR(500) NULL,
    proof_doc_id INT NOT NULL,
    status ENUM('manager_pending','admin_pending','approved','checked','paid','rejected','cancelled') NOT NULL DEFAULT 'manager_pending',
    raised_by VARCHAR(100) NOT NULL, raised_at DATETIME NOT NULL,
    manager_by VARCHAR(100) NULL, manager_at DATETIME NULL,
    admin_by VARCHAR(100) NULL, admin_at DATETIME NULL,
    rejected_by VARCHAR(100) NULL, rejected_at DATETIME NULL, reject_reason VARCHAR(255) NULL,
    checked_by VARCHAR(100) NULL, checked_at DATETIME NULL,
    paid_by VARCHAR(100) NULL, paid_at DATETIME NULL, paid_date DATE NULL,
    pay_mode VARCHAR(20) NULL, pay_reference VARCHAR(100) NULL, pay_proof_doc_id INT NULL, accounts_transaction_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pf_tc_number (charge_number), KEY idx_pf_tc_pr (pr_id), KEY idx_pf_tc_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO approval_policies (module, label, inherent, enabled, min_amount, approver_perm)
SELECT 'transport_charge_mgr', 'Courier / transport charge — Manager approval', 1, 1, 0.00, 'purchase.manager_approve'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM approval_policies WHERE module = 'transport_charge_mgr');
INSERT INTO approval_policies (module, label, inherent, enabled, min_amount, approver_perm)
SELECT 'transport_charge', 'Courier / transport charge — Admin approval', 1, 1, 0.00, 'purchase.backend_approve'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM approval_policies WHERE module = 'transport_charge');
