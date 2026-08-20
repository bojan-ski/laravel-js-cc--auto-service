export function registerConfirmForms() {
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-confirm]');

        if (!form) {
            return;
        }

        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
}
