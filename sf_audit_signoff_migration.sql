-- ============================================================================
-- Monthly audit: auditor (external) QC check + report + name + digital signature (2 Oct 2026)
-- Additive only: creates ONE new table. Nothing is changed or deleted. Safe to run more than once.
-- ============================================================================
CREATE TABLE IF NOT EXISTS sf_audit_signoffs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_id INT NOT NULL,
    auditor_type ENUM('external','internal') NOT NULL DEFAULT 'external',
    auditor_name VARCHAR(150) NOT NULL,
    auditor_org VARCHAR(150) NULL,
    auditor_designation VARCHAR(100) NULL,
    auditor_phone VARCHAR(20) NULL,
    qc_result ENUM('satisfactory','needs_improvement','unsatisfactory') NOT NULL,
    stock_findings TEXT NULL,
    qc_findings TEXT NOT NULL,
    recommendations TEXT NULL,
    signature_doc_id INT NOT NULL,
    report_doc_id INT NULL,
    signed_at DATETIME NOT NULL,
    entered_by VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sf_aso_audit (audit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
