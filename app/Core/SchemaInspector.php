<?php
declare(strict_types=1);

final class SchemaInspector
{
    public function __construct(private PDO $db) {}

    public function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM sys.tables t JOIN sys.schemas s ON s.schema_id=t.schema_id WHERE s.name=N'dbo' AND t.name=?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }

    public function quote(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new InvalidArgumentException('اسم قاعدة بيانات غير صالح.');
        }
        return '[' . $identifier . ']';
    }

    public function columns(string $table): array
    {
        $sql = "
            SELECT c.name AS column_name, ty.name AS data_type,
                   c.max_length, c.precision, c.scale, c.is_nullable,
                   c.is_identity, c.is_computed,
                   dc.definition AS default_definition,
                   CASE WHEN pk.column_id IS NULL THEN 0 ELSE 1 END AS is_primary
            FROM sys.tables t
            JOIN sys.schemas s ON s.schema_id=t.schema_id
            JOIN sys.columns c ON c.object_id=t.object_id
            JOIN sys.types ty ON ty.user_type_id=c.user_type_id
            LEFT JOIN sys.default_constraints dc
              ON dc.parent_object_id=c.object_id AND dc.parent_column_id=c.column_id
            LEFT JOIN (
                SELECT ic.object_id, ic.column_id
                FROM sys.indexes i
                JOIN sys.index_columns ic ON ic.object_id=i.object_id AND ic.index_id=i.index_id
                WHERE i.is_primary_key=1
            ) pk ON pk.object_id=c.object_id AND pk.column_id=c.column_id
            WHERE s.name=N'dbo' AND t.name=?
            ORDER BY c.column_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$table]);
        return $stmt->fetchAll();
    }

    public function primaryKey(string $table): ?string
    {
        foreach ($this->columns($table) as $column) {
            if ((int)$column['is_primary'] === 1) {
                return (string)$column['column_name'];
            }
        }
        return null;
    }

    public function foreignKeys(string $table): array
    {
        $sql = "
            SELECT pc.name AS column_name, rt.name AS referenced_table,
                   rc.name AS referenced_column
            FROM sys.foreign_key_columns fkc
            JOIN sys.tables pt ON pt.object_id=fkc.parent_object_id
            JOIN sys.schemas ps ON ps.schema_id=pt.schema_id
            JOIN sys.columns pc ON pc.object_id=pt.object_id AND pc.column_id=fkc.parent_column_id
            JOIN sys.tables rt ON rt.object_id=fkc.referenced_object_id
            JOIN sys.columns rc ON rc.object_id=rt.object_id AND rc.column_id=fkc.referenced_column_id
            WHERE ps.name=N'dbo' AND pt.name=?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$table]);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['column_name']] = [
                'table' => $row['referenced_table'],
                'column' => $row['referenced_column'],
            ];
        }
        return $result;
    }

    public function foreignOptions(string $table, string $keyColumn): array
    {
        $tableQ = $this->quote($table);
        $keyQ = $this->quote($keyColumn);
        $columns = $this->columns($table);
        $labelColumn = $keyColumn;
        foreach ($columns as $col) {
            if (in_array(strtolower((string)$col['data_type']), ['nvarchar','varchar','nchar','char','text','ntext'], true)
                && (string)$col['column_name'] !== $keyColumn) {
                $labelColumn = (string)$col['column_name'];
                break;
            }
        }
        $labelQ = $this->quote($labelColumn);
        $extra = '';
        $extraNames = [];
        foreach (['AccLevel','AccTypeID','Nature'] as $optional) {
            foreach ($columns as $col) {
                if (strcasecmp((string)$col['column_name'], $optional) === 0) {
                    $extra .= ', ' . $this->quote((string)$col['column_name']);
                    $extraNames[] = (string)$col['column_name'];
                    break;
                }
            }
        }
        $sql = "SELECT TOP (300) {$keyQ} AS option_id, CAST({$labelQ} AS NVARCHAR(500)) AS option_label {$extra} FROM dbo.{$tableQ} ORDER BY {$labelQ}";
        $rows = $this->db->query($sql)->fetchAll();
        return $rows;
    }

    public function accountLockReasons(int|string $accountId): array
    {
        $reasons = [];
        $sql = "
            SELECT pt.name AS child_table, pc.name AS child_column
            FROM sys.foreign_key_columns fkc
            JOIN sys.tables pt ON pt.object_id=fkc.parent_object_id
            JOIN sys.schemas ps ON ps.schema_id=pt.schema_id
            JOIN sys.tables rt ON rt.object_id=fkc.referenced_object_id
            JOIN sys.schemas rs ON rs.schema_id=rt.schema_id
            JOIN sys.columns pc ON pc.object_id=pt.object_id AND pc.column_id=fkc.parent_column_id
            WHERE ps.name=N'dbo' AND rs.name=N'dbo' AND rt.name=N'CharAccount'
              AND pt.name <> N'CharAccount'";
        foreach ($this->db->query($sql)->fetchAll() as $fk) {
            $child = (string)$fk['child_table'];
            $column = (string)$fk['child_column'];
            $stmt = $this->db->prepare('SELECT COUNT_BIG(*) FROM dbo.' . $this->quote($child) . ' WHERE ' . $this->quote($column) . ' = ?');
            $stmt->execute([$accountId]);
            if ((int)$stmt->fetchColumn() > 0) {
                $reasons[] = $child;
            }
        }
        return array_values(array_unique($reasons));
    }
}
