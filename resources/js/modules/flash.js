const FADE_MS = 200;

export function registerFlashMessages() {
    document.querySelectorAll('[data-flash]').forEach((toast) => {
        const dismiss = () => {
            toast.classList.add('opacity-0');
            setTimeout(() => toast.remove(), FADE_MS);
        };

        const button = toast.querySelector('[data-flash-dismiss]');

        if (button) {
            button.hidden = false;
            button.addEventListener('click', dismiss);
        }

        const timeout = Number(toast.dataset.flashTimeout);

        if (!timeout) {
            return;
        }

        let timer = setTimeout(dismiss, timeout);

        const pause = () => clearTimeout(timer);
        const resume = () => {
            clearTimeout(timer);
            timer = setTimeout(dismiss, timeout);
        };

        toast.addEventListener('mouseenter', pause);
        toast.addEventListener('focusin', pause);
        toast.addEventListener('mouseleave', resume);
        toast.addEventListener('focusout', resume);
    });
}
