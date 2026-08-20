function resolveMessage(form) {
    const message = form.dataset.confirm;
    const source = form.dataset.confirmSource;

    if (!source) {
        return message;
    }

    const field = form.elements[source];

    if (!field) {
        return message;
    }

    const option = field.selectedOptions?.[0];

    return message.replace(':selected', option ? option.textContent.trim() : field.value);
}

export function registerConfirmForms() {
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-confirm]');

        if (!form) {
            return;
        }

        if (!window.confirm(resolveMessage(form))) {
            event.preventDefault();
        }
    });
}
