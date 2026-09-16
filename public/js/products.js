'use strict';

const form = document.querySelector('#product-form');
// Let the server return consistent validation messages beside each field.
form.noValidate = true;

const message = document.querySelector('#message');
const submitButton = form.querySelector('button[type="submit"]');
const money = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

// Rebuild the table and grand total from the latest server response.
function renderProducts(data) {
    const rows = document.createDocumentFragment();

    for (const product of data.products) {
        const row = document.createElement('tr');
        const values = [
            product.name,
            product.quantity,
            money.format(product.price_cents / 100),
            product.submitted_at,
            money.format(product.total_cents / 100),
        ];

        for (const value of values) {
            const cell = document.createElement('td');
            cell.textContent = value;
            row.append(cell);
        }

        row.lastElementChild.classList.add('text-end');
        rows.append(row);
    }

    if (data.products.length === 0) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 5;
        cell.className = 'text-muted text-center';
        cell.textContent = 'No products yet.';
        row.append(cell);
        rows.append(row);
    }

    document.querySelector('#products').replaceChildren(rows);
    document.querySelector('#grand-total').textContent = money.format(data.grand_total_cents / 100);
}

// Save without reloading, show field errors, and refresh the product list.
form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (submitButton.disabled) return;

    for (const name of ['name', 'quantity', 'price']) {
        form.elements[name].classList.remove('is-invalid');
        form.elements[name].removeAttribute('aria-invalid');
        document.querySelector(`#${name}-error`).textContent = '';
    }

    submitButton.disabled = true;
    submitButton.textContent = 'Saving…';
    message.textContent = '';
    let saved = false;

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': form.elements._token.value,
            },
            body: JSON.stringify({
                name: form.elements.name.value,
                quantity: form.elements.quantity.value,
                price: form.elements.price.value,
            }),
        });

        if (!response.ok) {
            const data = await response.json();
            for (const name of ['name', 'quantity', 'price']) {
                if (data.errors?.[name]) {
                    form.elements[name].classList.add('is-invalid');
                    form.elements[name].setAttribute('aria-invalid', 'true');
                    document.querySelector(`#${name}-error`).textContent = data.errors[name][0];
                }
            }
            form.querySelector('.is-invalid')?.focus();
            throw new Error(data.message || 'Unable to save the product.');
        }

        saved = true;
        form.reset();
        const listing = await fetch(form.dataset.listUrl, {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });
        if (!listing.ok) throw new Error('Unable to refresh products.');

        renderProducts(await listing.json());
        message.textContent = 'Product saved.';
    } catch (error) {
        message.textContent = saved
            ? 'Product saved, but the table could not refresh. Reload the page to see it.'
            : error.message;
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = 'Save product';
    }
});
