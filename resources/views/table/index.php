<?php
$columns = array_values(array_filter($info['columns'], fn($c) => !erp_hidden_column($c)));
$displayColumns = $columns;
$pk = $info['primary_key'];
?>
<div class="page-heading">
    <div><div class="eyebrow"><?= Security::e($isDetails ? 'تفاصيل المستند' : ($module['group'] ?? 'النظام')) ?></div><h1><?= Security::e($module['title']) ?></h1>
        <p><?= $isDetails ? 'المستند الرئيسي: ' . Security::e($parentTitle) : 'إدارة السجلات والبحث والإضافة والتعديل والحذف.' ?></p>
    </div>
    <div class="heading-actions">
        <?php if ($isDetails): ?><a class="btn btn-light" href="?page=<?= Security::e($pageKey) ?>">العودة إلى القائمة</a><?php if ($canCreate): ?><a class="btn btn-primary" href="?page=<?= Security::e($pageKey) ?>&action=new_detail&id=<?= Security::e($parentId) ?>">＋ إضافة سطر</a><?php endif; ?>
        <?php elseif ($canCreate): ?><a class="btn btn-primary" href="?page=<?= Security::e($pageKey) ?>&action=create">＋ إضافة سجل جديد</a><?php endif; ?>
        <button class="btn btn-light" type="button" onclick="window.print()">طباعة</button>
    </div>
</div>
<div class="panel table-panel">
    <form class="toolbar" method="get">
        <input type="hidden" name="page" value="<?= Security::e($pageKey) ?>">
        <?php if ($isDetails): ?><input type="hidden" name="action" value="details"><input type="hidden" name="id" value="<?= Security::e($parentId) ?>"><?php endif; ?>
        <div class="search-box"><span>⌕</span><input type="search" name="search" value="<?= Security::e($search) ?>" placeholder="ابحث في بيانات الجدول..."></div>
        <button class="btn btn-secondary" type="submit">بحث</button>
        <span class="results-count">عدد النتائج المعروضة: <?= count($rows) ?> من أصل البيانات</span>
    </form>
    <?php if (!$rows): ?>
        <div class="empty-state"><div>▤</div><h3>لا توجد سجلات حتى الآن</h3><p>أضف أول سجل ليظهر هنا، أو غيّر عبارة البحث.</p></div>
    <?php else: ?>
        <div class="table-wrap"><table><thead><tr>
            <?php foreach ($displayColumns as $column): ?><th><?= Security::e(erp_label((string)$column['column_name'])) ?></th><?php endforeach; ?>
            <th class="actions-col">الإجراءات</th>
        </tr></thead><tbody>
        <?php foreach ($rows as $row): $recordId = (string)$row[$pk]; $accountId = (string)($row['AccountID'] ?? $row['accountID'] ?? ''); $locked = isset($accountLocks[$accountId]); ?>
            <tr <?= $pageKey === 'chart_accounts' ? 'data-account-id="' . Security::e($recordId) . '" data-account-parent="' . Security::e($row['AccParent'] ?? '') . '" data-depth="' . (int)($row['_tree_depth'] ?? max(0,(int)($row['AccLevel'] ?? 1)-1)) . '"' : '' ?>>
                <?php foreach ($displayColumns as $column): $field = (string)$column['column_name']; $value = $row[$field] ?? null; ?>
                    <td>
                        <?php if (erp_is_flag($column)): ?><span class="state-pill <?= (int)$value === 1 ? 'state-on' : 'state-off' ?>"><?= Security::e(erp_format_value($field, $value)) ?></span>
                        <?php elseif (strcasecmp($field, 'AccLevel') === 0): ?><span class="level-pill">مستوى <?= (int)$value ?></span>
                        <?php elseif (strcasecmp($field, 'AccCode') === 0): ?><span class="code-text"><?= Security::e($value) ?></span>
                        <?php elseif (strcasecmp($field, 'AccName') === 0 && $pageKey === 'chart_accounts'): ?><span class="tree-name" style="padding-right:<?= 10 + 18 * (int)($row['_tree_depth'] ?? max(0,(int)($row['AccLevel'] ?? 1)-1)) ?>px"><?php if (!empty($row['_has_children'])): ?><button type="button" class="tree-toggle" data-tree-toggle="<?= Security::e($recordId) ?>">−</button><?php else: ?><span class="tree-leaf">•</span><?php endif; ?><?= Security::e($value) ?></span>
                        <?php else: ?><span class="cell-value" title="<?= Security::e($value) ?>"><?= Security::e(function_exists('mb_strimwidth') ? mb_strimwidth((string)($value ?? '—'), 0, 55, '…', 'UTF-8') : (string)($value ?? '—')) ?></span><?php endif; ?>
                        <?php if ((int)($row['is_System'] ?? $row['is_system'] ?? 0) === 1): ?><small class="tiny-badge">حساب نظامي</small><?php endif; ?>
                        <?php if ($locked && $pageKey === 'chart_accounts'): ?><small class="tiny-badge badge-warning">مرتبط بسجل آخر</small><?php endif; ?>
                    </td>
                <?php endforeach; ?>
                <td class="row-actions">
                    <?php if (!$isDetails && !empty($module['details_table'])): ?><a class="action-link" href="?page=<?= Security::e($pageKey) ?>&action=details&id=<?= Security::e($recordId) ?>">التفاصيل</a><?php endif; ?>
                    <?php if ($canEdit && !($pageKey === 'chart_accounts' && ($locked || (int)($row['is_System'] ?? $row['is_system'] ?? 0) === 1))): ?>
                        <a class="action-link" href="?page=<?= Security::e($pageKey) ?>&action=<?= $isDetails ? 'edit_detail' : 'edit' ?>&<?= $isDetails ? 'id=' . Security::e($parentId) . '&detail_id=' . Security::e($recordId) : 'id=' . Security::e($recordId) ?>">تعديل</a>
                    <?php elseif ($pageKey === 'chart_accounts' && ($locked || (int)($row['is_System'] ?? $row['is_system'] ?? 0) === 1)): ?><span class="action-disabled">محمي</span><?php endif; ?>
                    <?php if ($canDelete && !($pageKey === 'chart_accounts' && ($locked || (int)($row['is_System'] ?? $row['is_system'] ?? 0) === 1))): ?>
                    <form method="post" action="?page=<?= Security::e($pageKey) ?>&action=<?= $isDetails ? 'delete_detail' : 'delete' ?>" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                        <input type="hidden" name="record_id" value="<?= Security::e($recordId) ?>">
                        <?php if ($isDetails): ?><input type="hidden" name="parent_id" value="<?= Security::e($parentId) ?>"><input type="hidden" name="detail_id" value="<?= Security::e($recordId) ?>"><?php endif; ?>
                        <button class="action-link danger-link" type="submit">حذف</button>
                    </form><?php endif; ?>
                    <?php if (!$isDetails && $canEdit && !$locked && $statusColumn && array_key_exists($statusColumn, $row)): ?>
                        <form method="post" action="?page=<?= Security::e($pageKey) ?>&action=toggle"><input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>"><input type="hidden" name="record_id" value="<?= Security::e($recordId) ?>"><button class="action-link" type="submit">تغيير الحالة</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <div class="table-foot">يتم عرض أحدث 200 سجل كحد أقصى. استخدم البحث لتضييق النتائج.</div>
    <?php endif; ?>
</div>
