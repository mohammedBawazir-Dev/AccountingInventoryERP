<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسجيل الدخول | ERP</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f3f5fa;font-family:Tahoma,Arial,sans-serif;color:#1f2937;padding:20px}
        .card{width:min(100%,430px);background:#fff;border-radius:18px;padding:32px;box-shadow:0 12px 40px #18243b15}
        .brand{width:58px;height:58px;border-radius:16px;background:#e9efff;color:#294caa;display:grid;place-items:center;font-weight:800;font-size:19px;margin-bottom:18px}
        h1{font-size:23px;margin:0 0 9px}.muted{color:#687386;line-height:1.8;font-size:14px;margin:0 0 22px}
        label{display:block;font-size:14px;font-weight:700;margin:15px 0 7px}input{width:100%;padding:13px;border:1px solid #d9dfeb;border-radius:10px;font:inherit;outline:none}input:focus{border-color:#4169d8;box-shadow:0 0 0 3px #4169d81c}
        .btn{width:100%;border:0;border-radius:10px;padding:14px;background:#294caa;color:white;font:inherit;font-weight:700;margin-top:22px;cursor:pointer}
        .alert{padding:12px 14px;border-radius:10px;background:#fff0f0;color:#a32929;margin-bottom:15px;font-size:14px;line-height:1.7}.footer{text-align:center;font-size:12px;color:#94a0b2;margin-top:22px}
    </style>
</head>
<body>
<main class="card">
    <div class="brand">ERP</div>
    <h1>تسجيل الدخول</h1>
    <p class="muted">حساب مدير النظام يدخل بالبريد الإلكتروني المحدد فقط. أما الحسابات الأخرى فتستخدم البريد وكلمة المرور.</p>
    <?php if ($error !== ''): ?><div class="alert"><?= Security::e($error) ?></div><?php endif; ?>
    <form method="post" action="?page=login" autocomplete="on">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
        <label for="email">البريد الإلكتروني</label>
        <input id="email" type="email" name="email" value="<?= Security::e(($email ?? '') !== '' ? $email : 'labeeb78@gmail.com') ?>" required maxlength="254" autocomplete="username" dir="ltr">
        <div id="password-group" hidden>
            <label for="password">كلمة المرور</label>
            <input id="password" type="password" name="password" autocomplete="current-password" disabled>
        </div>
        <button class="btn" type="submit">دخول</button>
    </form>
    <div class="footer">Accounting &amp; Inventory ERP</div>
</main>
<script>
(() => {
    const doctorEmail = 'labeeb78@gmail.com';
    const emailInput = document.getElementById('email');
    const passwordGroup = document.getElementById('password-group');
    const passwordInput = document.getElementById('password');
    const updatePasswordVisibility = () => {
        const isDoctor = emailInput.value.trim().toLowerCase() === doctorEmail;
        passwordGroup.hidden = isDoctor;
        passwordInput.disabled = isDoctor;
        passwordInput.required = !isDoctor;
        if (isDoctor) passwordInput.value = '';
    };
    emailInput.addEventListener('input', updatePasswordVisibility);
    updatePasswordVisibility();
})();
</script>
</body>
</html>
