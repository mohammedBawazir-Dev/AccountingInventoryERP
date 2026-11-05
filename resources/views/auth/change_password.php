<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تغيير كلمة المرور | ERP</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f3f5fa;font-family:Tahoma,Arial,sans-serif;color:#1f2937;padding:20px}
        .card{width:min(100%,460px);background:#fff;border-radius:18px;padding:32px;box-shadow:0 12px 40px #18243b15}.brand{width:58px;height:58px;border-radius:16px;background:#e9efff;color:#294caa;display:grid;place-items:center;font-weight:800;font-size:19px;margin-bottom:18px}
        h1{font-size:22px;margin:0 0 9px}.muted{color:#687386;line-height:1.8;font-size:13px;margin:0 0 20px}label{display:block;font-size:13px;font-weight:700;margin:15px 0 7px}input{width:100%;padding:13px;border:1px solid #d9dfeb;border-radius:10px;font:inherit}.btn{width:100%;border:0;border-radius:10px;padding:14px;background:#294caa;color:white;font:inherit;font-weight:700;margin-top:22px;cursor:pointer}.alert{padding:12px;border-radius:9px;background:#fff0f0;color:#a32929;margin-bottom:15px;font-size:13px}
    </style>
</head>
<body><main class="card"><div class="brand">ERP</div><h1>تغيير كلمة المرور</h1><p class="muted">استخدم كلمة مرور قوية لا تقل عن 12 حرفًا، ولا تشاركها مع الآخرين.</p>
<?php if ($error !== ''): ?><div class="alert"><?= Security::e($error) ?></div><?php endif; ?>
<form method="post" action="?page=change_password" autocomplete="off"><input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
<label for="current_password">كلمة المرور الحالية</label><input id="current_password" type="password" name="current_password" required autocomplete="current-password">
<label for="new_password">كلمة المرور الجديدة</label><input id="new_password" type="password" name="new_password" minlength="12" required autocomplete="new-password">
<label for="password_confirmation">تأكيد كلمة المرور الجديدة</label><input id="password_confirmation" type="password" name="password_confirmation" minlength="12" required autocomplete="new-password">
<button class="btn" type="submit">حفظ كلمة المرور الجديدة</button></form></main></body></html>
