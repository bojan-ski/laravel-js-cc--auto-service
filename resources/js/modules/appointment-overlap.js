const DEBOUNCE_MS = 300;

function buildUrl(form) {
    const url = new URL(form.dataset.appointmentOverlapUrl, window.location.origin);
    const scheduledAt = form.elements.scheduled_at?.value;
    const duration = form.elements.duration_minutes?.value;

    if (!scheduledAt || !duration) {
        return null;
    }

    url.searchParams.set('scheduled_at', scheduledAt);
    url.searchParams.set('duration_minutes', duration);

    const exclude = form.dataset.appointmentOverlapExclude;

    if (exclude) {
        url.searchParams.set('exclude', exclude);
    }

    return url;
}

async function refresh(form, output) {
    const url = buildUrl(form);

    if (!url) {
        output.textContent = '';

        return;
    }

    let response;

    try {
        response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
    } catch {
        output.textContent = '';

        return;
    }

    if (!response.ok) {
        output.textContent = '';

        return;
    }

    const payload = await response.json();
    const count = payload.data?.length ?? 0;

    output.textContent = count > 0
        ? form.dataset.appointmentOverlapTemplate.replace(':count', count)
        : '';
}

export function registerAppointmentOverlap() {
    document.querySelectorAll('[data-appointment-overlap]').forEach((form) => {
        const output = form.querySelector('[data-appointment-overlap-output]');

        if (!output) {
            return;
        }

        let timer;

        form.querySelectorAll('[data-appointment-overlap-input]').forEach((input) => {
            input.addEventListener('input', () => {
                window.clearTimeout(timer);
                timer = window.setTimeout(() => refresh(form, output), DEBOUNCE_MS);
            });
        });

        refresh(form, output);
    });
}
