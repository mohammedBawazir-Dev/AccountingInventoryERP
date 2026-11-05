<?php
declare(strict_types=1);

final class Modules
{
    public static function all(): array
    {
        return [
            'currencies' => ['title' => 'العملات', 'table' => 'Coins', 'icon' => '💱', 'group' => 'الإعدادات'],
            'boxes' => ['title' => 'الصناديق', 'table' => 'Boxes', 'icon' => '💵', 'group' => 'الإعدادات'],
            'banks' => ['title' => 'البنوك', 'table' => 'Banks', 'icon' => '🏦', 'group' => 'الإعدادات'],
            'chart_accounts' => ['title' => 'دليل الحسابات', 'table' => 'CharAccount', 'icon' => '🌳', 'group' => 'الإعدادات', 'tree' => true],
            'opening_balances' => ['title' => 'الأرصدة الافتتاحية', 'table' => 'OpeningBalances', 'icon' => '⚖️', 'group' => 'المحاسبة'],
            'suppliers' => ['title' => 'الموردون', 'table' => 'Suppliers', 'icon' => '🚚', 'group' => 'البيانات الأساسية'],
            'customers' => ['title' => 'العملاء', 'table' => 'Customers', 'icon' => '👥', 'group' => 'البيانات الأساسية'],
            'items' => ['title' => 'الأصناف', 'table_candidates' => ['Item', 'Items'], 'icon' => '📦', 'group' => 'المخزون'],
            'types' => ['title' => 'الأنواع', 'table' => 'type', 'icon' => '🗂️', 'group' => 'المخزون'],
            'units' => ['title' => 'الوحدات', 'table' => 'units', 'icon' => '📏', 'group' => 'المخزون'],
            'warehouses' => ['title' => 'المخازن', 'table' => 'Stocks', 'icon' => '🏬', 'group' => 'المخزون'],
            'purchase_invoices' => ['title' => 'فواتير الشراء', 'table' => 'purchaseInvoices', 'icon' => '🧾', 'group' => 'الفواتير', 'details_table' => 'purchase_invoice_details', 'details_fk' => 'purchase_invoice_id', 'details_pk' => 'purchase_invoice_id'],
            'sales_invoices' => ['title' => 'فواتير البيع', 'table' => 'sales_invoices', 'icon' => '🧾', 'group' => 'الفواتير', 'details_table' => 'sales_invoice_details', 'details_fk' => 'sales_invoice_id', 'details_pk' => 'sales_invoice_id'],
            'journal_entries' => ['title' => 'قيود اليومية', 'table' => 'Journal_Entries', 'icon' => '📘', 'group' => 'المحاسبة', 'details_table' => 'JournalEntrryLine', 'details_fk' => 'entryID', 'details_pk' => 'entryID'],
            'receipt_vouchers' => ['title' => 'سندات القبض', 'table' => 'ReceiptVouchers', 'icon' => '⬇️', 'group' => 'السندات'],
            'payment_vouchers' => ['title' => 'سندات الصرف', 'table' => 'PaymentVouchers', 'icon' => '⬆️', 'group' => 'السندات'],
            'inventory_movements' => ['title' => 'حركات المخزون', 'table' => 'inventory_movements', 'icon' => '🔄', 'group' => 'المخزون', 'details_table' => 'inventory_movement_details', 'details_fk' => 'movement_id', 'details_pk' => 'movement_id'],
        ];
    }

    public static function get(string $key): ?array
    {
        $all = self::all();
        return $all[$key] ?? null;
    }

    public static function resolveTable(PDO $db, array $module): string
    {
        $candidates = $module['table_candidates'] ?? [$module['table'] ?? ''];
        foreach ($candidates as $candidate) {
            $stmt = $db->prepare("SELECT 1 FROM sys.tables t JOIN sys.schemas s ON s.schema_id=t.schema_id WHERE s.name=N'dbo' AND t.name=?");
            $stmt->execute([$candidate]);
            if ($stmt->fetchColumn()) {
                return (string)$candidate;
            }
        }
        return (string)($candidates[0] ?? '');
    }
}
