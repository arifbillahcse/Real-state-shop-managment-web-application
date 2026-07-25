<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * Daily statement (§8) — one date, four sections:
 *   8.1 full-account customer activity (deliveries, deposits, dues, balance)
 *   8.2 short-account customer activity
 *   8.3 cash sales
 *   +  delivered-product stock summary and day totals
 */
class DailyStatement
{
    // Each section is independent — if one query fails (e.g. a table from a
    // not-yet-applied migration is missing), the rest of the statement still
    // loads instead of the whole page dying. Failures are logged so the real
    // cause is visible in the error log.
    public static function build(string $date): array
    {
        $safe = function (callable $fn, $fallback) use ($date) {
            try {
                return $fn($date);
            } catch (\Throwable $e) {
                error_log('DailyStatement section failed for ' . $date . ': ' . $e->getMessage());
                return $fallback;
            }
        };

        return [
            'date'          => $date,
            'full_account'  => $safe(fn($d) => self::accountSection($d, 'full'),  []),
            'short_account' => $safe(fn($d) => self::accountSection($d, 'short'), []),
            'cash_sales'    => $safe([self::class, 'cashSales'],             []),
            'product_stock' => $safe([self::class, 'deliveredProductStock'], []),
            'summary'       => $safe([self::class, 'summary'], [
                'product_kinds' => 0, 'total_value' => 0, 'cash_received' => 0, 'due' => 0,
            ]),
        ];
    }

    // Customers (by account type) with any ledger activity on the date
    private static function accountSection(string $date, string $type): array
    {
        $rows = Database::fetchAll(
            'SELECT c.id, c.name, c.phone, c.address, c.account_no,
                    -- day activity
                    COALESCE(SUM(CASE WHEN l.entry_type = "goods"        THEN l.debit  END), 0) AS delivery_value,
                    COALESCE(SUM(CASE WHEN l.entry_type = "deposit"      THEN l.credit END), 0) AS deposit,
                    COALESCE(SUM(CASE WHEN l.entry_type = "expense"      THEN l.debit  END), 0) AS other_expense,
                    COALESCE(SUM(CASE WHEN l.entry_type = "money_return" THEN l.debit  END), 0) AS money_returned
             FROM customers c
             JOIN customer_ledger l
                   ON l.customer_id = c.id AND l.status = "final" AND l.entry_date = ?
             WHERE c.account_type = ? AND c.is_active = 1
             GROUP BY c.id
             ORDER BY c.name',
            [$date, $type]
        );

        foreach ($rows as &$r) {
            // Item list of the day (goods entries)
            $items = Database::fetchAll(
                'SELECT cli.product_name, cli.quantity, cli.unit
                 FROM customer_ledger_items cli
                 JOIN customer_ledger l ON l.id = cli.ledger_id
                 WHERE l.customer_id = ? AND l.entry_type = "goods"
                   AND l.status = "final" AND l.entry_date = ?',
                [$r['id'], $date]
            );
            $r['items'] = array_map(
                fn($it) => $it['product_name'] . ' ' . rtrim(rtrim($it['quantity'], '0'), '.') . ' ' . $it['unit'],
                $items
            );

            // Previous due = balance before the date; current = previous + day's net
            $prev = Database::fetchOne(
                'SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS bal
                 FROM customer_ledger
                 WHERE customer_id = ? AND status = "final" AND entry_date < ?',
                [$r['id'], $date]
            );
            $day = Database::fetchOne(
                'SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS net
                 FROM customer_ledger
                 WHERE customer_id = ? AND status = "final" AND entry_date = ?',
                [$r['id'], $date]
            );
            $r['previous_due']    = (float)($prev['bal'] ?? 0);
            $r['current_balance'] = $r['previous_due'] + (float)($day['net'] ?? 0);

            // Active collector (হিসাব ট্রান্সফার)
            $collector = Database::fetchOne(
                'SELECT u.name FROM due_assignments a
                 JOIN users u ON u.id = a.staff_id
                 WHERE a.customer_id = ? AND a.status = "active"
                 ORDER BY a.id DESC LIMIT 1',
                [$r['id']]
            );
            $r['collector'] = $collector['name'] ?? null;
        }
        return $rows;
    }

    // Cash sales of the day (§8.3) — invoice-based sales
    private static function cashSales(string $date): array
    {
        return Database::fetchAll(
            'SELECT s.invoice_number, s.total_amount, s.paid_amount, s.due_amount,
                    s.payment_method,
                    COALESCE(c.name, "Walk-in") AS customer_name,
                    (SELECT GROUP_CONCAT(CONCAT(p.name, " ", si.quantity, " ", p.unit) SEPARATOR ", ")
                     FROM sale_items si JOIN products p ON p.id = si.product_id
                     WHERE si.sale_id = s.id) AS items
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             WHERE s.sale_date = ? AND s.status = "completed"
             ORDER BY s.id',
            [$date]
        );
    }

    // Delivered product stock (ডেলিভারি পণ্যর স্টক): day movement + current stock
    private static function deliveredProductStock(string $date): array
    {
        return Database::fetchAll(
            'SELECT v.product_name, v.unit, v.current_stock, v.sell_price,
                    COALESCE(day_ledger.qty, 0) + COALESCE(day_sales.qty, 0) AS today_sold
             FROM vw_current_stock v
             LEFT JOIN (
                 SELECT cli.product_id, SUM(cli.quantity) AS qty
                 FROM customer_ledger_items cli
                 JOIN customer_ledger l ON l.id = cli.ledger_id
                 WHERE l.entry_type = "goods" AND l.status = "final" AND l.entry_date = ?
                 GROUP BY cli.product_id
             ) day_ledger ON day_ledger.product_id = v.product_id
             LEFT JOIN (
                 SELECT si.product_id, SUM(si.quantity) AS qty
                 FROM sale_items si
                 JOIN sales s ON s.id = si.sale_id
                 WHERE s.sale_date = ? AND s.status = "completed"
                 GROUP BY si.product_id
             ) day_sales ON day_sales.product_id = v.product_id
             WHERE COALESCE(day_ledger.qty, 0) + COALESCE(day_sales.qty, 0) > 0
             ORDER BY today_sold * v.sell_price DESC',
            [$date, $date]
        );
    }

    // Day summary (সারাংশ)
    private static function summary(string $date): array
    {
        $ledger = Database::fetchOne(
            'SELECT COALESCE(SUM(CASE WHEN entry_type = "goods"   THEN debit  END), 0) AS goods_value,
                    COALESCE(SUM(CASE WHEN entry_type = "deposit" THEN credit END), 0) AS deposits
             FROM customer_ledger WHERE status = "final" AND entry_date = ?',
            [$date]
        );
        $sales = Database::fetchOne(
            'SELECT COALESCE(SUM(total_amount), 0) AS total,
                    COALESCE(SUM(paid_amount),  0) AS paid,
                    COALESCE(SUM(due_amount),   0) AS due,
                    COUNT(DISTINCT id)             AS invoice_count
             FROM sales WHERE sale_date = ? AND status = "completed"',
            [$date]
        );
        $kinds = Database::fetchOne(
            'SELECT COUNT(DISTINCT product_id) AS kinds FROM (
                SELECT cli.product_id
                FROM customer_ledger_items cli
                JOIN customer_ledger l ON l.id = cli.ledger_id
                WHERE l.entry_type = "goods" AND l.status = "final" AND l.entry_date = ?
                UNION
                SELECT si.product_id
                FROM sale_items si JOIN sales s ON s.id = si.sale_id
                WHERE s.sale_date = ? AND s.status = "completed"
             ) x',
            [$date, $date]
        );
        $totalValue = (float)($ledger['goods_value'] ?? 0) + (float)($sales['total'] ?? 0);
        $cashIn     = (float)($ledger['deposits'] ?? 0)    + (float)($sales['paid'] ?? 0);
        return [
            'product_kinds' => (int)($kinds['kinds'] ?? 0),
            'total_value'   => $totalValue,
            'cash_received' => $cashIn,
            'due'           => max(0, $totalValue - $cashIn),
        ];
    }
}
