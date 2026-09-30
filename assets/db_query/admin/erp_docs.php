<?php
// ============================================================================
// Document attachments for purchase / expense records (added 30 Sep 2026)
//   GET  ?action=list&entity_type=..&entity_id=..
//   POST action=upload (multipart: file, entity_type, entity_id, doc_type)
//   GET  ?action=download&id=..   (streams the file to a logged-in admin)
//   POST action=delete&id=..
// Files live in assets/erp_docs/ (web access denied by its .htaccess).
// ============================================================================
require_once __DIR__ . '/erp_helper.php';

const ERP_DOC_ENTITIES = ['purchase_request', 'purchase_order', 'grn', 'purchase_invoice', 'purchase_payment', 'purchase_return', 'sales_return', 'expense', 'supplier', 'repack', 'stock_adjustment'];
const ERP_DOC_TYPES = ['vendor_invoice', 'purchase_receipt', 'delivery_challan', 'eway_bill', 'qc_document', 'payment_proof', 'credit_note', 'expense_receipt', 'other'];
const ERP_DOC_EXT = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
                     'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xls' => 'application/vnd.ms-excel',
                     'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'csv' => 'text/csv'];
const ERP_DOC_MAX = 10 * 1024 * 1024;

$action = (string) erp_input('action', 'list');
erp_guard($pdo, $action === 'upload' || $action === 'delete' ? null : 'purchase.view');
$root = realpath(__DIR__ . '/../../erp_docs');
if (!$root) erp_fail('The document folder assets/erp_docs is missing on the server.');

try {
    switch ($action) {
        case 'list':
            $type = (string)erp_input('entity_type');
            if (!in_array($type, ERP_DOC_ENTITIES, true)) erp_fail('Invalid record type.');
            erp_out(['status' => 'success', 'rows' => erp_rows($pdo, "SELECT id, doc_type, original_name, mime_type, file_size, uploaded_by, created_at FROM erp_documents
                                                                     WHERE entity_type = ? AND entity_id = ? ORDER BY id DESC", [$type, (int)erp_input('entity_id')])]);

        case 'upload':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') erp_fail('Invalid request method.');
            $type = (string)erp_input('entity_type');
            $eid = (int)erp_input('entity_id');
            if (!in_array($type, ERP_DOC_ENTITIES, true) || $eid <= 0) erp_fail('Save the record first, then attach documents.');
            require_permission($pdo, $type === 'expense' ? 'expense.manage' : 'purchase.view');
            $docType = in_array(erp_input('doc_type'), ERP_DOC_TYPES, true) ? erp_input('doc_type') : 'other';
            $f = $_FILES['file'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) erp_fail($f && in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'The file is too large.' : 'Choose a file to upload.');
            if ($f['size'] > ERP_DOC_MAX) erp_fail('Files must be 10 MB or smaller.');
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (!isset(ERP_DOC_EXT[$ext])) erp_fail('Allowed files: PDF, JPG, PNG, WEBP, Excel, Word, CSV.');
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) && @getimagesize($f['tmp_name']) === false) erp_fail('That image file is not valid.');
            if ($ext === 'pdf' && file_get_contents($f['tmp_name'], false, null, 0, 5) !== '%PDF-') erp_fail('That PDF file is not valid.');
            $sub = date('Y/m');
            if (!is_dir("$root/$sub") && !mkdir("$root/$sub", 0755, true)) erp_fail('Could not create the document folder on the server.');
            $stored = $sub . '/' . bin2hex(random_bytes(12)) . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'], "$root/$stored")) erp_fail('Could not save the file on the server.');
            $orig = mb_substr(preg_replace('/[^\w.\- ()]+/u', '_', basename($f['name'])), 0, 200);
            $pdo->prepare("INSERT INTO erp_documents (entity_type, entity_id, doc_type, file_name, original_name, mime_type, file_size, uploaded_by) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$type, $eid, $docType, $stored, $orig, ERP_DOC_EXT[$ext], (int)$f['size'], erp_user()]);
            $id = (int)$pdo->lastInsertId();
            log_audit($pdo, 'upload', 'erp_documents', $id, null, ['entity' => "$type#$eid", 'doc_type' => $docType, 'file' => $orig]);
            erp_out(['status' => 'success', 'id' => $id, 'message' => 'Document attached.']);

        case 'download':
            $d = erp_row($pdo, "SELECT * FROM erp_documents WHERE id = ?", [(int)erp_input('id')]);
            $path = $d ? realpath("$root/{$d['file_name']}") : false;
            if (!$d || !$path || strpos($path, $root) !== 0 || !is_file($path)) { http_response_code(404); erp_fail('Document not found.'); }
            header_remove('Content-Type');
            header('Content-Type: ' . ($d['mime_type'] ?: 'application/octet-stream'));
            header('Content-Length: ' . filesize($path));
            header('X-Content-Type-Options: nosniff');
            $inline = in_array($d['mime_type'], ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true);
            header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $d['original_name']) . '"');
            readfile($path);
            exit;

        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') erp_fail('Invalid request method.');
            $d = erp_row($pdo, "SELECT * FROM erp_documents WHERE id = ?", [(int)erp_input('id')]);
            if (!$d) erp_fail('Document not found.');
            if ((int)($_SESSION['admin_role_id'] ?? 0) !== 1 && $d['uploaded_by'] !== erp_user()) erp_fail('Only the person who uploaded it (or a Super Admin) can remove this document.');
            $pdo->prepare("DELETE FROM erp_documents WHERE id = ?")->execute([$d['id']]);
            $path = realpath("$root/{$d['file_name']}");
            if ($path && strpos($path, $root) === 0) @unlink($path);
            log_audit($pdo, 'delete', 'erp_documents', $d['id'], $d, null);
            erp_out(['status' => 'success', 'message' => 'Document removed.']);
    }
    erp_fail('Unknown action.');
} catch (Throwable $e) {
    erp_db_error($e, 'documents');
}
