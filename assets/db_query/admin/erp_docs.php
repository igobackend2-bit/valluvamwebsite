<?php
// ============================================================================
// Central document system (first release 30 Sep 2026, v2 1 Oct 2026)
//   GET  ?action=list&entity_type=..&entity_id=..[&related=1]  documents of a record (+ its linked records)
//   POST action=upload (multipart: file, entity_type, entity_id | entity_number, category, description, replaces_id)
//   GET  ?action=download&id=..        streams the file (authenticated + permission checked)
//   POST action=archive|delete&id=..   archive (files are never hard-deleted; "delete" = archive)
//   POST action=restore&id=..
//   GET  ?action=all&...filters        All Documents page (server-side paging)
//   GET  ?action=history&id=..         every version of a document
//   GET  ?action=meta                  entity types + categories
// Files live in assets/erp_docs/YYYY/MM/<random>.<ext> (web access denied by .htaccess);
// stored names are random, the original name is kept only for display.
// ============================================================================
require_once __DIR__ . '/erp_ext.php';

const ERP_DOC_EXT = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
                     'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xls' => 'application/vnd.ms-excel',
                     'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'doc' => 'application/msword', 'csv' => 'text/csv'];

$action = (string)erp_input('action', 'list');
erpx_guard($pdo, null);
$root = realpath(__DIR__ . '/../../erp_docs');
if (!$root) erp_fail('The document folder assets/erp_docs is missing on the server.');
$ENT = doc_entities();
$CAT = doc_categories();
$maxBytes = max(1, min(50, (int)erp_setting($pdo, 'erp_doc_max_mb', 10))) * 1024 * 1024;

/** May the current admin see documents of this entity type? */
function doc_can_see(PDO $pdo, string $type): bool {
    $ent = doc_entities();
    if (!isset($ent[$type])) return false;
    if (!erp_can($pdo, 'documents.view') && !erp_can($pdo, 'purchase.view')) return false;
    return erp_can($pdo, $ent[$type][2]) || erp_can($pdo, 'documents.archive');
}
function doc_rows(PDO $pdo, string $where, array $p, string $limit = ''): array {
    $rows = erp_rows($pdo, "SELECT d.id, d.entity_type, d.entity_id, d.doc_type, d.original_name, d.mime_type, d.file_size, d.uploaded_by, d.created_at,
                                   COALESCE(m.category, '') AS category, m.description, COALESCE(m.version, 1) AS version, COALESCE(m.root_document_id, d.id) AS root_id,
                                   COALESCE(m.status, 'active') AS status, m.archived_by, m.archived_at, m.archive_reason
                            FROM erp_documents d LEFT JOIN erp_document_meta m ON m.document_id = d.id WHERE {$where} ORDER BY d.id DESC {$limit}", $p);
    foreach ($rows as &$r) if ($r['category'] === '') $r['category'] = doc_type_category($r['doc_type']);
    unset($r);
    return $rows;
}

try {
    switch ($action) {
        case 'meta':
            $types = [];
            foreach ($ENT as $k => $e) if (doc_can_see($pdo, $k)) $types[] = ['key' => $k, 'label' => $e[0]];
            erp_out(['status' => 'success', 'entity_types' => $types, 'categories' => $CAT, 'max_mb' => $maxBytes / 1048576,
                     'can_upload' => erp_can($pdo, 'documents.upload'), 'can_archive' => erp_can($pdo, 'documents.archive'), 'extensions' => array_keys(ERP_DOC_EXT)]);

        case 'list':
            $type = (string)erp_input('entity_type');
            $eid = (int)erp_input('entity_id');
            if (!isset($ENT[$type])) erp_fail('Invalid record type.');
            if (!doc_can_see($pdo, $type)) erp_fail('You do not have permission to see these documents.', 403);
            $showArchived = erp_input('archived') === '1';
            $own = doc_rows($pdo, "d.entity_type = ? AND d.entity_id = ?" . ($showArchived ? '' : " AND COALESCE(m.status,'active') = 'active'"), [$type, $eid]);
            $related = [];
            if (erp_input('related', '1') === '1') {
                foreach (doc_related($pdo, $type, $eid) as [$t, $i]) {
                    if ($t === $type && $i === $eid) continue;
                    if (!doc_can_see($pdo, $t)) continue;
                    foreach (doc_rows($pdo, "d.entity_type = ? AND d.entity_id = ? AND COALESCE(m.status,'active') = 'active'", [$t, $i]) as $d) {
                        $d['from_label'] = $ENT[$t][0] . ' ' . doc_entity_label($pdo, $t, $i);
                        $d['from_link'] = $ENT[$t][1] . ($t === 'company' ? '' : $i);
                        $related[] = $d;
                    }
                }
            }
            erp_out(['status' => 'success', 'rows' => $own, 'related' => $related, 'can_upload' => erp_can($pdo, 'documents.upload'), 'can_archive' => erp_can($pdo, 'documents.archive'), 'categories' => $CAT]);

        case 'upload':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') erp_fail('Invalid request method.');
            if (!erp_can($pdo, 'documents.upload')) erp_fail('You do not have permission to upload documents.', 403);
            $type = (string)erp_input('entity_type');
            if (!isset($ENT[$type])) erp_fail('Choose what the document belongs to.');
            if (!doc_can_see($pdo, $type)) erp_fail('You do not have permission for this record.', 403);
            $eid = (int)erp_input('entity_id', 0);
            if (!$eid && trim((string)erp_input('entity_number', '')) !== '') {
                $eid = doc_entity_find($pdo, $type, (string)erp_input('entity_number'));
                if ($eid === null) erp_fail('No ' . strtolower($ENT[$type][0]) . ' found with that number.');
            }
            if ($type !== 'company' && $eid <= 0) erp_fail('Save the record first, then attach documents.');
            if ($type !== 'company' && str_starts_with(doc_entity_label($pdo, $type, $eid), '#')) erp_fail('That record does not exist.');
            $cat = (string)erp_input('category', '');
            if (!isset($CAT[$cat])) $cat = isset($_POST['doc_type']) ? doc_type_category((string)$_POST['doc_type']) : 'OTHER';
            $replaces = (int)erp_input('replaces_id', 0);
            $prev = $replaces ? erp_row($pdo, "SELECT d.*, COALESCE(m.version,1) AS version, COALESCE(m.root_document_id, d.id) AS root_id FROM erp_documents d LEFT JOIN erp_document_meta m ON m.document_id = d.id WHERE d.id = ?", [$replaces]) : null;
            if ($replaces && (!$prev || $prev['entity_type'] !== $type || (int)$prev['entity_id'] !== $eid)) erp_fail('The document to replace belongs to another record.');
            $f = $_FILES['file'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) erp_fail($f && in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'The file is too large for the server.' : 'Choose a file to upload.');
            if ($f['size'] <= 0) erp_fail('The file is empty.');
            if ($f['size'] > $maxBytes) erp_fail('Files must be ' . ($maxBytes / 1048576) . ' MB or smaller.');
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (!isset(ERP_DOC_EXT[$ext])) erp_fail('Allowed files: PDF, JPG, JPEG, PNG, WEBP, Excel (XLSX/XLS), Word (DOCX/DOC), CSV.');
            // content must match the extension (never trust the name)
            $head = (string)file_get_contents($f['tmp_name'], false, null, 0, 8);
            $okContent = true;
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) $okContent = @getimagesize($f['tmp_name']) !== false;
            elseif ($ext === 'pdf') $okContent = substr($head, 0, 5) === '%PDF-';
            elseif (in_array($ext, ['xlsx', 'docx'], true)) $okContent = substr($head, 0, 2) === 'PK';
            elseif (in_array($ext, ['xls', 'doc'], true)) $okContent = $head === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
            elseif ($ext === 'csv') { $sample = (string)file_get_contents($f['tmp_name'], false, null, 0, 4096); $okContent = strpos($sample, "\0") === false; }
            if (!$okContent) erp_fail('The file content does not match its type (.' . $ext . ').');
            $sub = date('Y/m');
            if (!is_dir("$root/$sub") && !mkdir("$root/$sub", 0755, true)) erp_fail('Could not create the document folder on the server.');
            $stored = $sub . '/' . bin2hex(random_bytes(12)) . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'], "$root/$stored")) erp_fail('Could not save the file on the server.');
            $orig = mb_substr(preg_replace('/[^\w.\- ()]+/u', '_', basename((string)$f['name'])), 0, 200);
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO erp_documents (entity_type, entity_id, doc_type, file_name, original_name, mime_type, file_size, uploaded_by) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$type, $eid, doc_category_type($cat), $stored, $orig, ERP_DOC_EXT[$ext], (int)$f['size'], erp_user()]);
            $id = (int)$pdo->lastInsertId();
            $version = $prev ? (int)$prev['version'] + 1 : 1;
            $rootId = $prev ? (int)$prev['root_id'] : $id;
            $pdo->prepare("INSERT INTO erp_document_meta (document_id, category, description, version, root_document_id, status) VALUES (?,?,?,?,?, 'active')")
                ->execute([$id, $cat, mb_substr(trim((string)erp_input('description', '')), 0, 255) ?: null, $version, $rootId]);
            if ($prev) {   // older version is kept, marked archived
                $pdo->prepare("INSERT INTO erp_document_meta (document_id, category, version, root_document_id, status, archived_by, archived_at, archive_reason) VALUES (?,?,?,?, 'archived', ?, NOW(), ?)
                               ON DUPLICATE KEY UPDATE status = 'archived', archived_by = VALUES(archived_by), archived_at = NOW(), archive_reason = VALUES(archive_reason)")
                    ->execute([$prev['id'], doc_type_category($prev['doc_type']), $prev['version'], $rootId, erp_user(), "Replaced by version {$version}"]);
            }
            $pdo->commit();
            log_audit($pdo, 'upload', 'erp_documents', $id, $prev ? ['replaces' => $prev['id']] : null, ['entity' => "$type#$eid", 'category' => $cat, 'file' => $orig, 'version' => $version]);
            erp_out(['status' => 'success', 'id' => $id, 'version' => $version, 'message' => $prev ? "New version {$version} uploaded." : 'Document attached.']);

        case 'download':
            $d = erp_row($pdo, "SELECT * FROM erp_documents WHERE id = ?", [(int)erp_input('id')]);
            if (!$d) { http_response_code(404); erp_fail('Document not found.'); }
            if (!doc_can_see($pdo, $d['entity_type'])) { http_response_code(403); erp_fail('You do not have permission to open this document.'); }
            $path = realpath("$root/{$d['file_name']}");
            if (!$path || strpos($path, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) { http_response_code(404); erp_fail('The file is missing on the server.'); }
            header_remove('Content-Type');
            header('Content-Type: ' . ($d['mime_type'] ?: 'application/octet-stream'));
            header('Content-Length: ' . filesize($path));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store');
            $inline = erp_input('dl') !== '1' && in_array($d['mime_type'], ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true);
            header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', $d['original_name']) . '"');
            readfile($path);
            exit;

        case 'delete':
        case 'archive':
        case 'restore':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') erp_fail('Invalid request method.');
            $d = erp_row($pdo, "SELECT d.*, m.status AS meta_status, m.category, m.version, m.root_document_id FROM erp_documents d LEFT JOIN erp_document_meta m ON m.document_id = d.id WHERE d.id = ?", [(int)erp_input('id')]);
            if (!$d) erp_fail('Document not found.');
            if (!doc_can_see($pdo, $d['entity_type'])) erp_fail('You do not have permission for this document.', 403);
            $restore = $action === 'restore';
            if (!erp_can($pdo, 'documents.archive') && ($restore || $d['uploaded_by'] !== erp_user())) erp_fail('Only the person who uploaded it or an admin with "archive documents" permission can do this.', 403);
            $reason = mb_substr(trim((string)erp_input('reason', '')), 0, 255);
            $pdo->prepare("INSERT INTO erp_document_meta (document_id, category, version, root_document_id, status, archived_by, archived_at, archive_reason) VALUES (?,?,?,?,?,?,?,?)
                           ON DUPLICATE KEY UPDATE status = VALUES(status), archived_by = VALUES(archived_by), archived_at = VALUES(archived_at), archive_reason = VALUES(archive_reason)")
                ->execute([$d['id'], $d['category'] ?: doc_type_category($d['doc_type']), $d['version'] ?: 1, $d['root_document_id'] ?: $d['id'], $restore ? 'active' : 'archived',
                           $restore ? null : erp_user(), $restore ? null : date('Y-m-d H:i:s'), $restore ? null : ($reason ?: 'Archived')]);
            log_audit($pdo, $restore ? 'restore' : 'archive', 'erp_documents', $d['id'], ['status' => $d['meta_status'] ?: 'active'], ['status' => $restore ? 'active' : 'archived', 'reason' => $reason]);
            erp_out(['status' => 'success', 'message' => $restore ? 'Document restored.' : 'Document archived (the file is kept; it can be restored).']);

        case 'history':
            $d = erp_row($pdo, "SELECT d.id, d.entity_type, COALESCE(m.root_document_id, d.id) AS root_id FROM erp_documents d LEFT JOIN erp_document_meta m ON m.document_id = d.id WHERE d.id = ?", [(int)erp_input('id')]);
            if (!$d || !doc_can_see($pdo, $d['entity_type'])) erp_fail('Document not found.');
            $rows = doc_rows($pdo, "(d.id = ? OR m.root_document_id = ?)", [$d['root_id'], $d['root_id']]);
            $audit = erp_rows($pdo, "SELECT action, username, old_value, new_value, created_at FROM audit_logs WHERE module = 'erp_documents' AND record_id IN (" . implode(',', array_map('intval', array_column($rows, 'id')) ?: [0]) . ") ORDER BY id DESC");
            erp_out(['status' => 'success', 'versions' => $rows, 'audit' => $audit]);

        case 'all':
            $w = ['1=1']; $p = [];
            $visible = array_values(array_filter(array_keys($ENT), function ($t) use ($pdo) { return doc_can_see($pdo, $t); }));
            if (!$visible) erp_fail('You do not have permission to see documents.', 403);
            $w[] = 'd.entity_type IN (' . implode(',', array_fill(0, count($visible), '?')) . ')'; $p = array_merge($p, $visible);
            if (($t = (string)erp_input('entity_type', '')) !== '') { $w[] = 'd.entity_type = ?'; $p[] = $t; }
            if (($c = (string)erp_input('category', '')) !== '') { $w[] = "COALESCE(m.category, CASE d.doc_type WHEN 'vendor_invoice' THEN 'SUPPLIER_INVOICE' WHEN 'purchase_receipt' THEN 'GRN' WHEN 'delivery_challan' THEN 'GRN' WHEN 'eway_bill' THEN 'E_WAY_BILL' WHEN 'qc_document' THEN 'QC_DOCUMENT' WHEN 'payment_proof' THEN 'SUPPLIER_PAYMENT_PROOF' WHEN 'credit_note' THEN 'CREDIT_DEBIT_NOTE' WHEN 'expense_receipt' THEN 'EXPENSE_RECEIPT' ELSE 'OTHER' END) = ?"; $p[] = $c; }
            $st = (string)erp_input('status', 'active');
            if ($st !== '') { $w[] = "COALESCE(m.status,'active') = ?"; $p[] = $st; }
            if ($d = erp_date(erp_input('date_from'))) { $w[] = 'd.created_at >= ?'; $p[] = $d . ' 00:00:00'; }
            if ($d = erp_date(erp_input('date_to'))) { $w[] = 'd.created_at <= ?'; $p[] = $d . ' 23:59:59'; }
            if ($q = trim((string)erp_input('q', ''))) { $w[] = '(d.original_name LIKE ? OR m.description LIKE ? OR d.uploaded_by LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%"); }
            if (erp_input('latest', '1') === '1' && $st !== 'archived') $w[] = "NOT EXISTS (SELECT 1 FROM erp_document_meta n WHERE n.root_document_id = COALESCE(m.root_document_id, d.id) AND n.version > COALESCE(m.version,1))";
            [$lim, $off] = erp_page_args(50);
            $where = implode(' AND ', $w);
            $total = (int)erp_val($pdo, "SELECT COUNT(*) FROM erp_documents d LEFT JOIN erp_document_meta m ON m.document_id = d.id WHERE {$where}", $p);
            $rows = doc_rows($pdo, $where, $p, "LIMIT {$lim} OFFSET {$off}");
            foreach ($rows as &$r) {
                $r['entity_label'] = ($ENT[$r['entity_type']][0] ?? $r['entity_type']) . ' ' . doc_entity_label($pdo, $r['entity_type'], (int)$r['entity_id']);
                $r['entity_link'] = isset($ENT[$r['entity_type']]) ? $ENT[$r['entity_type']][1] . ($r['entity_type'] === 'company' ? '' : $r['entity_id']) : null;
            }
            unset($r);
            $bytes = (int)erp_val($pdo, "SELECT COALESCE(SUM(file_size),0) FROM erp_documents");
            erp_out(['status' => 'success', 'rows' => $rows, 'total' => $total, 'storage_mb' => round($bytes / 1048576, 1)]);
    }
    erp_fail('Unknown action.');
} catch (Throwable $e) {
    erp_db_error($e, 'documents');
}
