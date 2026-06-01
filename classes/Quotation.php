<?php
require_once __DIR__ . '/BaseModel.php';

class Quotation extends BaseModel
{
    protected static string $table = 'quotations';

    public static function generateNumber(): string
    {
        $count = Database::fetchOne(
            "SELECT COUNT(*) AS c FROM quotations WHERE DATE(created_at) = CURDATE()"
        );
        $seq = str_pad((int)($count['c'] ?? 0) + 1, 4, '0', STR_PAD_LEFT);
        return 'QT-' . date('Ymd') . '-' . $seq;
    }

    public static function create(array $data, array $items): int|string
    {
        if (empty($items)) return 'NO_ITEMS';

        $customerName = trim($data['customer_name'] ?? '');
        $customerId   = ($data['customer_id'] ?? 0) ?: null;
        $quoteDate    = $data['quote_date']  ?? date('Y-m-d');
        $validDays    = max(1, (int)($data['valid_days']  ?? 7));
        $discount     = max(0, (float)($data['discount']  ?? 0));
        $note         = trim($data['note'] ?? '');

        $subtotal = 0;
        $valid    = [];
        foreach ($items as $it) {
            $pid   = (int)($it['product_id'] ?? 0);
            $qty   = (float)($it['quantity']   ?? 0);
            $price = (float)($it['unit_price']  ?? 0);
            $pname = trim($it['product_name']   ?? '');
            if ($pid <= 0 || $qty <= 0 || $price <= 0) return 'INVALID_ITEM';
            $valid[]   = ['product_id' => $pid, 'product_name' => $pname,
                          'quantity' => $qty, 'unit_price' => $price,
                          'total_price' => $qty * $price];
            $subtotal += $qty * $price;
        }

        $total    = max(0, $subtotal - $discount);
        $qNo      = self::generateNumber();
        $userId   = $_SESSION['user_id'] ?? null;

        Database::beginTransaction();
        try {
            $qId = Database::insert(
                'INSERT INTO quotations
                 (quote_number, customer_name, customer_id, quote_date, valid_days,
                  subtotal, discount, total_amount, note, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$qNo, $customerName, $customerId, $quoteDate, $validDays,
                 $subtotal, $discount, $total, $note, $userId]
            );
            foreach ($valid as $it) {
                Database::insert(
                    'INSERT INTO quotation_items
                     (quotation_id, product_id, product_name, quantity, unit_price, total_price)
                     VALUES (?,?,?,?,?,?)',
                    [$qId, $it['product_id'], $it['product_name'],
                     $it['quantity'], $it['unit_price'], $it['total_price']]
                );
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            return 'DB_ERROR';
        }
        return (int)$qId;
    }

    public static function getAll(array $f = []): array
    {
        $sql    = 'SELECT * FROM quotations WHERE 1=1';
        $params = [];
        if (!empty($f['status'])) { $sql .= ' AND status = ?'; $params[] = $f['status']; }
        if (!empty($f['search'])) {
            $sql .= ' AND (customer_name LIKE ? OR quote_number LIKE ?)';
            $params[] = '%'.$f['search'].'%';
            $params[] = '%'.$f['search'].'%';
        }
        $sql .= ' ORDER BY quote_date DESC, id DESC';
        return Database::fetchAll($sql, $params);
    }

    public static function getById(int $id): array|false
    {
        $q = Database::fetchOne('SELECT * FROM quotations WHERE id = ? LIMIT 1', [$id]);
        if (!$q) return false;
        $q['items'] = Database::fetchAll(
            'SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY id', [$id]
        );
        return $q;
    }

    public static function update(int $id, array $data, array $newItems): bool|string
    {
        $q = Database::fetchOne('SELECT status FROM quotations WHERE id = ? LIMIT 1', [$id]);
        if (!$q)                    return 'NOT_FOUND';
        if ($q['status'] !== 'active') return 'NOT_ACTIVE';
        if (empty($newItems))       return 'NO_ITEMS';

        $customerName = trim($data['customer_name'] ?? '');
        $quoteDate    = $data['quote_date']  ?? date('Y-m-d');
        $validDays    = max(1, (int)($data['valid_days']  ?? 7));
        $discount     = max(0, (float)($data['discount']  ?? 0));
        $note         = trim($data['note'] ?? '');

        $subtotal = 0;
        $valid    = [];
        foreach ($newItems as $it) {
            $pid   = (int)($it['product_id']   ?? 0);
            $qty   = (float)($it['quantity']   ?? 0);
            $price = (float)($it['unit_price'] ?? 0);
            $pname = trim($it['product_name']  ?? '');
            if ($pid <= 0 || $qty <= 0 || $price <= 0) return 'INVALID_ITEM';
            $valid[]   = ['product_id' => $pid, 'product_name' => $pname,
                          'quantity' => $qty, 'unit_price' => $price,
                          'total_price' => $qty * $price];
            $subtotal += $qty * $price;
        }
        $total = max(0, $subtotal - $discount);

        Database::beginTransaction();
        try {
            Database::execute('DELETE FROM quotation_items WHERE quotation_id = ?', [$id]);
            foreach ($valid as $it) {
                Database::insert(
                    'INSERT INTO quotation_items
                     (quotation_id, product_id, product_name, quantity, unit_price, total_price)
                     VALUES (?,?,?,?,?,?)',
                    [$id, $it['product_id'], $it['product_name'],
                     $it['quantity'], $it['unit_price'], $it['total_price']]
                );
            }
            Database::execute(
                'UPDATE quotations SET customer_name=?, quote_date=?, valid_days=?,
                  subtotal=?, discount=?, total_amount=?, note=? WHERE id=?',
                [$customerName, $quoteDate, $validDays, $subtotal, $discount, $total, $note, $id]
            );
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            return 'DB_ERROR';
        }
        return true;
    }

    public static function updateStatus(int $id, string $status): bool|string
    {
        $q = Database::fetchOne('SELECT status FROM quotations WHERE id = ? LIMIT 1', [$id]);
        if (!$q) return 'NOT_FOUND';
        if ($q['status'] !== 'active') return 'NOT_ACTIVE';
        if (!in_array($status, ['converted', 'cancelled'], true)) return 'INVALID_STATUS';
        Database::execute('UPDATE quotations SET status = ? WHERE id = ?', [$status, $id]);
        return true;
    }
}
