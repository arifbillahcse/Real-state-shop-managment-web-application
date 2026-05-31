<?php

require_once __DIR__ . '/BaseModel.php';

class Report extends BaseModel
{
    /**
     * Sales summary between two dates.
     */
    public static function salesSummary(string $from, string $to): array
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS sale_count,
                    COALESCE(SUM(subtotal),0)     AS subtotal,
                    COALESCE(SUM(discount),0)     AS discount,
                    COALESCE(SUM(total_amount),0) AS total,
                    COALESCE(SUM(paid_amount),0)  AS paid,
                    COALESCE(SUM(due_amount),0)   AS due
             FROM sales
             WHERE status = 'completed' AND sale_date BETWEEN ? AND ?",
            [$from, $to]
        );
        return $row ?: [];
    }

    /**
     * Daily sales between two dates (for line/bar charts).
     */
    public static function dailySales(string $from, string $to): array
    {
        return Database::fetchAll(
            "SELECT sale_date,
                    COUNT(*) AS sale_count,
                    COALESCE(SUM(total_amount),0) AS total,
                    COALESCE(SUM(paid_amount),0)  AS paid,
                    COALESCE(SUM(due_amount),0)   AS due
             FROM sales
             WHERE status = 'completed' AND sale_date BETWEEN ? AND ?
             GROUP BY sale_date
             ORDER BY sale_date",
            [$from, $to]
        );
    }

    /**
     * Best-selling products by quantity and revenue.
     */
    public static function topProducts(string $from, string $to, int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));
        return Database::fetchAll(
            "SELECT p.id, p.name AS product_name, p.type AS product_type, p.unit,
                    SUM(si.quantity)    AS total_qty,
                    SUM(si.total_price) AS total_revenue
             FROM sale_items si
             JOIN sales s    ON s.id = si.sale_id
             JOIN products p ON p.id = si.product_id
             WHERE s.status = 'completed' AND s.sale_date BETWEEN ? AND ?
             GROUP BY p.id
             ORDER BY total_revenue DESC
             LIMIT $limit",
            [$from, $to]
        );
    }

    /**
     * Sales split by product type (rod vs cement).
     */
    public static function salesByType(string $from, string $to): array
    {
        return Database::fetchAll(
            "SELECT p.type AS product_type,
                    SUM(si.quantity)    AS total_qty,
                    SUM(si.total_price) AS total_revenue
             FROM sale_items si
             JOIN sales s    ON s.id = si.sale_id
             JOIN products p ON p.id = si.product_id
             WHERE s.status = 'completed' AND s.sale_date BETWEEN ? AND ?
             GROUP BY p.type",
            [$from, $to]
        );
    }

    /**
     * Purchase (stock inbound) summary between two dates.
     */
    public static function purchaseSummary(string $from, string $to): array
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS purchase_count,
                    COALESCE(SUM(quantity),0)   AS total_qty,
                    COALESCE(SUM(total_cost),0) AS total_cost
             FROM stock_inbound
             WHERE inbound_date BETWEEN ? AND ?",
            [$from, $to]
        );
        return $row ?: [];
    }

    /**
     * Payments collected between two dates.
     */
    public static function paymentsSummary(string $from, string $to): array
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS payment_count,
                    COALESCE(SUM(amount),0) AS total_collected
             FROM payments
             WHERE payment_date BETWEEN ? AND ?",
            [$from, $to]
        );
        return $row ?: [];
    }

    /**
     * Current stock valuation across all products.
     */
    public static function stockValuation(): array
    {
        $rows = Database::fetchAll(
            "SELECT product_id, product_name, product_type, unit,
                    current_stock, buy_price, sell_price,
                    (current_stock * buy_price)  AS stock_cost,
                    (current_stock * sell_price) AS stock_value
             FROM vw_current_stock
             ORDER BY product_type, product_name"
        );
        $totals = ['cost' => 0, 'value' => 0];
        foreach ($rows as $r) {
            $totals['cost']  += (float)$r['stock_cost'];
            $totals['value'] += (float)$r['stock_value'];
        }
        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Customers with outstanding dues.
     */
    public static function customerDues(): array
    {
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
    public static function profitEstimate(string $from, string $to): array
    {
        $row = Database::fetchOne(
            "SELECT COALESCE(SUM(si.total_price),0)          AS revenue,
                    COALESCE(SUM(si.quantity * p.buy_price),0) AS cost
             FROM sale_items si
             JOIN sales s    ON s.id = si.sale_id
             JOIN products p ON p.id = si.product_id
             WHERE s.status = 'completed' AND s.sale_date BETWEEN ? AND ?",
            [$from, $to]
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
