<?php
// ============================================================================
// Shared helpers for shop / competitor quotations (added 1 Oct 2026)
// Used by po_quotes_api.php (quotations on a purchase order) and
// purchase_flow_api.php (quotations on a purchase request). Needs erp_helper.php.
// ============================================================================
if (!function_exists('pq_str')) {
function pq_str($v, int $max): ?string {
    $v = trim(preg_replace('/\s+/u', ' ', (string)$v));
    return $v === '' ? null : mb_substr($v, 0, $max);
}
/** Validates up to 3 quotations from the form. Returns the clean list (empty columns are dropped). */
function pq_clean(PDO $pdo, array $raw, int $selected): array {
    if (count($raw) > 3) erp_invalid('Up to 3 quotations can be compared.');
    $clean = [];
    $slot = 0;
    foreach ($raw as $q) {
        $name = pq_str($q['supplier_name'] ?? '', 150);
        $lines = is_array($q['items'] ?? null) ? $q['items'] : [];
        if (!$name && !$lines) continue;   // empty column
        $slot++;
        if (!$name) erp_invalid("Quotation {$slot}: enter the supplier name.");
        $gst = strtoupper((string)pq_str($q['gst_number'] ?? '', 15));
        if ($gst !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][A-Z0-9]Z[A-Z0-9]$/', $gst)) erp_invalid("{$name}: enter a valid 15-character GSTIN or leave it blank.");
        $ifsc = strtoupper((string)pq_str($q['bank_ifsc'] ?? '', 11));
        if ($ifsc !== '' && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc)) erp_invalid("{$name}: enter a valid 11-character IFSC code or leave it blank.");
        $acct = preg_replace('/\s+/', '', (string)($q['bank_account_number'] ?? ''));
        if ($acct !== '' && !preg_match('/^[0-9A-Za-z]{6,34}$/', $acct)) erp_invalid("{$name}: the bank account number looks wrong.");
        $email = pq_str($q['email'] ?? '', 150);
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) erp_invalid("{$name}: the e-mail address looks wrong.");
        $days = trim((string)($q['delivery_days'] ?? ''));
        if ($days !== '' && (!ctype_digit($days) || (int)$days > 3650)) erp_invalid("{$name}: delivery days must be a whole number of days.");
        $supplierId = (int)($q['supplier_id'] ?? 0) ?: null;
        if ($supplierId && !erp_val($pdo, "SELECT id FROM suppliers WHERE id = ?", [$supplierId])) $supplierId = null;
        $freight = erp_m(erp_num($q['freight'] ?? 0, "{$name} freight"));
        $items = []; $sub = 0.0; $tax = 0.0;
        foreach ($lines as $l) {
            $iname = pq_str($l['item_name'] ?? '', 255);
            $type = (string)($l['item_type'] ?? ''); $iid = (int)($l['item_id'] ?? 0);
            if ($type !== '' || $iid) {
                $type = erp_item_type($type);
                $it = erp_item($pdo, $type, $iid);
                if (!$it) erp_invalid("{$name}: an item in the quotation no longer exists.");
                $iname = $iname ?: $it['name'];
            } else { $type = null; $iid = null; }
            if (!$iname) continue;
            $qty = erp_q(erp_num($l['quantity'] ?? 0, "{$name} quantity"));
            $rate = erp_u(erp_num($l['rate'] ?? 0, "{$name} rate"));
            $tp = erp_num($l['tax_percent'] ?? 0, "{$name} GST %");
            if ($tp > 100) erp_invalid("{$name}: GST % cannot be more than 100.");
            if ($qty <= 0 && $rate <= 0) continue;   // no price given for this item
            $c = erp_line($qty, $rate, 0, $tp);
            $sub += $c['net']; $tax += $c['tax'];
            $items[] = [$type, $iid, $iname, $qty, pq_str($l['unit'] ?? '', 30), $rate, $tp, $c['total']];
        }
        $clean[] = ['slot' => $slot, 'supplier_id' => $supplierId, 'supplier_name' => $name, 'contact_person' => pq_str($q['contact_person'] ?? '', 150),
            'mobile' => pq_str($q['mobile'] ?? '', 20), 'email' => $email, 'gst_number' => $gst ?: null, 'account_holder_name' => pq_str($q['account_holder_name'] ?? '', 150),
            'bank_name' => pq_str($q['bank_name'] ?? '', 150), 'bank_account_number' => $acct ?: null, 'bank_ifsc' => $ifsc ?: null, 'upi_id' => pq_str($q['upi_id'] ?? '', 150),
            'delivery_days' => $days === '' ? null : (int)$days, 'payment_terms' => pq_str($q['payment_terms'] ?? '', 150), 'freight' => $freight,
            'valid_till' => erp_date($q['valid_till'] ?? ''), 'notes' => pq_str($q['notes'] ?? '', 500), 'subtotal' => erp_m($sub), 'tax_total' => erp_m($tax),
            'grand_total' => erp_m($sub + $tax + $freight), 'is_selected' => (int)((int)($q['slot'] ?? $slot) === $selected && $selected > 0),
            'source_file' => pq_str($q['source_file'] ?? '', 255), 'items' => $items];
    }
    return $clean;
}
/** Replaces the stored quotations of one document. $t = ['q' => quotes table, 'i' => items table, 'fk' => 'po_id' | 'pr_id'] */
function pq_store(PDO $pdo, array $t, int $docId, array $clean): array {
    $old = erp_rows($pdo, "SELECT slot, supplier_name, grand_total, is_selected FROM {$t['q']} WHERE {$t['fk']} = ? ORDER BY slot", [$docId]);
    $pdo->prepare("DELETE FROM {$t['q']} WHERE {$t['fk']} = ?")->execute([$docId]);   // items go with it (ON DELETE CASCADE)
    $insQ = $pdo->prepare("INSERT INTO {$t['q']} ({$t['fk']}, slot, supplier_id, supplier_name, contact_person, mobile, email, gst_number, account_holder_name, bank_name, bank_account_number,
                           bank_ifsc, upi_id, delivery_days, payment_terms, freight, valid_till, notes, subtotal, tax_total, grand_total, is_selected, source_file, created_by)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $insI = $pdo->prepare("INSERT INTO {$t['i']} (quote_id, item_type, item_id, item_name, quantity, unit, rate, tax_percent, line_total) VALUES (?,?,?,?,?,?,?,?,?)");
    foreach ($clean as $c) {
        $insQ->execute([$docId, $c['slot'], $c['supplier_id'], $c['supplier_name'], $c['contact_person'], $c['mobile'], $c['email'], $c['gst_number'], $c['account_holder_name'],
            $c['bank_name'], $c['bank_account_number'], $c['bank_ifsc'], $c['upi_id'], $c['delivery_days'], $c['payment_terms'], $c['freight'], $c['valid_till'], $c['notes'],
            $c['subtotal'], $c['tax_total'], $c['grand_total'], $c['is_selected'], $c['source_file'], erp_user()]);
        $qid = (int)$pdo->lastInsertId();
        foreach ($c['items'] as $i) $insI->execute(array_merge([$qid], $i));
    }
    return $old;
}
/** Stored quotations with their item lines. */
function pq_load(PDO $pdo, array $t, int $docId): array {
    $quotes = erp_rows($pdo, "SELECT * FROM {$t['q']} WHERE {$t['fk']} = ? ORDER BY slot", [$docId]);
    foreach ($quotes as &$q) $q['items'] = erp_rows($pdo, "SELECT item_type, item_id, item_name, quantity, unit, rate, tax_percent, line_total FROM {$t['i']} WHERE quote_id = ? ORDER BY id", [$q['id']]);
    unset($q);
    return $quotes;
}
function pq_table_exists(PDO $pdo, string $table): bool {
    try { $pdo->query("SELECT 1 FROM {$table} LIMIT 1"); return true; } catch (PDOException $e) { return false; }
}
}
