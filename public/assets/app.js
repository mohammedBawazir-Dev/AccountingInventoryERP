document.addEventListener('DOMContentLoaded', () => {
    // In the chart of accounts, level/type/nature inherit from the selected parent.
    const parentSelect = document.querySelector('[name="AccParent"]');
    if (parentSelect) {
        const applyParent = () => {
            const option = parentSelect.options[parentSelect.selectedIndex];
            const parentLevel = option ? option.dataset.acclevel : '';
            const levelInput = document.querySelector('[name="AccLevel"]');
            const typeInput = document.querySelector('[name="AccTypeID"]');
            const natureInput = document.querySelector('[name="Nature"]');
            if (!parentSelect.value) {
                if (levelInput) levelInput.value = '1';
                if (natureInput && !natureInput.value) natureInput.value = '1';
                if (typeInput) typeInput.value = '';
                return;
            }
            if (levelInput && parentLevel) levelInput.value = String(Number(parentLevel) + 1);
            if (typeInput && option.dataset.acctypeid) typeInput.value = option.dataset.acctypeid;
            if (natureInput && option.dataset.nature) natureInput.value = option.dataset.nature;
        };
        parentSelect.addEventListener('change', applyParent);
        if (!parentSelect.value) applyParent();
    }

    // Recalculate line totals on purchase/sales/movement detail forms.
    const form = document.querySelector('.data-form');
    if (form) {
        const byName = (name) => form.querySelector(`[name="${name}"]`);
        const quantity = byName('quantity');
        const price = byName('price');
        const discount = byName('discount');
        const unitCost = byName('unit_cost');
        const total = byName('total');
        const calculate = () => {
            if (!total) return;
            const q = Number(quantity?.value || 0);
            const p = Number(price?.value || unitCost?.value || 0);
            const d = Number(discount?.value || 0);
            const result = Math.max(0, q * p - d);
            total.value = result.toFixed(6).replace(/0+$/, '').replace(/\.$/, '');
        };
        [quantity, price, discount, unitCost].filter(Boolean).forEach(el => el.addEventListener('input', calculate));
        if (quantity && (price || unitCost) && total) calculate();
    }
});

// Collapse/expand account branches on the chart of accounts page.
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-tree-toggle]');
    if (!button) return;
    const id = button.dataset.treeToggle;
    const current = document.querySelector(`tr[data-account-id="${CSS.escape(id)}"]`);
    if (!current) return;
    const depth = Number(current.dataset.depth || 0);
    const collapse = button.textContent.trim() === '−';
    let next = current.nextElementSibling;
    while (next && Number(next.dataset.depth || 0) > depth) {
        if (collapse) next.hidden = true;
        else {
            const parentId = next.dataset.accountParent;
            const parent = parentId ? document.querySelector(`tr[data-account-id="${CSS.escape(parentId)}"]`) : null;
            if (!parent || !parent.hidden) next.hidden = false;
        }
        next = next.nextElementSibling;
    }
    button.textContent = collapse ? '+' : '−';
});
