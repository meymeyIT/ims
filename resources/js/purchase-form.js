/*
 * Purchase order form: add / remove product lines and show live totals.
 * The server recalculates every amount; these numbers are only a preview.
 */
const form = document.querySelector('[data-purchase-form]');

if (form) {
    const body = form.querySelector('[data-lines]');
    const template = document.getElementById('purchase-line-template');
    const emptyState = form.querySelector('[data-empty-lines]');
    let nextIndex = Number(body.dataset.nextIndex || 0);

    const toNumber = (value) => {
        const number = parseFloat(value);
        return Number.isFinite(number) ? number : 0;
    };
    const money = (value) => value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function refreshUnit(row) {
        const option = row.querySelector('[data-product]').selectedOptions[0];
        row.querySelector('[data-unit-label]').textContent = option?.dataset.unit || '—';
    }

    function markDuplicates() {
        const seen = new Map();
        body.querySelectorAll('[data-product]').forEach((select) => {
            select.classList.remove('is-duplicate');
            if (!select.value) return;
            if (seen.has(select.value)) {
                select.classList.add('is-duplicate');
                seen.get(select.value).classList.add('is-duplicate');
            } else {
                seen.set(select.value, select);
            }
        });
    }

    function recalculate() {
        let subtotal = 0;

        body.querySelectorAll('[data-line]').forEach((row) => {
            const qty = toNumber(row.querySelector('[data-qty]').value);
            const cost = toNumber(row.querySelector('[data-cost]').value);
            const lineTotal = Math.round(qty * cost * 100) / 100;
            row.querySelector('[data-line-total]').textContent = money(lineTotal);
            subtotal += lineTotal;
        });

        const discount = toNumber(form.querySelector('[data-discount]').value);
        const tax = toNumber(form.querySelector('[data-tax]').value);
        const total = subtotal - discount + tax;

        form.querySelector('[data-subtotal]').textContent = money(subtotal);
        const totalEl = form.querySelector('[data-total]');
        totalEl.textContent = money(total);
        totalEl.classList.toggle('text-danger', total < 0);

        emptyState.classList.toggle('d-none', body.querySelectorAll('[data-line]').length > 0);
        markDuplicates();
    }

    function addLine() {
        const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        body.insertAdjacentHTML('beforeend', html);
        const row = body.lastElementChild;
        refreshUnit(row);
        recalculate();
        row.querySelector('[data-product]').focus();
    }

    form.querySelector('[data-add-line]').addEventListener('click', addLine);

    body.addEventListener('click', (event) => {
        if (event.target.closest('[data-remove-line]')) {
            event.target.closest('[data-line]').remove();
            recalculate();
        }
    });

    body.addEventListener('change', (event) => {
        if (event.target.matches('[data-product]')) {
            const row = event.target.closest('[data-line]');
            const costInput = row.querySelector('[data-cost]');
            const option = event.target.selectedOptions[0];

            // Suggest the product's purchase price when no cost was typed yet.
            if (option?.dataset.price && (costInput.value === '' || toNumber(costInput.value) === 0)) {
                costInput.value = option.dataset.price;
            }
            refreshUnit(row);
            recalculate();
        }
    });

    form.addEventListener('input', (event) => {
        if (event.target.matches('[data-qty], [data-cost], [data-discount], [data-tax]')) {
            recalculate();
        }
    });

    body.querySelectorAll('[data-line]').forEach(refreshUnit);
    if (body.querySelectorAll('[data-line]').length === 0) {
        addLine();
    }
    recalculate();
}

/* Receive page: "Receive all remaining" fills each line with its remaining quantity. */
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-fill-remaining]');
    if (!button) return;

    document.querySelectorAll('[data-remaining]').forEach((input) => {
        input.value = input.dataset.remaining;
    });
});
