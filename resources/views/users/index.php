<?php
$roleTitles = $roles;
?>
<div class="page-heading">
    <div><div class="eyebrow">الأمان والتحكم</div><h1>إدارة المستخدمين والصلاحيات</h1><p>أنشئ حسابات الموظفين، ثم عيّن لكل مستخدم الشاشات والعمليات المسموح بها.</p></div>
    <a class="btn btn-primary" href="?page=users&action=create">＋ إضافة مستخدم</a>
</div>
<div class="notice" style="margin-bottom:16px">حساب الدكتور <b><?= Security::e(Auth::DOCTOR_EMAIL) ?></b> هو المدير الرئيسي، وله جميع الصلاحيات ولا يمكن تعطيله من هذه الشاشة.</div>
<div class="panel table-panel">
    <?php if (!$users): ?>
        <div class="empty-state"><h3>لا يوجد مستخدمون</h3><p>أضف حسابًا جديدًا للبدء.</p></div>
    <?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>الاسم</th><th>البريد الإلكتروني</th><th>الدور</th><th>الحالة</th><th>آخر دخول</th><th>الإجراءات</th></tr></thead><tbody>
        <?php foreach ($users as $user): $isAdmin = (int)$user['IsSuperAdmin'] === 1; ?>
            <tr>
                <td><b><?= Security::e($user['FullName']) ?></b><?= $isAdmin ? ' <small class="tiny-badge">مدير النظام</small>' : '' ?></td>
                <td dir="ltr" style="text-align:right"><?= Security::e($user['Email']) ?></td>
                <td><?= Security::e($roleTitles[$user['RoleName']] ?? ($isAdmin ? 'مدير النظام' : $user['RoleName'])) ?></td>
                <td><span class="state-pill <?= (int)$user['IsActive'] === 1 ? 'state-on' : 'state-off' ?>"><?= (int)$user['IsActive'] === 1 ? 'مفعّل' : 'معطّل' ?></span></td>
                <td><?= Security::e($user['LastLoginAt'] ?: 'لم يسجل الدخول بعد') ?></td>
                <td class="row-actions">
                    <?php if (!$isAdmin): ?>
                        <a class="action-link" href="?page=users&action=edit&id=<?= (int)$user['UserID'] ?>">تعديل</a>
                        <a class="action-link" href="?page=user_permissions&id=<?= (int)$user['UserID'] ?>">الصلاحيات</a>
                        <form method="post" action="?page=users&action=toggle" onsubmit="return confirm('هل تريد تغيير حالة هذا المستخدم؟')">
                            <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                            <input type="hidden" name="user_id" value="<?= (int)$user['UserID'] ?>">
                            <button class="action-link <?= (int)$user['IsActive'] === 1 ? 'danger-link' : '' ?>" type="submit"><?= (int)$user['IsActive'] === 1 ? 'تعطيل' : 'تفعيل' ?></button>
                        </form>
                    <?php else: ?><span class="action-disabled">صلاحيات كاملة</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</div>
