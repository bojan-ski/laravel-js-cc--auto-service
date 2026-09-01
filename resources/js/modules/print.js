const BUTTON_CLASSES = [
    'inline-flex',
    'items-center',
    'justify-center',
    'rounded-sm',
    'px-3',
    'py-2',
    'text-sm',
    'font-semibold',
    'focus-visible:outline-2',
    'focus-visible:outline-offset-2',
    'focus-visible:outline-accent',
    'border',
    'border-border-strong',
    'bg-surface',
    'text-text',
    'hover:bg-surface-muted',
];

export function registerPrintButtons() {
    document.querySelectorAll('[data-print-slot]').forEach((slot) => {
        const label = slot.dataset.printLabel;

        if (!label) {
            return;
        }

        const button = document.createElement('button');

        button.type = 'button';
        button.textContent = label;
        button.classList.add(...BUTTON_CLASSES);
        button.addEventListener('click', () => window.print());

        slot.append(button);
    });
}
