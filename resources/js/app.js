/*
 * Small progressive-enhancement helpers. Every page still works without JavaScript,
 * except the copy buttons.
 */

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');

    if (!button) {
        return;
    }

    const source = document.querySelector(button.dataset.copy);
    const text = source ? (source.value ?? source.textContent).trim() : '';

    if (!text) {
        return;
    }

    try {
        await navigator.clipboard.writeText(text);
    } catch {
        source?.select?.();
        document.execCommand('copy');
    }

    const label = button.querySelector('[data-copy-label]') ?? button;
    const original = label.textContent;
    label.textContent = 'Copied';
    window.setTimeout(() => (label.textContent = original), 1800);
});

const dialog = document.getElementById('confirm-dialog');

if (dialog) {
    let pendingForm = null;

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (form.dataset.confirm && form.dataset.confirmed !== '1') {
            event.preventDefault();
            pendingForm = form;
            dialog.querySelector('[data-confirm-title]').textContent = form.dataset.confirmTitle || 'Are you sure?';
            dialog.querySelector('[data-confirm-message]').textContent = form.dataset.confirm;
            dialog.querySelector('[data-confirm-ok]').textContent = form.dataset.confirmOk || 'Confirm';
            dialog.showModal();
        }
    });

    dialog.querySelector('[data-confirm-ok]').addEventListener('click', () => {
        if (pendingForm) {
            pendingForm.dataset.confirmed = '1';
            dialog.close();
            pendingForm.requestSubmit();
        }
    });

    dialog.querySelector('[data-confirm-cancel]').addEventListener('click', () => {
        pendingForm = null;
        dialog.close();
    });
}

// Show a spinner and block double submits while a request is in flight.
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (event.defaultPrevented || form.dataset.noLoading !== undefined) {
        return;
    }

    form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
        if (button.dataset.loadingText !== undefined || form.dataset.loading !== undefined) {
            window.setTimeout(() => {
                button.disabled = true;
                button.insertAdjacentHTML('afterbegin', '<span class="spinner" aria-hidden="true"></span>');

                if (button.dataset.loadingText) {
                    button.querySelector('[data-label]')?.replaceChildren(button.dataset.loadingText);
                }
            }, 0);
        }
    });
});

// Re-enable buttons when the page is restored from the back/forward cache.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        document.querySelectorAll('button[disabled] > .spinner').forEach((spinner) => {
            spinner.parentElement.disabled = false;
            spinner.remove();
        });
    }
});
