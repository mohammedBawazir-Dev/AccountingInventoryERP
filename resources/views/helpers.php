<?php
declare(strict_types=1);

function erp_label(string $field): string
{
    static $labels = [
        'AccountID' => 'الحساب', 'accountID' => 'الحساب', 'AccCode' => 'كود الحساب', 'AccName' => 'اسم الحساب',
        'AccParent' => 'الحساب الأب', 'AccLevel' => 'مستوى الحساب', 'AccTypeID' => 'نوع الحساب', 'Nature' => 'طبيعة الحساب (1 مدين، 2 دائن)',
        'IsPostable' => 'يقبل الترحيل', 'Is_active' => 'حالة الحساب', 'is_active' => 'حالة التفعيل', 'is_System' => 'حساب نظامي', 'SystemKey' => 'مفتاح النظام',
        'CoinID' => 'العملة', 'CoinName' => 'اسم العملة', 'CoinCode' => 'رمز العملة', 'CoinsExchangeRate' => 'سعر الصرف', 'CoinSystem' => 'عملة النظام الأساسية',
        'BoxID' => 'رقم الصندوق', 'BoxName' => 'اسم الصندوق', 'BankID' => 'رقم البنك', 'BankName' => 'اسم البنك', 'AccountNumber' => 'رقم الحساب البنكي',
        'CustomersID' => 'رقم العميل', 'CusName' => 'اسم العميل', 'CusPhone' => 'هاتف العميل', 'CusAddress' => 'عنوان العميل',
        'SuplierID' => 'رقم المورد', 'SuplierName' => 'اسم المورد', 'SuplierPhone' => 'هاتف المورد', 'SuplierArea' => 'منطقة المورد', 'SuplierStoped' => 'موقوف',
        'itemID' => 'رقم الصنف', 'ItemID' => 'رقم الصنف', 'ItemName' => 'اسم الصنف', 'itemName' => 'اسم الصنف',
        'id' => 'المعرف', 'name' => 'الاسم', 'code' => 'الرمز', 'UnitID' => 'رقم الوحدة', 'UnitName' => 'اسم الوحدة',
        'StockID' => 'رقم المخزن', 'StockName' => 'اسم المخزن', 'stockID' => 'رقم المخزن', 'account_id' => 'الحساب',
        'openingBalancesID' => 'رقم الرصيد الافتتاحي', 'OPExchangeRate' => 'سعر الصرف', 'OPDebit' => 'مدين', 'OPCredit' => 'دائن', 'OPFiscalYear' => 'السنة المالية', 'OPData' => 'تاريخ الإدخال',
        'entryID' => 'رقم القيد', 'entryNo' => 'رقم القيد التسلسلي', 'entryDate' => 'تاريخ القيد', 'descriotion' => 'البيان',
        'JournalEntryID' => 'رقم القيد', 'accountID' => 'الحساب', 'debit' => 'مدين', 'credit' => 'دائن', 'description' => 'البيان',
        'purchase_invoice_id' => 'رقم فاتورة الشراء', 'sales_invoice_id' => 'رقم فاتورة البيع', 'invoice_number' => 'رقم الفاتورة', 'invoice_date' => 'تاريخ الفاتورة',
        'account_id' => 'حساب المورد/العميل', 'payment_account_id' => 'حساب الدفع', 'coin_id' => 'العملة', 'warehouse_id' => 'المخزن',
        'exchange_rate' => 'سعر الصرف', 'total_in_base_currency' => 'الإجمالي بالعملة الأساسية', 'payment_method' => 'طريقة الدفع',
        'items_total' => 'إجمالي الأصناف', 'discount_total' => 'إجمالي الخصم', 'expenses' => 'النفقات', 'tax_cost' => 'تكلفة الضريبة',
        'transportation' => 'تكلفة النقل', 'other_cost' => 'تكاليف أخرى', 'other_cost_description' => 'وصف التكاليف الأخرى',
        'statement' => 'البيان', 'reference' => 'المرجع', 'quantity' => 'الكمية', 'price' => 'السعر', 'cost_price' => 'تكلفة الوحدة',
        'discount' => 'الخصم', 'total' => 'الإجمالي', 'purchase_invoice_detail_id' => 'رقم التفصيل', 'sales_invoice_detail_id' => 'رقم التفصيل',
        'movement_id' => 'رقم الحركة', 'movement_detail_id' => 'رقم تفصيل الحركة', 'display_id' => 'رقم الحركة التسلسلي',
        'movement_type' => 'نوع الحركة', 'direction' => 'الاتجاه', 'movement_date' => 'تاريخ الحركة', 'document_number' => 'رقم المستند',
        'source_type' => 'نوع المصدر', 'source_id' => 'معرف المصدر', 'unit_cost' => 'تكلفة الوحدة', 'min_price' => 'أدنى سعر بيع',
        'max_price' => 'أعلى سعر بيع', 'sale_price' => 'سعر البيع المقترح', 'type_id' => 'النوع', 'unit_id' => 'الوحدة',
        'VoucherNumber' => 'رقم السند', 'VoucherDate' => 'تاريخ السند', 'CustomerID' => 'العميل', 'SuplierID' => 'المورد',
        'CoinID' => 'العملة', 'ExchangeRate' => 'سعر الصرف', 'Amount' => 'المبلغ', 'PaymentMethod' => 'طريقة الدفع',
        'PaymentAccountID' => 'الحساب المالي', 'BoxID' => 'الصندوق', 'BankID' => 'البنك', 'CheckNumber' => 'رقم الشيك',
        'Statement' => 'البيان', 'Reference' => 'المرجع', 'JournalEntryID' => 'القيد المحاسبي',
    ];
    return $labels[$field] ?? $field;
}

function erp_hidden_column(array $column): bool
{
    $name = strtolower((string)$column['column_name']);
    $type = strtolower((string)$column['data_type']);
    return (int)$column['is_computed'] === 1
        || in_array($type, ['timestamp','rowversion','image','binary','varbinary','geography','geometry'], true)
        || preg_match('/^(created_at|createdat|updated_at|updatedat)$/', $name) === 1;
}

function erp_is_flag(array $column): bool
{
    $name = strtolower((string)$column['column_name']);
    return strtolower((string)$column['data_type']) === 'bit'
        || in_array($name, ['is_active','isactive','is_system','issystem','ispostable','coinsystem','suplierstoped','cusstoped'], true);
}

function erp_format_value(string $field, mixed $value): string
{
    if ($value === null || $value === '') return '—';
    $fieldLower = strtolower($field);
    if (in_array($fieldLower, ['is_active','isactive','is_system','issystem','ispostable','coinsystem'], true)) {
        return (int)$value === 1 ? 'نعم' : 'لا';
    }
    if (in_array($fieldLower, ['suplierstoped','cusstoped'], true)) return (int)$value === 1 ? 'موقوف' : 'نشط';
    if ($fieldLower === 'nature') return (int)$value === 1 ? 'مدين' : 'دائن';
    if ($fieldLower === 'direction') return $value === 'in' ? 'دخول' : ($value === 'out' ? 'خروج' : (string)$value);
    if ($fieldLower === 'movement_type') {
        return [
            'supply' => 'توريد يدوي', 'issue' => 'صرف يدوي', 'purchase' => 'فاتورة شراء',
            'sale' => 'فاتورة بيع', 'purchase_return' => 'مرتجع شراء', 'sale_return' => 'مرتجع بيع'
        ][$value] ?? (string)$value;
    }
    return (string)$value;
}
