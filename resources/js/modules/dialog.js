export function registerDialogs() {
    document.querySelectorAll('dialog[data-dialog]').forEach((dialog) => {
        if (typeof dialog.showModal !== 'function') {
            return;
        }

        document.querySelectorAll(`[data-dialog-open="${dialog.id}"]`).forEach((trigger) => {
            trigger.setAttribute('aria-haspopup', 'dialog');

            trigger.addEventListener('click', (event) => {
                event.preventDefault();
                dialog.showModal();
            });
        });

        dialog.querySelectorAll('[data-dialog-close]').forEach((button) => {
            button.addEventListener('click', () => dialog.close());
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });

        if (dialog.hasAttribute('data-dialog-open-on-load')) {
            dialog.showModal();
        }
    });
}
