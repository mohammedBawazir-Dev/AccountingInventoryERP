<div class="page-heading">
    <div><div class="eyebrow">إدارة الحسابات</div><h1><?= $isEdit ? 'تعديل بيانات المستخدم' : 'إضافة مستخدم جديد' ?></h1><p>كل مستخدم جديد يبدأ دون صلاحيات حتى يحددها مدير النظام.</p></div>
    <a class="btn btn-light" href="?page=users">رجوع</a>
</div>
<div class="panel form-panel">
    <form method="post" action="?page=users&action=<?= $isEdit ? 'update' : 'store' ?>" class="data-form" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
        <?php if ($isEdit): ?><input type="hidden" name="user_id" value="<?= (int)$user['UserID'] ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="field-group"><label for="full_name">اسم المستخدم *</label><input id="full_name" name="full_name" maxlength="150" required value="<?= Security::e($user['FullName'] ?? '') ?>"></div>
            <div class="field-group"><label for="email">البريد الإلكتروني *</label><input id="email" type="email" name="email" maxlength="254" required dir="ltr" value="<?= Security::e($user['Email'] ?? '') ?>"></div>
            <div class="field-group"><label for="role_name">الدور الوظيفي *</label><select id="role_name" name="role_name" required><?php foreach ($roles as $key => $title): ?><option value="<?= Security::e($key) ?>" <?= ($user['RoleName'] ?? 'custom') === $key ? 'selected' : '' ?>><?= Security::e($title) ?></option><?php endforeach; ?></select></div>
            <div class="field-group"><label for="password">كلمة المرور <?= $isEdit ? '(اتركها فارغة للإبقاء على الحالية)' : '*' ?></label><input id="password" type="password" name="password" minlength="12" <?= $isEdit ? '' : 'required' ?> autocomplete="new-password"><small>12 حرفًا على الأقل. استخدم كلمة مرور مختلفة لكل مستخدم.</small></div>
            <div class="field-group"><label for="password_confirmation">تأكيد كلمة المرور <?= $isEdit ? '(عند تغييرها)' : '*' ?></label><input id="password_confirmation" type="password" name="password_confirmation" minlength="12" <?= $isEdit ? '' : 'required' ?> autocomplete="new-password"></div>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">حفظ المستخدم</button><a class="btn btn-light" href="?page=users">إلغاء</a></div>
    </form>
</div>
