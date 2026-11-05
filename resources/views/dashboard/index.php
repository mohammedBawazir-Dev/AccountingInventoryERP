<?php
$tiles = [
    ['label'=>'العملاء','key'=>'customers','icon'=>'👥'],['label'=>'الموردون','key'=>'suppliers','icon'=>'🚚'],
    ['label'=>'الأصناف','key'=>'items','icon'=>'📦'],['label'=>'المخازن','key'=>'warehouses','icon'=>'🏬'],
    ['label'=>'فواتير البيع','key'=>'sales_invoices','icon'=>'🧾'],['label'=>'فواتير الشراء','key'=>'purchase_invoices','icon'=>'🛒'],
    ['label'=>'قيود اليومية','key'=>'journal_entries','icon'=>'📘'],['label'=>'حركات المخزون','key'=>'inventory_movements','icon'=>'🔄'],
];
$visibleTiles = array_values(array_filter($tiles, fn($tile) => Auth::can($tile['key'], 'view')));
?>
<div class="welcome-row">
    <div><h1>مرحبًا بك في نظام المحاسبة والمخزون</h1><p>نظرة عامة على بيانات النظام وحركة العمل المتاحة لحسابك.</p></div>
    <?php if (Auth::can('system_check', 'view')): ?><a class="btn btn-primary" href="?page=system_check">فحص قاعدة البيانات</a><?php endif; ?>
</div>

<?php if ($visibleTiles): ?>
<div class="stats-grid">
    <?php foreach ($visibleTiles as $tile): ?>
        <a class="stat-card" href="?page=<?= Security::e($tile['key']) ?>"><span class="stat-icon"><?= $tile['icon'] ?></span><span class="stat-label"><?= Security::e($tile['label']) ?></span><strong class="stat-number"><?= number_format((int)($counts[$tile['key']] ?? 0)) ?></strong></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($canSales || $canPurchases): ?>
<div class="summary-grid">
    <?php if ($canSales): ?><div class="panel summary-panel"><div class="panel-heading"><div><h2>إجمالي فواتير البيع</h2><p>بحسب الحقل المحول للعملة الأساسية إن كان متوفرًا</p></div><span class="summary-icon">↗</span></div><strong><?= number_format((float)$salesTotal, 2) ?></strong></div><?php endif; ?>
    <?php if ($canPurchases): ?><div class="panel summary-panel"><div class="panel-heading"><div><h2>إجمالي فواتير الشراء</h2><p>بحسب الحقل المحول للعملة الأساسية إن كان متوفرًا</p></div><span class="summary-icon">↙</span></div><strong><?= number_format((float)$purchaseTotal, 2) ?></strong></div><?php endif; ?>
</div>
<?php endif; ?>
<?php if ($visibleTiles): ?><div class="panel"><div class="panel-heading"><div><h2>الوصول السريع</h2><p>الشاشات التي يملك حسابك صلاحية عرضها</p></div></div><div class="quick-grid">
    <?php foreach ($visibleTiles as $tile): ?><a href="?page=<?= Security::e($tile['key']) ?>" class="quick-link"><span><?= $tile['icon'] ?></span><?= Security::e($tile['label']) ?><b>←</b></a><?php endforeach; ?>
</div></div><?php endif; ?>
<?php if ($canMovements): ?>
<div class="panel"><div class="panel-heading"><div><h2>آخر حركات المخزون</h2><p>آخر السجلات المسجلة في جدول حركات المخزون</p></div><a class="action-link" href="?page=inventory_movements">عرض الكل</a></div>
<?php if (!$recentMovements): ?><div class="empty-state"><p>لا توجد حركات مخزون مسجلة بعد.</p></div><?php else: $movementColumns = array_values(array_filter($recentMovements[0], fn($v, $k) => !in_array(strtolower((string)$k), ['created_at','updated_at','update_at'], true), ARRAY_FILTER_USE_BOTH)); $movementKeys = array_slice(array_keys($movementColumns), 0, 6); ?>
<div class="table-wrap"><table><thead><tr><?php foreach ($movementKeys as $field): ?><th><?= Security::e(erp_label((string)$field)) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach (array_slice($recentMovements,0,8) as $record): ?><tr><?php foreach ($movementKeys as $field): ?><td><?= Security::e(erp_format_value((string)$field, $record[$field] ?? null)) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
<?php endif; ?>
<div class="notice"><strong>ملاحظة تشغيلية:</strong> لوحة التحكم تعرض البيانات التي يملك حسابك صلاحية الوصول إليها، ولا تنشئ قيودًا محاسبية أو حركات مخزون تلقائيًا من الفواتير.</div>


<section class="project-team panel" aria-label="فريق إعداد المشروع">
    <div class="project-team-heading">
        <span class="project-team-icon">✦</span>
        <div><h2>إعداد وتنفيذ المشروع</h2><p>نظام المحاسبة والمخزون</p></div>
    </div>
    <div class="project-team-members">
        <div class="project-team-member"><strong>محمد سعيد باوزير</strong></div>
        <div class="project-team-member"><strong>أحمد محمود السنباني</strong></div>
    </div>
    <div class="project-team-major"><strong>علوم الحاسوب</strong></div>
</section>