<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * Reporting queries.
 *
 * Every method takes an optional $branchId. When set (which is forced for
 * branch-locked users — see resolveBranchId()), the figures cover only that
 * branch, so an assistant manager's report shows their branch and nothing
 * else. When null, figures cover the whole business.
 *
 * Note on scoping: stock and customer dues are joined back through `sales`
 * where a branch column exists; the central product catalogue itself has no
 * branch, so per-branch stock comes from vw_branch_stock instead.
 */
class Report extends BaseModel
{
    /** Build "AND <col> = ?" plus its bound value, or empty when unscoped. */
    private static function branchClause(?int $branchId, string $col = 's.branch_id'): array
    {
        return $branchId ? [" AND $col = ?", [$branchId]] : ['', []];
    }

    /**
     * Sales summary between two dates.
     */
    public static function salesSummary(string $from, string $to, ?int $branchId = null): array
    {
        [$clause, $extra] = self::branchClause($branchId, 'branch_id');
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS sale_count,
                    COALESCE(SUM(subtotal),0)     AS subtotal,
                    COALESCE(SUM(discount),0)     AS discount,
                    COALESCE(SUM(total_amount),0) AS total,
                    COALESCE(SUM(paid_amount),0)  AS paid,
                    COALESCE(SUM(due_amount),0)   AS due
             FROM sales
             WHERE status = 'completed' AND sale_date BETWEEN ? AND ?$clause",
            array_merge([$from, $to], $extra)
        );
        return $row ?: [];
    }

    /**
     * Daily sales between two dates (for line/bar charts).
     */
    public static function dailySales(string $from, string $to, ?int $branchId = null): array
    {
        [$clause, $extra] = self::branchClause($branchId, 'branch_id');
        return Database::fetchAll(
            "SELECT sale_date,
                    COUNT(*) AS sale_count,
                    COALESCE(SUM(total_amount),0) AS total,
                    COALESCE(SUM(paid_amount),0)  AS paid,
                    COALESCE(SUM(due_amount),0)   AS due
             FROM sales
             WHERE status = 'completed' AND sale_date BETWEEN ? AND ?$clause
             GROUP BY sale_date
             ORDER BY sale_date",
            array_merge([$from, $to], $extra)
        );
    }

    /**
     * Best-selling products by quantity and revenue.
     */
    public static function topProducts(string $from, string $to, int $limit = 10, ?int $branchId = null): array
    {
        $limit = max(1, min(100, $limit));
        [$clause, $extra] = self::branchClause($branchId);
        return Database::fetchAll(
            "SELECT p.id, p.name AS product_name, pc.name AS product_type, p.unit,
                    SUM(si.quantity)    AS total_qty,
                    SUM(si.total_price) AS total_revenue
             FROM sale_items si
             JOIN sales s    ON s.id = si.sale_id
             JOIN products p ON p.id = si.product_id
             JOIN product_categories pc ON pc.id = p.category_id
             WHERE s.status = 'completed' AND s.sale_date BETWEEN ? AND ?$clause
             GROUP BY p.id
             ORDER BY total_revenue DESC
             LIMIT $limit",
            array_merge([$from, $to], $extra)
        );
    }

    /**
     * Sales split by product type (rod vs cement).
     */
    public static function salesByType(string $from, string $to, ?int $branchId = null): array
    {
        [$clause, $extra] = self::branchClause($branchId);
        return Database::fetchAll(
            "SELECT pc.name AS product_type,
                    SUM(si.quantity)    AS total_qty,
                    SUM(si.total_price) AS total_revenue
             FROM sale_items si
             JOIN sales s    ON s.id = si.sale_id
             JOIN products p ON p.id = si.product_id
             JOIN product_categories pc ON pc.id = p.category_id
             WHERE s.status = 'completed' AND s.sale_date BETWEEN ? AND ?$clause
             GROUP BY pc.id",
            array_merge([$from, $to], $extra)
        );
    }

    /**
     * Purchase (stock inbound) summary between two dates.
     */
    public static function purchaseSummary(string $from, string $to, ?int $branchId = null): array
    {
        [$clause, $extra] = self::branchClause($branchId, 'branch_id');
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS purchase_count,
                    COALESCE(SUM(quantity),0)   AS total_qty,
                    COALESCE(SUM(total_cost),0) AS total_cost
             FROM stock_inbound
             WHERE inbound_date BETWEEN ? AND ?$clause",
            array_merge([$from, $to], $extra)
        );
        return $row ?: [];
    }

    /**
     * Payments collected between two dates.
     * Payments have no branch of their own, so they are attributed through
     * the sale they were made against.
     */
    public static function paymentsSummary(string $from, string $to, ?int $branchId = null): array
    {
        if ($branchId) {
            $row = Database::fetchOne(
                "SELECT COUNT(*) AS payment_count,
                        COALESCE(SUM(pay.amount),0) AS total_collected
                 FROM payments pay
                 JOIN sales s ON s.id = pay.sale_id
                 WHERE pay.payment_date BETWEEN ? AND ? AND s.branch_id = ?",
                [$from, $to, $branchId]
            );
        } else {
            $row = Database::fetchOne(
                "SELECT COUNT(*) AS payment_count,
                        COALESCE(SUM(amount),0) AS total_collected
                 FROM payments
                 WHERE payment_date BETWEEN ? AND ?",
                [$from, $to]
            );
        }
        return $row ?: [];
    }

    /**
     * Current stock valuation. Scoped users see their branch's stock via
     * vw_branch_stock; unscoped users see the global picture.
     */
    public static function stockValuation(?int $branchId = null): array
    {
        if ($branchId) {
            $rows = Database::fetchAll(
                "SELECT product_id, product_name, product_type, unit,
                        current_stock, buy_price, sell_price,
                        (current_stock * buy_price)  AS stock_cost,
                        (current_stock * sell_price) AS stock_value
                 FROM vw_branch_stock
                 WHERE branch_id = ?
                 ORDER BY product_type, product_name",
                [$branchId]
            );
        } else {
            $rows = Database::fetchAll(
                "SELECT product_id, product_name, product_type, unit,
                        current_stock, buy_price, sell_price,
                        (current_stock * buy_price)  AS stock_cost,
                        (current_stock * sell_price) AS stock_value
                 FROM vw_current_stock
                 ORDER BY product_type, product_name"
            );
        }
        $totals = ['cost' => 0, 'value' => 0];
        foreach ($rows as $r) {
            $totals['cost']  += (float)$r['stock_cost'];
            $totals['value'] += (float)$r['stock_value'];
        }
        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Customers with outstanding dues. Scoped users only see dues arising
     * from their own branch's sales.
     */
    public static function customerDues(?int $branchId = null): array
    {
        if ($branchId) {
            return Database::fetchAll(
                "SELECT c.id AS customer_id, c.name AS customer_name, c.phone,
                        COALESCE(SUM(s.total_amount),0) AS total_purchase,
                        COALESCE(SUM(s.paid_amount),0)  AS total_paid,
                        COALESCE(SUM(s.due_amount),0)   AS total_due
                 FROM customers c
                 JOIN sales s ON s.customer_id = c.id
                              AND s.status = 'completed' AND s.branch_id = ?
                              AND s.ledger_id IS NULL
                 GROUP BY c.id
                 HAVING total_due > 0
                 ORDER BY total_due DESC",
                [$branchId]
            );
        }
        return Database::fetchAll(
            "SELECT customer_id, customer_name, phone,
                    total_purchase, total_paid, total_due
             FROM vw_customer_dues
             WHERE total_due > 0
             ORDER BY total_due DESC"
        );
    }

    /**
     * Profit estimate: revenue vs cost of goods sold (using current buy_price).
     */
    public static function profitEstimate(string $from, string $to, ?int $branchId = null): array
    {
        [$clause, $extra] = self::branchClause($branchId);
        $row = Database::fetchOne(
            "SELECT COALESCE(SUM(si.total_price),0)          AS revenue,
                    COALESCE(SUM(si.quantity * p.buy_price),0) AS cost
             FROM sale_items si
             JOIN sales s    ON s.id = si.sale_id
             JOIN products p ON p.id = si.product_id
             WHERE s.status = 'completed' AND s.sale_date BETWEEN ? AND ?$clause",
            array_merge([$from, $to], $extra)
        );
        $revenue = (float)($row['revenue'] ?? 0);
        $cost    = (float)($row['cost'] ?? 0);
        return [
            'revenue' => $revenue,
            'cost'    => $cost,
            'profit'  => $revenue - $cost,
        ];
    }
}
