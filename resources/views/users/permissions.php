<div class="page-heading">
    <div><div class="eyebrow">التحكم الدقيق بالوصول</div><h1>صلاحيات المستخدم</h1><p><?= Security::e($user['FullName']) ?> · <span dir="ltr"><?= Security::e($user['Email']) ?></span></p></div>
    <a class="btn btn-light" href="?page=users">رجوع للمستخدمين</a>
</div>
<?php if ((int)$user['IsSuperAdmin'] === 1): ?>
    <div class="notice">هذا الحساب مدير النظام الرئيسي، ولذلك يمتلك جميع الصلاحيات تلقائيًا ولا يحتاج إلى ضبط يدوي.</div>
<?php else: ?>
<form method="post" action="?page=user_permissions&id=<?= (int)$user['UserID'] ?>" class="panel">
    <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
    <input type="hidden" name="user_id" value="<?= (int)$user['UserID'] ?>">
    <p class="muted-text">حدد ما يستطيع المستخدم فعله في كل شاشة. اختيار الإضافة أو التعديل أو الحذف يمنحه حق عرض الشاشة تلقائيًا.</p>
    <div class="table-wrap">
        <table class="permissions-table"><thead><tr><th>القسم</th><th>الشاشة</th><th>عرض</th><th>إضافة</th><th>تعديل</th><th>حذف</th></tr></thead><tbody>
        <?php foreach ($modules as $key => $module): $p = $permissions[$key] ?? []; ?>
            <tr>
                <td><?= Security::e($module['group'] ?? 'النظام') ?></td>
                <td><b><?= Security::e($module['title']) ?></b></td>
                <?php foreach (['view'=>'عرض','create'=>'إضافة','edit'=>'تعديل','delete'=>'حذف'] as $action => $label): ?>
                    <td><label class="permission-check" title="<?= Security::e($label) ?>"><input type="checkbox" name="permissions[<?= Security::e($key) ?>][<?= $action ?>]" value="1" <?= !empty($p[$action]) ? 'checked' : '' ?>><span class="sr-only"><?= Security::e($label) ?></span></label></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">حفظ الصلاحيات</button><a class="btn btn-light" href="?page=users">إلغاء</a></div>
</form>
<?php endif; ?>
