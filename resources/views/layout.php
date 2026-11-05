<?php
require_once __DIR__ . '/helpers.php';
$allModules = Modules::all();
$groups = [];
foreach ($allModules as $key => $item) {
    if (Auth::can($key, 'view')) $groups[$item['group']][] = ['key' => $key] + $item;
}
$currentPage = (string)($_GET['page'] ?? 'dashboard');
$currentUser = Auth::currentUser();
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Security::e($pageTitle ?? 'نظام المحاسبة والمخزون') ?> | ERP</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand"><div class="brand-icon">ERP</div><div><strong>نظام المحاسبة والمخزون</strong><small>Accounting & Inventory</small></div></div>
        <?php if (Auth::can('dashboard', 'view')): ?><a class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="?page=dashboard"><span>▦</span> لوحة التحكم</a><?php endif; ?>
        <?php foreach ($groups as $groupName => $items): ?>
            <div class="nav-caption"><?= Security::e($groupName) ?></div>
            <?php foreach ($items as $item): ?>
                <a class="nav-link <?= $currentPage === $item['key'] ? 'active' : '' ?>" href="?page=<?= Security::e($item['key']) ?>"><span><?= Security::e($item['icon']) ?></span><?= Security::e($item['title']) ?></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <?php if (Auth::isSuperAdmin()): ?>
            <div class="nav-caption">إدارة النظام</div>
            <a class="nav-link <?= in_array($currentPage, ['users','user_permissions'], true) ? 'active' : '' ?>" href="?page=users"><span>👤</span> المستخدمون والصلاحيات</a>
        <?php endif; ?>
        <?php if (Auth::can('system_check', 'view')): ?><div class="sidebar-bottom"><a class="nav-link <?= $currentPage === 'system_check' ? 'active' : '' ?>" href="?page=system_check"><span>⚙</span> فحص الاتصال</a></div><?php endif; ?>
    </aside>

    <main class="main-area">
        <header class="topbar">
            <button class="menu-button" type="button" onclick="document.getElementById('sidebar').classList.toggle('show')">☰</button>
            <div><div class="top-title"><?= Security::e($pageTitle ?? 'لوحة التحكم') ?></div><div class="top-subtitle">نظام إدارة الموارد المحاسبية والمخزنية</div></div>
            <div class="topbar-spacer"></div><span class="local-chip">SQL Server · PHP</span>
            <div class="user-menu" style="display:flex;align-items:center;gap:10px;margin-right:12px;flex-wrap:wrap">
                <span style="font-size:13px;color:var(--muted,#64748b)"><?= Security::e($currentUser['FullName'] ?? 'المستخدم') ?> · <?= Security::e($currentUser['Email'] ?? '') ?><?= Auth::isSuperAdmin() ? ' · مدير النظام' : '' ?></span>
                <?php if (!Auth::isSuperAdmin()): ?><a class="btn btn-light btn-sm" href="?page=change_password">تغيير كلمة المرور</a><?php endif; ?>
                <form method="post" action="?page=logout" style="margin:0"><input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>"><button type="submit" class="btn btn-light btn-sm">تسجيل الخروج</button></form>
            </div>
        </header>
        <section class="page-content">
            <?php foreach ($flashes as $flash): ?><div class="alert alert-<?= Security::e($flash['type']) ?>"><?= Security::e($flash['message']) ?></div><?php endforeach; ?>
            <?= $content ?>
        </section>
        <footer class="footer">Accounting & Inventory ERP · PHP + SQL Server</footer>
    </main>
</div>
<script src="assets/app.js"></script>
</body>
</html>
