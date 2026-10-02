-- Rollback (only if needed): switch the approval rules off. The table is kept (it may hold payment history).
UPDATE approval_policies SET enabled = 0 WHERE module IN ('transport_charge_mgr','transport_charge');
