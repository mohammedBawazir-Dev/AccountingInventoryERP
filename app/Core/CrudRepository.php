<?php
declare(strict_types=1);

final class CrudRepository
{
    public function __construct(private PDO $db, private SchemaInspector $schema) {}

    public function tableInfo(string $table): array
    {
        $columns = $this->schema->columns($table);
        if (!$columns) {
            throw new RuntimeException("لم يتم العثور على الجدول dbo.{$table} في قاعدة البيانات.");
        }
        $primaryKey = $this->schema->primaryKey($table);
        if ($primaryKey === null) {
            throw new RuntimeException("الجدول dbo.{$table} لا يحتوي على مفتاح أساسي؛ لا يمكن تفعيل عمليات التعديل والحذف بأمان.");
        }
        return [
            'columns' => $columns,
            'primary_key' => $primaryKey,
            'foreign_keys' => $this->schema->foreignKeys($table),
        ];
    }

    public function rows(string $table, string $search = '', ?array $where = null): array
    {
        $info = $this->tableInfo($table);
        $params = [];
        $conditions = [];
        if ($where !== null) {
            $conditions[] = $this->schema->quote((string)$where['column']) . ' = :parent_value';
            $params[':parent_value'] = $where['value'];
        }

        if ($search !== '') {
            $likeParts = [];
            $index = 0;
            foreach ($info['columns'] as $column) {
                $type = strtolower((string)$column['data_type']);
                if (in_array($type, ['image','binary','varbinary','timestamp','rowversion','geography','geometry'], true)) {
                    continue;
                }
                $placeholder = ':search_' . $index++;
                $likeParts[] = 'TRY_CONVERT(NVARCHAR(4000), ' . $this->schema->quote((string)$column['column_name']) . ') LIKE ' . $placeholder;
                $params[$placeholder] = '%' . $search . '%';
            }
            if ($likeParts) {
                $conditions[] = '(' . implode(' OR ', $likeParts) . ')';
            }
        }

        $sql = 'SELECT TOP (200) * FROM dbo.' . $this->schema->quote($table);
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY ' . $this->schema->quote($info['primary_key']) . ' DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(string $table, int|string $id): ?array
    {
        $info = $this->tableInfo($table);
        $sql = 'SELECT * FROM dbo.' . $this->schema->quote($table)
            . ' WHERE ' . $this->schema->quote($info['primary_key']) . ' = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function save(string $table, array $input, int|string|null $id = null, array $forced = []): int|string|null
    {
        $info = $this->tableInfo($table);
        $columns = $info['columns'];
        $pk = $info['primary_key'];
        $isUpdate = $id !== null;

        $existing = $isUpdate ? $this->find($table, $id) : null;
        if ($isUpdate && !$existing) {
            throw new RuntimeException('السجل المطلوب تعديله غير موجود.');
        }

        $data = array_merge($input, $forced);
        if (strcasecmp($table, 'CharAccount') === 0) {
            $data = $this->prepareAccountData($data, $existing);
            if ($isUpdate) {
                $lockReasons = $this->schema->accountLockReasons($id);
                $isSystem = (int)($existing['is_System'] ?? $existing['is_system'] ?? 0) === 1;
                if ($isSystem || $lockReasons) {
                    throw new RuntimeException('لا يمكن تعديل هذا الحساب لأنه حساب نظامي أو مرتبط بسجل آخر (مثل صندوق أو بنك أو عميل أو مورد).');
                }
            }
        }

        $allowed = [];
        foreach ($columns as $column) {
            $name = (string)$column['column_name'];
            $type = strtolower((string)$column['data_type']);
            if ((int)$column['is_identity'] === 1 || (int)$column['is_computed'] === 1
                || in_array($type, ['timestamp','rowversion'], true)
                || preg_match('/^(created_at|createdat|opeData)$/i', $name)) {
                continue;
            }
            if ($isUpdate && strcasecmp($name, $pk) === 0) {
                continue;
            }
            $allowed[$name] = $column;
        }

        $values = [];
        foreach ($allowed as $name => $column) {
            if (!array_key_exists($name, $data)) {
                continue;
            }
            $value = $data[$name];
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '' && (int)$column['is_nullable'] === 0 && $column['default_definition'] !== null) {
                    continue; // Let SQL Server apply the column default.
                }
                if ($value === '' && (int)$column['is_nullable'] === 1) {
                    $value = null;
                }
            }
            if ($value === '' && (int)$column['is_nullable'] === 1) {
                $value = null;
            }
            $type = strtolower((string)$column['data_type']);
            if ($type === 'bit' && $value !== null) {
                $value = in_array((string)$value, ['1','true','on'], true) ? 1 : 0;
            }
            $values[$name] = $value;
        }

        foreach ($allowed as $name => $column) {
            if ((int)$column['is_nullable'] === 0
                && $column['default_definition'] === null
                && !array_key_exists($name, $values)) {
                throw new RuntimeException('الحقل ' . $name . ' مطلوب.');
            }
            if ((int)$column['is_nullable'] === 0
                && array_key_exists($name, $values)
                && $values[$name] === null) {
                throw new RuntimeException('الحقل ' . $name . ' لا يقبل قيمة فارغة.');
            }
        }

        $this->calculateDetailTotal($table, $values);

        if (!$values) {
            throw new RuntimeException('لا توجد حقول قابلة للحفظ في هذا النموذج.');
        }

        $quotedColumns = array_map(fn($name) => $this->schema->quote((string)$name), array_keys($values));
        $placeholders = [];
        $params = [];
        $i = 0;
        foreach ($values as $name => $value) {
            $placeholder = ':v' . $i++;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $value;
        }

        if ($isUpdate) {
            $sets = [];
            $params[':record_id'] = $id;
            foreach (array_keys($values) as $index => $name) {
                $sets[] = $this->schema->quote((string)$name) . ' = :v' . $index;
            }
            $sql = 'UPDATE dbo.' . $this->schema->quote($table)
                . ' SET ' . implode(', ', $sets)
                . ' WHERE ' . $this->schema->quote($pk) . ' = :record_id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $id;
        }

        $sql = 'INSERT INTO dbo.' . $this->schema->quote($table)
            . ' (' . implode(', ', $quotedColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $identity = $this->db->query('SELECT SCOPE_IDENTITY()')->fetchColumn();
        return $identity !== false && $identity !== null ? (string)$identity : null;
    }

    private function prepareAccountData(array $data, ?array $existing): array
    {
        $parent = $data['AccParent'] ?? null;
        if ($parent === '' || $parent === '0') {
            $parent = null;
        }
        if ($parent === null) {
            $data['AccLevel'] = 1;
            if (!$existing) {
                $data['Nature'] = $data['Nature'] ?? 1;
            }
            $data['AccParent'] = null;
            return $data;
        }

        $stmt = $this->db->prepare('SELECT AccountID, AccLevel, AccTypeID, Nature FROM dbo.CharAccount WHERE AccountID = ?');
        $stmt->execute([$parent]);
        $parentRow = $stmt->fetch();
        if (!$parentRow) {
            throw new RuntimeException('الحساب الأب المحدد غير موجود.');
        }
        $level = (int)$parentRow['AccLevel'] + 1;
        if ($level > 5) {
            throw new RuntimeException('لا يمكن إنشاء مستوى يتجاوز المستوى الخامس.');
        }
        $data['AccParent'] = $parentRow['AccountID'];
        $data['AccLevel'] = $level;
        $data['AccTypeID'] = $parentRow['AccTypeID'];
        $data['Nature'] = $parentRow['Nature'];
        return $data;
    }

    private function calculateDetailTotal(string $table, array &$values): void
    {
        $lower = strtolower($table);
        if (in_array($lower, ['purchase_invoice_details','sales_invoice_details'], true)
            && isset($values['quantity'], $values['price']) && array_key_exists('total', $values)) {
            $quantity = (float)$values['quantity'];
            $price = (float)$values['price'];
            $discount = (float)($values['discount'] ?? 0);
            $values['total'] = max(0, ($quantity * $price) - $discount);
        }
        if ($lower === 'inventory_movement_details'
            && isset($values['quantity'], $values['unit_cost']) && array_key_exists('total', $values)) {
            $values['total'] = max(0, (float)$values['quantity'] * (float)$values['unit_cost']);
        }
    }

    public function delete(string $table, int|string $id): void
    {
        $info = $this->tableInfo($table);
        $row = $this->find($table, $id);
        if (!$row) {
            throw new RuntimeException('السجل المطلوب حذفه غير موجود.');
        }
        if (strcasecmp($table, 'CharAccount') === 0) {
            $isSystem = (int)($row['is_System'] ?? $row['is_system'] ?? 0) === 1;
            if ($isSystem) {
                throw new RuntimeException('لا يمكن حذف الحسابات النظامية.');
            }
            $locks = $this->schema->accountLockReasons($id);
            if ($locks) {
                throw new RuntimeException('لا يمكن حذف الحساب؛ يوجد ارتباط من جدول: ' . implode(', ', $locks));
            }
        }
        $sql = 'DELETE FROM dbo.' . $this->schema->quote($table)
            . ' WHERE ' . $this->schema->quote($info['primary_key']) . ' = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
    }

    public function count(string $table): int
    {
        if (!$this->schema->tableExists($table)) {
            return 0;
        }
        return (int)$this->db->query('SELECT COUNT_BIG(*) FROM dbo.' . $this->schema->quote($table))->fetchColumn();
    }

    public function toggle(string $table, int|string $id, string $column, bool $inverse = false): void
    {
        $info = $this->tableInfo($table);
        $columnFound = false;
        foreach ($info['columns'] as $col) {
            if (strcasecmp((string)$col['column_name'], $column) === 0) {
                $columnFound = true;
                $column = (string)$col['column_name'];
                break;
            }
        }
        if (!$columnFound) {
            throw new RuntimeException('حقل الحالة غير موجود في الجدول.');
        }
        $row = $this->find($table, $id);
        if (!$row) {
            throw new RuntimeException('السجل غير موجود.');
        }
        $current = (int)$row[$column];
        $next = $current === 1 ? 0 : 1;
        if ($inverse) {
            $next = $current === 1 ? 0 : 1;
        }
        $sql = 'UPDATE dbo.' . $this->schema->quote($table)
            . ' SET ' . $this->schema->quote($column) . ' = ? WHERE ' . $this->schema->quote($info['primary_key']) . ' = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$next, $id]);
    }
}
