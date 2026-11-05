<div class="panel diagnostic-panel">
    <div class="eyebrow">403 · الوصول مرفوض</div>
    <h1>ليس لديك صلاحية لفتح هذه الصفحة</h1>
    <p><?= Security::e($message ?? 'تواصل مع مدير النظام لمنحك الصلاحية المطلوبة.') ?></p>
    <a class="btn btn-primary" href="?page=<?= Security::e(Auth::landingPage()) ?>">العودة</a>
</div>
