function showDeleteFor(form, trigger) {
    if (!form) {
        return;
    }

    const action = trigger.dataset.lightboxDeleteAction;

    form.hidden = !action;

    if (!action) {
        return;
    }

    form.action = action;
    form.dataset.confirm = trigger.dataset.lightboxDeleteConfirm ?? '';
    form.querySelector('button')?.setAttribute('aria-label', trigger.dataset.lightboxDeleteLabel ?? '');
}

export function registerLightbox() {
    document.querySelectorAll('[data-lightbox-dialog]').forEach((dialog) => {
        const image = dialog.querySelector('[data-lightbox-image]');
        const deleteForm = dialog.querySelector('[data-lightbox-delete]');
        const section = dialog.closest('section');

        if (!image || !section || typeof dialog.showModal !== 'function') {
            return;
        }

        section.querySelectorAll('[data-lightbox-trigger]').forEach((trigger) => {
            trigger.addEventListener('click', (event) => {
                event.preventDefault();

                image.src = trigger.href;
                image.alt = trigger.dataset.lightboxAlt ?? '';
                showDeleteFor(deleteForm, trigger);

                image.decode().catch(() => {}).then(() => {
                    if (image.src === trigger.href && !dialog.open) {
                        dialog.showModal();
                    }
                });
            });
        });

        dialog.querySelector('[data-lightbox-close]')?.addEventListener('click', () => dialog.close());

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });

        dialog.addEventListener('close', () => {
            image.src = '';
            image.alt = '';

            if (deleteForm) {
                deleteForm.hidden = true;
            }
        });
    });
}
