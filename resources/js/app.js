import './bootstrap'; // Laravel's axios setup (sends the CSRF token with AJAX requests)
import * as bootstrap from 'bootstrap';
import Swal from 'sweetalert2';
import './purchase-form';
import './theme';

// Chart.js is downloaded only on the dashboard (separate file, keeps other pages light).
if (document.getElementById('dashboard-charts')) {
    import('./dashboard');
}

window.bootstrap = bootstrap;
window.Swal = Swal;

/*
 * Confirmation dialogs.
 * Add data-confirm="message" to any <form>; it asks with SweetAlert2 before submitting.
 * Optional: data-confirm-title, data-confirm-button, data-confirm-color.
 */
document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) {
        return;
    }

    event.preventDefault();

    const result = await Swal.fire({
        title: form.dataset.confirmTitle || 'Are you sure?',
        text: form.dataset.confirm,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: form.dataset.confirmButton || 'Yes, continue',
        cancelButtonText: 'Cancel',
        confirmButtonColor: form.dataset.confirmColor || '#dc3545',
        reverseButtons: true,
        focusCancel: true,
    });

    if (result.isConfirmed) {
        form.submit(); // native submit: does not trigger this listener again
    }
});

/* Disable submit buttons after a form is submitted (prevents double-clicks / duplicate records). */
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented || form.method.toLowerCase() === 'get') {
        return;
    }

    setTimeout(() => {
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = true;
        });
    }, 0);
});

/* Show / hide password: <button data-toggle-password="#password"> */
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-toggle-password]');
    if (!button) {
        return;
    }

    const input = document.querySelector(button.dataset.togglePassword);
    if (!input) {
        return;
    }

    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.querySelector('i')?.classList.toggle('bi-eye', !show);
    button.querySelector('i')?.classList.toggle('bi-eye-slash', show);
    button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
});

/* "Select all" checkbox per group: data-check-all="group" controls inputs with data-module="group". */
function syncCheckAll(group) {
    const master = document.querySelector(`[data-check-all="${group}"]`);
    const boxes = [...document.querySelectorAll(`input[data-module="${group}"]`)];
    if (!master || boxes.length === 0) {
        return;
    }
    const checked = boxes.filter((box) => box.checked).length;
    master.checked = checked === boxes.length;
    master.indeterminate = checked > 0 && checked < boxes.length;
}

document.addEventListener('change', (event) => {
    const target = event.target;

    if (target.matches('[data-check-all]')) {
        document.querySelectorAll(`input[data-module="${target.dataset.checkAll}"]`)
            .forEach((box) => { box.checked = target.checked; });
    } else if (target.matches('input[data-module]')) {
        syncCheckAll(target.dataset.module);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-check-all]').forEach((master) => syncCheckAll(master.dataset.checkAll));
});

/* Live preview of a chosen image: <input type="file" data-image-preview="#previewBox"> */
document.addEventListener('change', (event) => {
    const input = event.target;
    if (!input.matches('input[type="file"][data-image-preview]')) {
        return;
    }

    const box = document.querySelector(input.dataset.imagePreview);
    const file = input.files?.[0];
    if (!box || !file) {
        return;
    }

    if (!file.type.startsWith('image/')) {
        Swal.fire({ icon: 'error', title: 'Not an image', text: 'Please choose a JPG, PNG or WEBP image.' });
        input.value = '';
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        Swal.fire({ icon: 'error', title: 'Image too large', text: 'The image must be 2 MB or smaller.' });
        input.value = '';
        return;
    }

    const img = document.createElement('img');
    img.alt = 'Selected image preview';
    img.src = URL.createObjectURL(file);
    img.onload = () => URL.revokeObjectURL(img.src);
    box.replaceChildren(img);
});
