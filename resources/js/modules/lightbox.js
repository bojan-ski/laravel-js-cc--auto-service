export function registerLightbox() {
    document.querySelectorAll('[data-lightbox-dialog]').forEach((dialog) => {
        const image = dialog.querySelector('[data-lightbox-image]');
        const section = dialog.closest('section');

        if (!image || !section || typeof dialog.showModal !== 'function') {
            return;
        }

        section.querySelectorAll('[data-lightbox-trigger]').forEach((trigger) => {
            trigger.addEventListener('click', (event) => {
                event.preventDefault();

                image.src = trigger.href;
                image.alt = trigger.dataset.lightboxAlt ?? '';

                dialog.showModal();
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
        });
    });
}
