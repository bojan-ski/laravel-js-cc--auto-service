export function registerDropdowns() {
    const dropdowns = [...document.querySelectorAll('details[data-dropdown]')];

    if (dropdowns.length === 0) {
        return;
    }

    document.addEventListener('click', (event) => {
        dropdowns.forEach((dropdown) => {
            if (dropdown.open && !dropdown.contains(event.target)) {
                dropdown.open = false;
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        dropdowns.forEach((dropdown) => {
            if (!dropdown.open) {
                return;
            }

            const hadFocus = dropdown.contains(document.activeElement);

            dropdown.open = false;

            if (hadFocus) {
                dropdown.querySelector('summary')?.focus();
            }
        });
    });
}
