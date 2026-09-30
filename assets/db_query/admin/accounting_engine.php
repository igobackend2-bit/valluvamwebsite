<?php
// ============================================================================
// Double-entry journal layer (added 1 Oct 2026)
//
// Journals are created AUTOMATICALLY from the records that already exist —
// nothing in the existing sales / purchase / cash-book code changed:
//   purchase bill ........ DR GRNI / purchases, DR GST input  CR Accounts payable
//   supplier payment ..... DR Accounts payable                CR Cash / Bank
//   purchase return ...... DR Accounts payable                CR GRNI
//   sales invoice / manual / credit sale / website order
//                          DR Receivable or Cash/Bank         CR Sales, CR GST output
//   customer receipt ..... DR Cash / Bank                     CR Receivable
//   sales return ......... DR Sales returns                   CR Cash/Bank / Receivable
//   expense .............. DR Expense, DR GST input           CR Cash/Bank / Other payables
//   other cash-book rows . by type (income / expense / unknown → Suspense)
//   inventory ............ one journal per day from the costing engine:
//                          receipts CR GRNI, sales DR COGS, waste DR write-off,
//                          adjustments, repacking, transfers (stock in transit)
// Every journal has UNIQUE (source_type, source_id, event) → never posted twice.
// Cancelled sources get a reversal journal. Debit = credit is enforced.
// Journals start at the GL start date (Accounting settings); earlier history
// stays in the operational reports only.
// ============================================================================
require_once __DIR__ . '/erp_ext.php';

function acc_start(PDO $pdo): ?string {
    $d = (string)erp_setting($pdo, 'erp_gl_start_date', '');
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
}
function acc_id(PDO $pdo, string $key): int {
    static $c = [];
    if (!isset($c[$key])) {
        $id = erp_val($pdo, "SELECT id FROM chart_of_accounts WHERE system_key = ?", [$key]);
        if (!$id) throw new RuntimeException("System account '{$key}' is missing from the chart of accounts.");
        $c[$key] = (int)$id;
    }
    return $c[$key];
}
/** CoA account for a payment: named bank account first, then the account set for that payment mode, then default bank / cash. */
function acc_money(PDO $pdo, ?string $mode, ?string $accountName = null): int {
    static $banks = null;
    if ($banks === null) $banks = erp_rows($pdo, "SELECT * FROM bank_accounts WHERE status = 'active' ORDER BY is_default DESC, id");
    $mode = strtolower((string)$mode);
    if ($accountName) foreach ($banks as $b) if (strcasecmp($b['name'], trim($accountName)) === 0) return (int)$b['coa_account_id'];
    foreach ($banks as $b) if ($mode !== '' && in_array($mode, array_map('trim', explode(',', strtolower((string)$b['payment_modes']))), true)) return (int)$b['coa_account_id'];
    if ($mode === 'cash') return acc_id($pdo, 'cash');
    foreach ($banks as $b) if ((int)$b['is_default'] === 1) return (int)$b['coa_account_id'];
    return acc_id($pdo, 'bank');
}
/** Expense account from a free-text cash-book category. */
function acc_expense_account(PDO $pdo, ?string $category): int {
    static $list = null;
    if ($list === null) $list = erp_rows($pdo, "SELECT id, name, system_key FROM chart_of_accounts WHERE account_type = 'expense' AND is_active = 1 AND (sub_type = 'operating' OR sub_type IS NULL)");
    $c = strtolower(trim((string)$category));
    if ($c !== '') {
        foreach ($list as $a) if (strtolower($a['name']) === $c) return (int)$a['id'];
        foreach ($list as $a) { $n = strtolower($a['name']); $w = strtok($n, ' &'); if ($w && strlen($w) > 3 && strpos($c, $w) !== false) return (int)$a['id']; }
        $syn = ['salary' => 'exp_salary', 'wage' => 'exp_salary', 'rent' => 'exp_rent', 'power' => 'exp_electricity', 'eb ' => 'exp_electricity', 'courier' => 'exp_delivery',
                'fuel' => 'exp_transport', 'freight' => 'exp_transport', 'ads' => 'exp_advertising', 'promotion' => 'exp_marketing', 'repair' => 'exp_maintenance', 'internet' => 'exp_software',
                'stationery' => 'exp_office', 'bank' => 'exp_bank', 'packing' => 'exp_packaging'];
        foreach ($syn as $k => $key) if (strpos($c, $k) !== false) return acc_id($pdo, $key);
    }
    return acc_id($pdo, 'other_expense');
}
/** Closed-period protection: a journal dated in a closed period is dated the first open day after it. */
function acc_open_date(PDO $pdo, string $date): string {
    for ($i = 0; $i < 60; $i++) {
        $p = erp_row($pdo, "SELECT end_date FROM financial_periods WHERE status = 'closed' AND ? BETWEEN start_date AND end_date ORDER BY end_date DESC LIMIT 1", [$date]);
        if (!$p) return $date;
        $date = date('Y-m-d', strtotime($p['end_date'] . ' +1 day'));
    }
    return $date;
}

/**
 * Posts one balanced journal. $lines: [['acc' => id, 'dr' => x, 'cr' => y, 'party_type','party_id','party_name','channel','memo'], ...]
 * Returns the journal id, or null if this (source_type, source_id, event) was already posted or nothing to post.
 */
function jr_post(PDO $pdo, string $module, string $srcType, string $srcId, string $event, string $date, ?string $ref, string $narration, array $lines, array $opt = []): ?int {
    $clean = []; $dr = 0.0; $cr = 0.0;
    foreach ($lines as $l) {
        $d = erp_m($l['dr'] ?? 0); $c = erp_m($l['cr'] ?? 0);
        if ($d < 0) { $c += -$d; $d = 0.0; }
        if ($c < 0) { $d += -$c; $c = 0.0; }
        if ($d < 0.005 && $c < 0.005) continue;
        $net = erp_m($d - $c);                       // one side per line
        $l['dr'] = $net > 0 ? $net : 0.0; $l['cr'] = $net < 0 ? -$net : 0.0;
        if ($l['dr'] < 0.005 && $l['cr'] < 0.005) continue;
        $dr += $l['dr']; $cr += $l['cr'];
        $clean[] = $l;
    }
    $dr = erp_m($dr); $cr = erp_m($cr);
    if (!$clean) return null;
    if (abs($dr - $cr) >= 0.005) {
        if (abs($dr - $cr) <= 1.0 && empty($opt['strict'])) {     // paise rounding in the source document
            $diff = erp_m($dr - $cr);
            $clean[] = ['acc' => acc_id($pdo, 'rounding'), 'dr' => $diff < 0 ? -$diff : 0, 'cr' => $diff > 0 ? $diff : 0, 'memo' => 'Rounding'];
            if ($diff > 0) $cr += $diff; else $dr += -$diff;
        } else {
            throw new ErpValidation("Journal for {$srcType} {$ref} does not balance (debit ₹{$dr}, credit ₹{$cr}).");
        }
    }
    $date = empty($opt['keep_date']) ? acc_open_date($pdo, $date) : $date;
    $own = !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        $num = next_document_number($pdo, 'journal', 'JV');
        $pdo->prepare("INSERT INTO journal_entries (journal_number, entry_date, source_module, source_type, source_id, event, source_ref, narration, total, status, reversal_of, is_manual, created_by, approved_by, posted_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())")
            ->execute([$num, $date, $module, $srcType, $srcId, $event, $ref ? mb_substr($ref, 0, 80) : null, mb_substr($narration, 0, 255), erp_m($dr),
                       $opt['status'] ?? 'posted', $opt['reversal_of'] ?? null, !empty($opt['manual']) ? 1 : 0, $opt['user'] ?? erp_user(), $opt['approved_by'] ?? null]);
        $jid = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare("INSERT INTO journal_lines (journal_id, account_id, debit, credit, party_type, party_id, party_name, channel, memo) VALUES (?,?,?,?,?,?,?,?,?)");
        foreach ($clean as $l) $ins->execute([$jid, (int)$l['acc'], erp_m($l['dr']), erp_m($l['cr']), $l['party_type'] ?? null, $l['party_id'] ?? null,
                                              isset($l['party_name']) ? mb_substr((string)$l['party_name'], 0, 150) : null, $l['channel'] ?? null, isset($l['memo']) ? mb_substr((string)$l['memo'], 0, 255) : null]);
        if ($own) $pdo->commit();
        return $jid;
    } catch (PDOException $e) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        if ($e->getCode() === '23000' && strpos($e->getMessage(), 'uniq_je_source') !== false) return null;   // already posted
        throw $e;
    }
}
/** Mirror-image journal for a posted journal (used when a source document is cancelled). */
function jr_reverse(PDO $pdo, int $journalId, string $event, ?string $date, string $narration): ?int {
    $j = erp_row($pdo, "SELECT * FROM journal_entries WHERE id = ?", [$journalId]);
    if (!$j || $j['status'] !== 'posted') return null;
    $lines = [];
    foreach (erp_rows($pdo, "SELECT * FROM journal_lines WHERE journal_id = ?", [$journalId]) as $l)
        $lines[] = ['acc' => $l['account_id'], 'dr' => $l['credit'], 'cr' => $l['debit'], 'party_type' => $l['party_type'], 'party_id' => $l['party_id'], 'party_name' => $l['party_name'], 'channel' => $l['channel'], 'memo' => $l['memo']];
    $own = !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        $rid = jr_post($pdo, $j['source_module'], $j['source_type'], $j['source_id'], $event, $date ?: date('Y-m-d'), $j['source_ref'], $narration, $lines, ['reversal_of' => $journalId, 'strict' => true]);
        if ($rid) $pdo->prepare("UPDATE journal_entries SET status = 'reversed' WHERE id = ?")->execute([$journalId]);
        if ($own) $pdo->commit();
        return $rid;
    } catch (Throwable $e) { if ($own && $pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
function jr_find(PDO $pdo, string $srcType, string $srcId, string $event): ?array {
    return erp_row($pdo, "SELECT * FROM journal_entries WHERE source_type = ? AND source_id = ? AND event = ?", [$srcType, $srcId, $event]);
}
/** Reverses the 'post' (and other listed events) of a source once, when the source got cancelled. */
function jr_cancel_source(PDO $pdo, string $srcType, string $srcId, array $events, string $date, string $why): int {
    $n = 0;
    foreach ($events as $ev) {
        $j = jr_find($pdo, $srcType, $srcId, $ev);
        if ($j && $j['status'] === 'posted' && !jr_find($pdo, $srcType, $srcId, 'cancel_' . $ev)) { if (jr_reverse($pdo, (int)$j['id'], 'cancel_' . $ev, $date, $why)) $n++; }
    }
    return $n;
}

/** Sales channel of a sale (tag, else website for website orders, offline for the rest). */
function acc_channel(PDO $pdo, string $srcType, int $id): string {
    static $map = null;
    if ($map === null) {
        $map = [];
        try { foreach (erp_rows($pdo, "SELECT m.source_type, m.source_id, c.code FROM sales_channel_map m JOIN sales_channels c ON c.id = m.channel_id") as $r) $map[$r['source_type'] . ':' . $r['source_id']] = $r['code']; }
        catch (PDOException $e) {}
    }
    return $map[$srcType . ':' . $id] ?? ($srcType === 'website_order' ? 'website' : 'offline');
}

// ============================================================================ SYNC
/** Runs every poster. Returns counts per source. Safe to run any time (idempotent). */
function acc_sync(PDO $pdo, bool $force = false): array {
    $S = acc_start($pdo);
    if (!$S) return ['enabled' => false];
    if (!$force && time() - (int)erp_setting($pdo, 'erp_gl_last_sync', 0) < 60) return ['enabled' => true, 'skipped' => true];
    if (!erp_lock($pdo, 'gl', 20)) return ['enabled' => true, 'busy' => true];
    $n = [];
    try {
        erp_setting_set($pdo, 'erp_gl_last_sync', (string)time(), 'Last automatic journal posting');
        $taxInCost = erp_tax_in_cost($pdo);
        $has = function ($sql, $p = []) use ($pdo) { try { erp_val($pdo, $sql, $p); return true; } catch (PDOException $e) { return false; } };

        // ---------------------------------------------------------------- purchase bills
        $c = 0;
        foreach (erp_rows($pdo, "SELECT pi.*, s.supplier_name FROM purchase_invoices pi JOIN suppliers s ON s.id = pi.supplier_id
                                 WHERE pi.status IN ('posted','cancelled') AND pi.invoice_date >= ? AND pi.posted_at IS NOT NULL
                                   AND NOT EXISTS (SELECT 1 FROM journal_entries j WHERE j.source_type = 'purchase_invoice' AND j.source_id = pi.id AND j.event = 'post')", [$S]) as $b) {
            $party = ['party_type' => 'supplier', 'party_id' => (int)$b['supplier_id'], 'party_name' => $b['supplier_name']];
            $lines = []; $alloc = 0.0;
            foreach (erp_rows($pdo, "SELECT * FROM purchase_invoice_items WHERE pinv_id = ?", [$b['id']]) as $i) {
                $net = (float)$i['line_total'] - (float)$i['tax_amount'];
                $base = $net + (float)$i['allocated_charges'] + ($taxInCost ? (float)$i['tax_amount'] : 0);
                $alloc += (float)$i['allocated_charges'];
                $lines[] = ['acc' => acc_id($pdo, $i['grn_item_id'] ? 'grni' : 'purchase_expense'), 'dr' => $base, 'memo' => $i['grn_item_id'] ? 'Goods received (clears GRNI)' : 'Bill line without goods receipt'];
            }
            $nonCost = erp_m((float)$b['charges_total'] - $alloc);
            if ($nonCost > 0) $lines[] = ['acc' => acc_id($pdo, 'freight_expense'), 'dr' => $nonCost, 'memo' => 'Charges not added to stock cost'];
            if (!$taxInCost && (float)$b['tax_total'] > 0) $lines[] = ['acc' => acc_id($pdo, 'gst_input'), 'dr' => $b['tax_total'], 'memo' => 'GST on purchase'];
            $lines[] = ['acc' => acc_id($pdo, 'ap'), 'cr' => $b['grand_total']] + $party;
            if (jr_post($pdo, 'purchase', 'purchase_invoice', (string)$b['id'], 'post', $b['invoice_date'], $b['pinv_number'] . ' / ' . $b['supplier_invoice_no'], 'Purchase bill ' . $b['supplier_invoice_no'] . ' — ' . $b['supplier_name'], $lines)) $c++;
        }
        foreach (erp_rows($pdo, "SELECT id, pinv_number FROM purchase_invoices WHERE status = 'cancelled'") as $b)
            $c += jr_cancel_source($pdo, 'purchase_invoice', (string)$b['id'], ['post'], date('Y-m-d'), 'Purchase bill ' . $b['pinv_number'] . ' cancelled');
        $n['purchase_bills'] = $c;

        // ---------------------------------------------------------------- supplier payments
        $c = 0;
        foreach (erp_rows($pdo, "SELECT pp.*, s.supplier_name FROM purchase_payments pp JOIN suppliers s ON s.id = pp.supplier_id WHERE pp.payment_date >= ?
                                   AND NOT EXISTS (SELECT 1 FROM journal_entries j WHERE j.source_type = 'purchase_payment' AND j.source_id = pp.id AND j.event = 'post')", [$S]) as $p) {
            $party = ['party_type' => 'supplier', 'party_id' => (int)$p['supplier_id'], 'party_name' => $p['supplier_name']];
            if (jr_post($pdo, 'purchase', 'purchase_payment', (string)$p['id'], 'post', $p['payment_date'], $p['payment_number'], 'Payment to ' . $p['supplier_name'] . ($p['reference_number'] ? ' · ref ' . $p['reference_number'] : ''),
                        [['acc' => acc_id($pdo, 'ap'), 'dr' => $p['amount']] + $party, ['acc' => acc_money($pdo, $p['payment_mode'], $p['account']), 'cr' => $p['amount'], 'memo' => $p['reference_number']]])) $c++;
        }
        foreach (erp_rows($pdo, "SELECT id, payment_number FROM purchase_payments WHERE status = 'cancelled'") as $p)
            $c += jr_cancel_source($pdo, 'purchase_payment', (string)$p['id'], ['post'], date('Y-m-d'), 'Payment ' . $p['payment_number'] . ' cancelled');
        $n['supplier_payments'] = $c;

        // costing-engine trail since the GL start (used by purchase returns and the inventory journals)
        $trails = [];
        foreach (['product', 'raw_material'] as $t) { $tr = []; $trails[$t] = ['run' => ce_run($pdo, $t, $S, null, null, $tr), 'trail' => $tr]; }
        $retValue = [];   // purchase return number => stock value that left at average cost
        foreach ($trails as $t) foreach ($t['trail'] as $e) if ($e['kind'] === 'move' && $e['bucket'] === 'purchase_return') $retValue[$e['ref_no']] = ($retValue[$e['ref_no']] ?? 0) - $e['value'];

        // ---------------------------------------------------------------- purchase returns
        $c = 0;
        foreach (erp_rows($pdo, "SELECT r.*, s.supplier_name FROM purchase_returns r JOIN suppliers s ON s.id = r.supplier_id WHERE r.status = 'posted' AND r.return_date >= ?
                                   AND r.settlement IN ('credit_note','refund')
                                   AND NOT EXISTS (SELECT 1 FROM journal_entries j WHERE j.source_type = 'purchase_return' AND j.source_id = r.id AND j.event = 'post')", [$S]) as $r) {
            $party = ['party_type' => 'supplier', 'party_id' => (int)$r['supplier_id'], 'party_name' => $r['supplier_name']];
            // GRNI is cleared at the stock value that left (average cost); the difference to the credit agreed with the supplier is a price variance
            $stockValue = isset($retValue[$r['return_number']]) ? erp_m($retValue[$r['return_number']]) : (float)$r['total_value'];
            $lines = [['acc' => acc_id($pdo, 'ap'), 'dr' => $r['total_value'], 'memo' => 'Debit note / credit from supplier'] + $party,
                      ['acc' => acc_id($pdo, 'grni'), 'cr' => $stockValue, 'memo' => 'Returned goods at stock value'],
                      ['acc' => acc_id($pdo, 'ppv'), 'dr' => erp_m($stockValue - (float)$r['total_value']), 'memo' => 'Return credit vs average cost']];
            if ((float)$r['refund_received'] > 0) {
                $mode = erp_val($pdo, "SELECT payment_mode FROM accounts_transactions WHERE reference_type = 'purchase_return' AND reference_number = ? LIMIT 1", [$r['return_number']]) ?: 'bank_transfer';
                $lines[] = ['acc' => acc_money($pdo, $mode), 'dr' => $r['refund_received'], 'memo' => 'Refund received'];
                $lines[] = ['acc' => acc_id($pdo, 'ap'), 'cr' => $r['refund_received'], 'memo' => 'Refund received'] + $party;
            }
            if (jr_post($pdo, 'purchase', 'purchase_return', (string)$r['id'], 'post', $r['return_date'], $r['return_number'], 'Purchase return ' . $r['return_number'] . ' — ' . $r['supplier_name'], $lines)) $c++;
        }
        $n['purchase_returns'] = $c;

        // ---------------------------------------------------------------- transport payments (landed cost payable)
        $c = 0;
        foreach (erp_rows($pdo, "SELECT p.*, s.shipment_number, s.transport_company, s.transporter_name FROM inbound_shipment_payments p JOIN inbound_shipments s ON s.id = p.shipment_id
                                 WHERE p.payment_date >= ? AND NOT EXISTS (SELECT 1 FROM journal_entries j WHERE j.source_type = 'shipment_payment' AND j.source_id = p.id AND j.event = 'post')", [$S]) as $p) {
            $who = $p['transport_company'] ?: $p['transporter_name'];
            if (jr_post($pdo, 'purchase', 'shipment_payment', (string)$p['id'], 'post', $p['payment_date'], $p['shipment_number'], 'Transport payment ' . $p['shipment_number'] . ($who ? ' — ' . $who : ''),
                        [['acc' => acc_id($pdo, 'freight_payable'), 'dr' => $p['amount'], 'party_type' => 'transporter', 'party_name' => $who],
                         ['acc' => acc_money($pdo, $p['payment_mode']), 'cr' => $p['amount'], 'memo' => $p['reference_number']]])) $c++;
        }
        foreach (erp_rows($pdo, "SELECT id FROM inbound_shipment_payments WHERE status = 'cancelled'") as $p)
            $c += jr_cancel_source($pdo, 'shipment_payment', (string)$p['id'], ['post'], date('Y-m-d'), 'Transport payment cancelled');
        $n['transport_payments'] = $c;

        // ---------------------------------------------------------------- sales invoices
        $c = 0;
        if ($has("SELECT 1 FROM invoices LIMIT 1")) {
            foreach (erp_rows($pdo, "SELECT * FROM invoices i WHERE i.status NOT IN ('draft','cancelled') AND i.invoice_date >= ?
                                       AND NOT EXISTS (SELECT 1 FROM journal_entries j WHERE j.source_type = 'invoice' AND j.source_id = i.id AND j.event = 'post')", [$S]) as $i) {
                $ch = acc_channel($pdo, 'invoice', (int)$i['id']);
                $party = ['party_type' => 'customer', 'party_name' => trim((string)$i['customer_name'])];
                $tax = (float)$i['tax_amount'];
                if (jr_post($pdo, 'sales', 'invoice', (string)$i['id'], 'post', $i['invoice_date'], $i['invoice_number'], 'Sales invoice ' . $i['invoice_number'] . ' — ' . $i['customer_name'],
                            [['acc' => acc_id($pdo, 'ar'), 'dr' => $i['grand_total'], 'channel' => $ch] + $party,
                             ['acc' => acc_id($pdo, 'sales'), 'cr' => (float)$i['grand_total'] - $tax, 'channel' => $ch],
                             ['acc' => acc_id($pdo, 'gst_output'), 'cr' => $tax, 'channel' => $ch]])) $c++;
            }
            foreach (erp_rows($pdo, "SELECT id, invoice_number FROM invoices WHERE status = 'cancelled'") as $i)
                $c += jr_cancel_source($pdo, 'invoice', (string)$i['id'], ['post'], date('Y-m-d'), 'Invoice ' . $i['invoice_number'] . ' cancelled');
        }
        $n['sales_invoices'] = $c;

        // ---------------------------------------------------------------- manual sales
        $c = 0;
        if ($has("SELECT 1 FROM manual_sales LIMIT 1")) {
            foreach (erp_rows($pdo, "SELECT * FROM manual_sales m WHERE m.sales_date >= ? AND NOT EXISTS (SELECT 1 FROM journal_entries j WHERE j.source_type = 'manual_sale' AND j.source_id = m.id AND j.event = 'post')", [$S]) as $m) {
                $ch = acc_channel($pdo, 'manual_sale', (int)$m['id']);
                $paid = $m['payment_status'] === 'paid' && $m['payment_mode'] !== 'credit';
                $tax = (float)$m['total_tax'];
                $first = $paid ? ['acc' => acc_money($pdo, $m['payment_mode']), 'dr' => $m['grand_total'], 'channel' => $ch]
                               : ['acc' => acc_id($pdo, 'ar'), 'dr' => $m['grand_total'], 'channel' => $ch, 'party_type' => 'customer', 'party_name' => trim((string)$m['customer_name'])];
                if (jr_post($pdo, 'sales', 'manual_sale', (string)$m['id'], 'post', $m['sales_date'], $m['sale_number'], 'Manual sale ' . $m['sale_number'] . ' — ' . ($m['customer_name'] ?: 'walk-in'),
                            [$first, ['acc' => acc_id($pdo, 'sales'), 'cr' => (float)$m['grand_total'] - $tax, 'channel' => $ch], ['acc' => acc_id($pdo, 'gst_output'), 'cr' => $tax, 'channel' => $ch]])) $c++;
            }
        }
        $n['manual_sales'] = $c;

        // ---------------------------------------------------------------- credit sales
        $c = 0;
        if ($has("SELECT 1 FROM credit_sales LIMIT 1")) {
            foreach (erp_rows($pdo, "SELECT * FROM credit_sales cs WHERE cs.sale_date >= ? AND NOT EXISTS (SELECT 1 FROM journal_entries j WHERE j.source_type = 'credit_sale' AND j.source_id = cs.id AND j.event = 'post')", [$S]) as $s) {
                $ch = acc_channel($pdo, 'credit_sale', (int)$s['id']);
                $tax = (float)$s['total_tax'];
                if (jr_post($pdo, 'sales', 'credit_sale', (string)$s['id'], 'post', $s['sale_date'], $s['credit_number'], 'Credit sale ' . $s['credit_number'] . ' — ' . $s['customer_name'],
                            [['acc' => acc_id($pdo, 'ar'), 'dr' => $s['grand_total'], 'channel' => $ch, 'party_type' => 'customer', 'party_name' => trim((string)$s['customer_name'])],
                             ['acc' => acc_id($pdo, 'sales'), 'cr' => (float)$s['grand_total'] - $tax, 'channel' => $ch], ['acc' => acc_id($pdo, 'gst_output'), 'cr' => $tax, 'channel' => $ch]])) $c++;
            }
        }
        $n['credit_sales'] = $c;

        // ---------------------------------------------------------------- website orders (paid online, or cash on delivery)
        $c = 0;
        if ($has("SELECT 1 FROM orders LIMIT 1")) {
            foreach (erp_rows($pdo, "SELECT * FROM orders o WHERE DATE(o.created_at) >= ? AND COALESCE(o.order_status,'') <> 'cancelled'
                                       AND (o.payment_status = 'paid' OR UPPER(o.payment_method) = 'COD')
                                       AND NOT EXISTS (SELECT 1 FROM journal_entries j WHERE j.source_type = 'website_order' AND j.source_id = o.id AND j.event = 'post')", [$S]) as $o) {
                $paid = $o['payment_status'] === 'paid';
                $cust = trim($o['first_name'] . ' ' . $o['last_name']);
                $first = $paid ? ['acc' => acc_money($pdo, 'upi', erp_setting($pdo, 'erp_online_payment_account', null)), 'dr' => $o['amount'], 'channel' => 'website', 'memo' => 'Online payment ' . ($o['razorpay_payment_id'] ?? '')]
                               : ['acc' => acc_id($pdo, 'ar'), 'dr' => $o['amount'], 'channel' => 'website', 'party_type' => 'customer', 'party_name' => $cust, 'memo' => 'Cash on delivery'];
                if (jr_post($pdo, 'sales', 'website_order', (string)$o['id'], 'post', substr($o['created_at'], 0, 10), $o['receipt'], 'Website order ' . $o['receipt'] . ' — ' . $cust,
                            [$first, ['acc' => acc_id($pdo, 'sales'), 'cr' => $o['amount'], 'channel' => 'website']])) $c++;
            }
            // COD later marked paid → cash collected
            foreach (erp_rows($pdo, "SELECT o.* FROM orders o JOIN journal_entries j ON j.source_type = 'website_order' AND j.source_id = o.id AND j.event = 'post' AND j.status = 'posted'
                                     WHERE o.payment_status = 'paid' AND UPPER(o.payment_method) = 'COD' AND COALESCE(o.order_status,'') <> 'cancelled'
                                       AND NOT EXISTS (SELECT 1 FROM journal_entries k WHERE k.source_type = 'website_order' AND k.source_id = o.id AND k.event = 'collect')") as $o) {
                $cust = trim($o['first_name'] . ' ' . $o['last_name']);
                if (jr_post($pdo, 'sales', 'website_order', (string)$o['id'], 'collect', date('Y-m-d'), $o['receipt'], 'COD collected ' . $o['receipt'],
                            [['acc' => acc_money($pdo, 'cash'), 'dr' => $o['amount'], 'channel' => 'website'], ['acc' => acc_id($pdo, 'ar'), 'cr' => $o['amount'], 'party_type' => 'customer', 'party_name' => $cust, 'channel' => 'website']])) $c++;
            }
            foreach (erp_rows($pdo, "SELECT o.id, o.receipt FROM orders o WHERE o.order_status = 'cancelled' AND EXISTS (SELECT 1 FROM journal_entries j WHERE j.source_type = 'website_order' AND j.source_id = o.id)") as $o)
                $c += jr_cancel_source($pdo, 'website_order', (string)$o['id'], ['post', 'collect'], date('Y-m-d'), 'Website order ' . $o['receipt'] . ' cancelled');
        }
        $n['website_orders'] = $c;

        // ---------------------------------------------------------------- sales returns
        $c = 0;
        foreach (erp_rows($pdo, "SELECT * FROM sales_returns r WHERE r.status = 'posted' AND r.return_date >= ? AND r.settlement IN ('refund','credit_note')
                                   AND NOT EXISTS (SELECT 1 FROM journal_entries j WHERE j.source_type = 'sales_return' AND j.source_id = r.id AND j.event = 'post')", [$S]) as $r) {
            $ch = acc_channel($pdo, $r['source_type'], (int)$r['source_id']);
            $party = ['party_type' => 'customer', 'party_name' => trim((string)$r['customer_name'])];
            // refund_amount = money paid back (refund) or credit given (credit note). Above the ex-tax goods value it is GST
            // given back; below it the difference is kept (e.g. a deduction) and shown as other income.
            $give = (float)$r['refund_amount']; $val = (float)$r['total_value'];
            $lines = [['acc' => acc_id($pdo, 'sales_returns'), 'dr' => $val, 'channel' => $ch]];
            if ($give > 0) $lines[] = $r['settlement'] === 'refund'
                ? ['acc' => acc_money($pdo, $r['refund_mode'] ?: 'cash'), 'cr' => $give, 'memo' => 'Refund paid', 'channel' => $ch]
                : ['acc' => acc_id($pdo, 'ar'), 'cr' => $give, 'memo' => 'Credit note to customer', 'channel' => $ch] + $party;
            $diff = erp_m($give - $val);
            if ($diff > 0) $lines[] = ['acc' => acc_id($pdo, 'gst_output'), 'dr' => $diff, 'memo' => 'GST on returned goods', 'channel' => $ch];
            elseif ($diff < 0) $lines[] = ['acc' => acc_id($pdo, 'other_income'), 'cr' => -$diff, 'memo' => 'Return deduction kept', 'channel' => $ch];
            if (jr_post($pdo, 'sales', 'sales_return', (string)$r['id'], 'post', $r['return_date'], $r['return_number'], 'Sales return ' . $r['return_number'] . ' (' . $r['source_number'] . ')', $lines)) $c++;
        }
        foreach (erp_rows($pdo, "SELECT id, return_number FROM sales_returns WHERE status = 'cancelled'") as $r)
            $c += jr_cancel_source($pdo, 'sales_return', (string)$r['id'], ['post'], date('Y-m-d'), 'Sales return ' . $r['return_number'] . ' cancelled');
        $n['sales_returns'] = $c;

        // ---------------------------------------------------------------- expenses (with approval)
        $c = 0;
        foreach (erp_rows($pdo, "SELECT e.*, a.name AS account_name FROM expenses e JOIN chart_of_accounts a ON a.id = e.account_id WHERE e.status IN ('posted','cancelled') AND e.posted_at IS NOT NULL AND e.expense_date >= ?
                                   AND NOT EXISTS (SELECT 1 FROM journal_entries j WHERE j.source_type = 'expense' AND j.source_id = e.id AND j.event = 'post')", [$S]) as $e) {
            $paidAtPosting = $e['payment_status'] === 'paid' && (!$e['paid_date'] || $e['paid_date'] <= $e['expense_date']);
            $credit = $paidAtPosting
                ? ['acc' => $e['bank_account_id'] ? (int)erp_val($pdo, "SELECT coa_account_id FROM bank_accounts WHERE id = ?", [$e['bank_account_id']]) : acc_money($pdo, $e['payment_mode']), 'cr' => $e['total'], 'memo' => $e['reference_number']]
                : ['acc' => acc_id($pdo, 'other_payable'), 'cr' => $e['total'], 'party_type' => 'payee', 'party_name' => $e['payee']];
            if (jr_post($pdo, 'expense', 'expense', (string)$e['id'], 'post', $e['expense_date'], $e['expense_number'], $e['account_name'] . ($e['payee'] ? ' — ' . $e['payee'] : '') . ($e['description'] ? ' · ' . $e['description'] : ''),
                        [['acc' => (int)$e['account_id'], 'dr' => $e['amount']], ['acc' => acc_id($pdo, 'gst_input'), 'dr' => $e['tax_amount'], 'memo' => 'GST on expense'], $credit])) $c++;
        }
        // unpaid → paid later
        foreach (erp_rows($pdo, "SELECT e.* FROM expenses e WHERE e.status = 'posted' AND e.payment_status = 'paid'
                                   AND EXISTS (SELECT 1 FROM journal_entries j JOIN journal_lines l ON l.journal_id = j.id WHERE j.source_type = 'expense' AND j.source_id = e.id AND j.event = 'post' AND l.account_id = ?)
                                   AND NOT EXISTS (SELECT 1 FROM journal_entries j WHERE j.source_type = 'expense' AND j.source_id = e.id AND j.event = 'paid')", [acc_id($pdo, 'other_payable')]) as $e) {
            if (jr_post($pdo, 'expense', 'expense', (string)$e['id'], 'paid', $e['paid_date'] ?: date('Y-m-d'), $e['expense_number'], 'Paid ' . $e['expense_number'] . ($e['payee'] ? ' — ' . $e['payee'] : ''),
                        [['acc' => acc_id($pdo, 'other_payable'), 'dr' => $e['total'], 'party_type' => 'payee', 'party_name' => $e['payee']],
                         ['acc' => $e['bank_account_id'] ? (int)erp_val($pdo, "SELECT coa_account_id FROM bank_accounts WHERE id = ?", [$e['bank_account_id']]) : acc_money($pdo, $e['payment_mode']), 'cr' => $e['total'], 'memo' => $e['reference_number']]])) $c++;
        }
        foreach (erp_rows($pdo, "SELECT id, expense_number FROM expenses WHERE status = 'cancelled' AND posted_at IS NOT NULL") as $e)
            $c += jr_cancel_source($pdo, 'expense', (string)$e['id'], ['post', 'paid'], date('Y-m-d'), 'Expense ' . $e['expense_number'] . ' cancelled');
        $n['expenses'] = $c;

        // ---------------------------------------------------------------- existing cash book (Accounts → Transactions)
        $c = 0;
        $own = ['purchase_payment', 'purchase_return', 'expense', 'sales_return', 'shipment'];
        $in = implode(',', array_fill(0, count($own), '?'));
        foreach (erp_rows($pdo, "SELECT * FROM accounts_transactions t WHERE t.status = 'completed' AND t.date >= ? AND COALESCE(t.reference_type,'') NOT IN ($in)
                                   AND NOT EXISTS (SELECT 1 FROM journal_entries j WHERE j.source_type = 'cashbook' AND j.source_id = t.id AND j.event = 'post')", array_merge([$S], $own)) as $t) {
            $money = acc_money($pdo, $t['payment_mode'], $t['account']);
            $party = $t['party_name'];
            $amt = (float)$t['amount'];
            $ref = strtolower((string)$t['reference_type']);
            $lines = null; $what = '';
            if ($t['type'] === 'payment_received' && in_array($ref, ['invoice', 'credit_sale', 'manual_sale'], true)) {
                $lines = [['acc' => $money, 'dr' => $amt], ['acc' => acc_id($pdo, 'ar'), 'cr' => $amt, 'party_type' => 'customer', 'party_name' => $party]]; $what = 'Customer receipt';
            } elseif ($t['type'] === 'expense') {
                $lines = [['acc' => acc_expense_account($pdo, $t['category']), 'dr' => $amt, 'memo' => $t['category']], ['acc' => $money, 'cr' => $amt]]; $what = 'Expense';
            } elseif ($t['type'] === 'income') {
                $lines = [['acc' => $money, 'dr' => $amt], ['acc' => acc_id($pdo, 'other_income'), 'cr' => $amt, 'memo' => $t['category']]]; $what = 'Income';
            } elseif ($t['type'] === 'payment_received') {
                $lines = [['acc' => $money, 'dr' => $amt], ['acc' => acc_id($pdo, 'suspense'), 'cr' => $amt, 'party_name' => $party, 'memo' => 'Receipt to classify: ' . $t['category']]]; $what = 'Receipt';
            } elseif (in_array($t['type'], ['payment_made', 'refund'], true)) {
                $lines = [['acc' => acc_id($pdo, 'suspense'), 'dr' => $amt, 'party_name' => $party, 'memo' => 'Payment to classify: ' . $t['category']], ['acc' => $money, 'cr' => $amt]]; $what = 'Payment';
            }
            if ($lines && jr_post($pdo, 'cashbook', 'cashbook', (string)$t['id'], 'post', $t['date'], $t['transaction_id'], $what . ' ' . $t['transaction_id'] . ' — ' . ($t['category'] ?: '') . ($party ? ' · ' . $party : ''), $lines)) $c++;
        }
        foreach (erp_rows($pdo, "SELECT id, transaction_id FROM accounts_transactions WHERE status = 'cancelled' AND EXISTS (SELECT 1 FROM journal_entries j WHERE j.source_type = 'cashbook' AND j.source_id = accounts_transactions.id)") as $t)
            $c += jr_cancel_source($pdo, 'cashbook', (string)$t['id'], ['post'], date('Y-m-d'), 'Transaction ' . $t['transaction_id'] . ' cancelled');
        $n['cash_book'] = $c;

        // ---------------------------------------------------------------- supplier opening balances
        $c = 0;
        foreach (erp_rows($pdo, "SELECT sp.*, s.supplier_name FROM supplier_profiles sp JOIN suppliers s ON s.id = sp.supplier_id") as $sp) {
            $posted = (float)erp_val($pdo, "SELECT COALESCE(SUM(l.credit - l.debit),0) FROM journal_entries j JOIN journal_lines l ON l.journal_id = j.id
                                            WHERE j.source_type = 'supplier_opening' AND j.source_id = ? AND l.account_id = ?", [$sp['supplier_id'], acc_id($pdo, 'ap')]);
            $diff = erp_m((float)$sp['opening_balance'] - $posted);
            if (abs($diff) < 0.005) continue;
            $ev = 'v' . ((int)erp_val($pdo, "SELECT COUNT(*) FROM journal_entries WHERE source_type = 'supplier_opening' AND source_id = ?", [$sp['supplier_id']]) + 1);
            $party = ['party_type' => 'supplier', 'party_id' => (int)$sp['supplier_id'], 'party_name' => $sp['supplier_name']];
            if (jr_post($pdo, 'purchase', 'supplier_opening', (string)$sp['supplier_id'], $ev, $S, 'Opening', 'Opening balance — ' . $sp['supplier_name'],
                        [['acc' => acc_id($pdo, 'obe'), 'dr' => $diff], ['acc' => acc_id($pdo, 'ap'), 'cr' => $diff] + $party])) $c++;
        }
        $n['supplier_openings'] = $c;

        // ---------------------------------------------------------------- inventory (from the costing engine)
        $n['inventory_days'] = acc_sync_inventory($pdo, $S, $trails);
    } finally { erp_unlock($pdo, 'gl'); }
    return ['enabled' => true, 'posted' => $n];
}

/** Counter account for each costing bucket. Returns [counterKey, inventoryIsDebitWhenPositive]. */
function acc_inv_counter(string $bucket, string $refType): string {
    switch ($bucket) {
        case 'purchase': case 'purchase_return': return 'grni';
        case 'repack_in': case 'repack_consume': return 'repack';
        case 'sales_return': case 'sale': return 'cogs';
        case 'waste': return 'inv_loss';
        case 'transfer_in': case 'transfer_out': return 'inventory_transit';
        case 'landed_stock': case 'landed_cogs': return strtolower($refType) === 'shipment' ? 'freight_payable' : 'grni';
        case 'opening_estimate': return 'obe';
        default: return 'stock_adjust';   // adjust_in, adjust_out, unrecorded
    }
}
/**
 * Inventory journals: opening value at the GL start date, then one journal per day with a line
 * pair per costing bucket. Re-computes from the engine every run and posts only the DIFFERENCE
 * against what is already posted (e.g. after an opening cost is set later), so the Inventory
 * accounts always equal the Stock Valuation report.
 */
function acc_sync_inventory(PDO $pdo, string $S, ?array $trails = null): int {
    $target = [];   // day => key => signed amount on the inventory account
    foreach (['product' => 'inventory', 'raw_material' => 'inventory_raw'] as $type => $invKey) {
        if ($trails) { $run = $trails[$type]['run']; $trail = $trails[$type]['trail']; }
        else { $trail = []; $run = ce_run($pdo, $type, $S, null, null, $trail); }
        $open = 0.0;
        foreach ($run as $r) $open += (float)$r['opening_value'];
        $target['opening'][$type . '|opening|obe'] = ($target['opening'][$type . '|opening|obe'] ?? 0) + $open;
        foreach ($trail as $t) {
            if ($t['kind'] === 'open_est' && (empty($t['in_period']) || !empty($t['retro']))) continue;
            $day = substr($t['date'], 0, 10);
            if ($day < $S) $day = $S;
            if ($t['kind'] === 'value') $bucket = $t['bucket'];
            elseif ($t['kind'] === 'gap') $bucket = 'unrecorded';
            else $bucket = $t['bucket'];
            $counter = acc_inv_counter($bucket, (string)$t['ref_type']);
            if ($bucket === 'landed_cogs') { $key = $type . '|landed_cogs|' . $counter; }      // value goes cogs ← counter (inventory not touched)
            else $key = $type . '|' . $bucket . '|' . $counter;
            $target[$day][$key] = ($target[$day][$key] ?? 0) + (float)$t['value'];
        }
    }
    $count = 0;
    foreach ($target as $day => $keys) {
        $srcType = $day === 'opening' ? 'inventory_opening' : 'inventory_day';
        $srcId = $day === 'opening' ? $S : $day;
        $posted = [];
        foreach (erp_rows($pdo, "SELECT l.memo, SUM(l.debit - l.credit) v FROM journal_entries j JOIN journal_lines l ON l.journal_id = j.id
                                 WHERE j.source_type = ? AND j.source_id = ? AND l.memo LIKE 'k=%' AND l.memo LIKE '%#inv' GROUP BY l.memo", [$srcType, $srcId]) as $p)
            $posted[substr($p['memo'], 2, -4)] = (float)$p['v'];
        $lines = [];
        foreach ($keys + array_fill_keys(array_keys($posted), 0) as $key => $_) {
            $diff = erp_m(($keys[$key] ?? 0) - ($posted[$key] ?? 0));
            if (abs($diff) < 0.005) continue;
            [$type, $bucket, $counter] = explode('|', $key);
            $invAcc = acc_id($pdo, $type === 'product' ? 'inventory' : 'inventory_raw');
            $side = $bucket === 'landed_cogs' ? acc_id($pdo, 'cogs') : $invAcc;       // the "inventory side" line
            $lines[] = ['acc' => $side, 'dr' => $diff > 0 ? $diff : 0, 'cr' => $diff < 0 ? -$diff : 0, 'memo' => 'k=' . $key . '#inv'];
            $lines[] = ['acc' => acc_id($pdo, $counter), 'dr' => $diff < 0 ? -$diff : 0, 'cr' => $diff > 0 ? $diff : 0, 'memo' => 'k=' . $key . '#ctr'];
        }
        if (!$lines) continue;
        $ev = 'v' . ((int)erp_val($pdo, "SELECT COUNT(*) FROM journal_entries WHERE source_type = ? AND source_id = ?", [$srcType, $srcId]) + 1);
        $date = $day === 'opening' ? $S : $day;
        $narr = $day === 'opening' ? 'Opening inventory value at GL start (Stock Valuation)' : 'Inventory movements ' . $day . ' (weighted-average cost)' . ($ev !== 'v1' ? ' — correction' : '');
        if (jr_post($pdo, 'inventory', $srcType, $srcId, $ev, $date, $day === 'opening' ? 'Opening stock' : 'Stock ' . $day, $narr, $lines, ['strict' => true])) $count++;
    }
    return $count;
}

// ============================================================================ REPORT HELPERS
/** Balance per account up to a date (or within a range). Returns [account_id => [debit, credit]]. */
function acc_balances(PDO $pdo, ?string $from, string $to): array {
    $w = "j.status IN ('posted','reversed') AND j.entry_date <= ?"; $p = [$to];
    if ($from) { $w .= " AND j.entry_date >= ?"; $p[] = $from; }
    $out = [];
    foreach (erp_rows($pdo, "SELECT l.account_id, SUM(l.debit) d, SUM(l.credit) c FROM journal_lines l JOIN journal_entries j ON j.id = l.journal_id WHERE $w GROUP BY l.account_id", $p) as $r)
        $out[(int)$r['account_id']] = [(float)$r['d'], (float)$r['c']];
    return $out;
}
function acc_accounts(PDO $pdo): array {
    $out = [];
    foreach (erp_rows($pdo, "SELECT * FROM chart_of_accounts ORDER BY code") as $a) $out[(int)$a['id']] = $a;
    return $out;
}
/** Normal-sign balance: assets/expenses = debit − credit; liabilities/equity/income = credit − debit. */
function acc_signed(array $acc, float $d, float $c): float {
    return in_array($acc['account_type'], ['asset', 'expense'], true) ? erp_m($d - $c) : erp_m($c - $d);
}
