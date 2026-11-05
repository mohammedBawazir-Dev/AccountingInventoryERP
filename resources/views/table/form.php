<?php
require_once __DIR__ . '/../helpers.php';
$pk = $info['primary_key'];
$foreignKeys = $info['foreign_keys'];
$actionUrl = '?page=' . rawurlencode($pageKey) . '&action=' . rawurlencode($saveAction);
if ($isDetails && $parentId !== null) $actionUrl .= '&id=' . rawurlencode($parentId);
function erp_default_value(?string $default): string
{
    if ($default === null) return '';
    $value = trim($default);
    while (strlen($value) >= 2 && $value[0] === '(' && $value[strlen($value)-1] === ')') $value = substr($value, 1, -1);
    return trim($value, "' ");
}
?>
<div class="page-heading">
    <div><div class="eyebrow"><?= Security::e($module['group'] ?? 'النظام') ?></div><h1><?= $isEdit ? 'تعديل' : 'إضافة' ?> <?= Security::e($module['title']) ?></h1><p>أدخل البيانات ثم اضغط حفظ.</p></div>
    <a class="btn btn-light" href="<?= $isDetails ? '?page=' . Security::e($pageKey) . '&action=details&id=' . Security::e($parentId) : '?page=' . Security::e($pageKey) ?>">رجوع</a>
</div>
<div class="panel form-panel">
    <form method="post" action="<?= Security::e($actionUrl) ?>" class="data-form" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
        <?php if ($isEdit): ?>
            <?php if ($isDetails): ?><input type="hidden" name="detail_id" value="<?= Security::e($row[$pk] ?? '') ?>">
            <?php else: ?><input type="hidden" name="record_id" value="<?= Security::e($row[$pk] ?? '') ?>"><?php endif; ?>
        <?php endif; ?>
        <?php if ($isDetails && $parentId !== null): ?><input type="hidden" name="parent_id" value="<?= Security::e($parentId) ?>"><?php endif; ?>

        <div class="form-grid">
        <?php foreach ($info['columns'] as $column):
            $field = (string)$column['column_name'];
            $type = strtolower((string)$column['data_type']);
            $value = $row[$field] ?? erp_default_value($column['default_definition'] ?? null);
            if (erp_hidden_column($column) || (int)$column['is_identity'] === 1 || (int)$column['is_computed'] === 1) continue;
            if ($isEdit && strcasecmp($field, $pk) === 0) continue;
            if ($isDetails && $parentField !== null && strcasecmp($field, $parentField) === 0) continue;
            $required = (int)$column['is_nullable'] === 0 && $column['default_definition'] === null;
            $isFlag = erp_is_flag($column);
            $isFK = isset($foreignKeys[$field]);
            $wide = in_array($type, ['text','ntext'], true) || (int)$column['max_length'] > 1000;
            ?>
            <div class="field-group <?= $wide ? 'field-wide' : '' ?>">
                <label for="f_<?= Security::e($field) ?>"><?= Security::e(erp_label($field)) ?><?= $required ? ' *' : '' ?></label>
                <?php if ($isFlag): ?>
                    <?php $checked = $value === '' ? false : (int)$value === 1; ?>
                    <label class="switch-row"><input type="hidden" name="<?= Security::e($field) ?>" value="0"><input id="f_<?= Security::e($field) ?>" type="checkbox" name="<?= Security::e($field) ?>" value="1" <?= $checked ? 'checked' : '' ?>><span><?= in_array(strtolower($field), ['suplierstoped','cusstoped'], true) ? 'موقوف' : 'مفعّل / نعم' ?></span></label>
                <?php elseif ($field === 'movement_type'): ?>
                    <select id="f_<?= Security::e($field) ?>" name="<?= Security::e($field) ?>" <?= $required ? 'required' : '' ?>><option value="">اختر نوع الحركة</option>
                        <?php foreach (['supply'=>'توريد يدوي','issue'=>'صرف يدوي','purchase'=>'فاتورة شراء','sale'=>'فاتورة بيع','purchase_return'=>'مرتجع شراء','sale_return'=>'مرتجع بيع'] as $v=>$label): ?><option value="<?= Security::e($v) ?>" <?= (string)$value === $v ? 'selected' : '' ?>><?= Security::e($label) ?></option><?php endforeach; ?>
                    </select>
                <?php elseif ($field === 'direction'): ?>
                    <select id="f_<?= Security::e($field) ?>" name="<?= Security::e($field) ?>" <?= $required ? 'required' : '' ?>><option value="">اختر الاتجاه</option><option value="in" <?= (string)$value==='in'?'selected':'' ?>>دخول إلى المخزون</option><option value="out" <?= (string)$value==='out'?'selected':'' ?>>خروج من المخزون</option></select>
                <?php elseif (strcasecmp($field, 'Nature') === 0): ?>
                    <select id="f_<?= Security::e($field) ?>" name="<?= Security::e($field) ?>"><option value="1" <?= (string)$value==='1'?'selected':'' ?>>مدين</option><option value="2" <?= (string)$value==='2'?'selected':'' ?>>دائن</option></select>
                <?php elseif (strcasecmp($field, 'payment_method') === 0): ?>
                    <select id="f_<?= Security::e($field) ?>" name="<?= Security::e($field) ?>"><option value="1" <?= (string)$value==='1'?'selected':'' ?>>آجل</option><option value="2" <?= (string)$value==='2'?'selected':'' ?>>نقد</option><option value="3" <?= (string)$value==='3'?'selected':'' ?>>بنك</option><option value="4" <?= (string)$value==='4'?'selected':'' ?>>شبكة</option></select>
                <?php elseif ($isFK): ?>
                    <select id="f_<?= Security::e($field) ?>" name="<?= Security::e($field) ?>" <?= $required ? 'required' : '' ?> data-field="<?= Security::e($field) ?>">
                        <?php if ((int)$column['is_nullable'] === 1 || strcasecmp($field, 'AccParent') === 0): ?><option value="">— بدون اختيار —</option><?php endif; ?>
                        <?php foreach ($options[$field] as $option): $oid = (string)$option['option_id']; ?>
                            <option value="<?= Security::e($oid) ?>" <?= (string)$value === $oid ? 'selected' : '' ?>
                                <?php foreach (['AccLevel','AccTypeID','Nature'] as $extra): if (isset($option[$extra])): ?>data-<?= strtolower($extra) ?>="<?= Security::e($option[$extra]) ?>"<?php endif; endforeach; ?>>
                                <?= Security::e($option['option_label'] ?? $oid) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif (in_array($type, ['text','ntext'], true) || (int)$column['max_length'] > 1000): ?>
                    <textarea id="f_<?= Security::e($field) ?>" name="<?= Security::e($field) ?>" rows="3" <?= $required ? 'required' : '' ?>><?= Security::e($value) ?></textarea>
                <?php else:
                    $inputType = 'text';
                    if (in_array($type, ['int','bigint','smallint','tinyint','decimal','numeric','float','real','money','smallmoney'], true)) $inputType = 'number';
                    if ($type === 'date') $inputType = 'date';
                    if (in_array($type, ['datetime','datetime2','smalldatetime'], true)) $inputType = 'datetime-local';
                    $step = in_array($type, ['decimal','numeric','float','real','money','smallmoney'], true) ? 'any' : '1';
                    $readOnly = ($pageKey === 'chart_accounts' && in_array(strtolower($field), ['acclevel','acctypeid','nature'], true)) || ($isDetails && strcasecmp($field, 'total') === 0);
                    ?>
                    <input id="f_<?= Security::e($field) ?>" type="<?= $inputType ?>" name="<?= Security::e($field) ?>" value="<?= Security::e($value) ?>" <?= $inputType === 'number' ? 'step="' . $step . '"' : '' ?> <?= $required ? 'required' : '' ?> <?= $readOnly ? 'readonly class="readonly-field"' : '' ?>>
                <?php endif; ?>
                <?php if (strcasecmp($field, 'total') === 0 && $isDetails): ?><small>يُحسب الإجمالي تلقائيًا عند الحفظ.</small><?php endif; ?>
                <?php if ($pageKey === 'chart_accounts' && strcasecmp($field, 'AccCode') === 0): ?><small>أدخل كود الحساب وفق ترقيم دليل الحسابات المعتمد لديكم؛ صيغة الترقيم لم يحددها ملف المتطلبات.</small><?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">حفظ البيانات</button><a class="btn btn-light" href="<?= $isDetails ? '?page=' . Security::e($pageKey) . '&action=details&id=' . Security::e($parentId) : '?page=' . Security::e($pageKey) ?>">إلغاء</a></div>
    </form>
</div>
<?php if ($pageKey === 'chart_accounts'): ?>
<div class="notice">الحساب الفرعي يرث المستوى ونوع الحساب وطبيعته من الحساب الأب. إدخال كود الحساب يدوي حاليًا لأن قاموس البيانات لا يحدد قاعدة توليد الكود أو عدد خانات كل مستوى.</div>
<?php endif; ?>
