const INK = '#000000';
const PAPER = '#ffffff';
const INK_WIDTH = 2.5;
const MAX_PIXEL_RATIO = 2;

function preparePad(canvas) {
    const ratio = Math.min(window.devicePixelRatio || 1, MAX_PIXEL_RATIO);
    const { width, height } = canvas.getBoundingClientRect();

    canvas.width = Math.round(width * ratio);
    canvas.height = Math.round(height * ratio);

    const context = canvas.getContext('2d');

    context.setTransform(ratio, 0, 0, ratio, 0, 0);
    context.fillStyle = PAPER;
    context.fillRect(0, 0, width, height);
    context.strokeStyle = INK;
    context.lineWidth = INK_WIDTH;
    context.lineCap = 'round';
    context.lineJoin = 'round';

    return context;
}

function pointIn(canvas, event) {
    const bounds = canvas.getBoundingClientRect();

    return { x: event.clientX - bounds.left, y: event.clientY - bounds.top };
}

function registerPad(form) {
    const canvas = form.querySelector('[data-signature-canvas]');
    const input = form.querySelector('[data-signature-input]');
    const empty = form.querySelector('[data-signature-empty]');

    if (!canvas || !input || typeof canvas.getContext !== 'function') {
        return;
    }

    form.querySelectorAll('[data-signature-drawn]').forEach((element) => {
        element.hidden = false;
        element.disabled = false;
    });

    let context = preparePad(canvas);
    let drawing = false;
    let inked = false;

    const reset = () => {
        context = preparePad(canvas);
        inked = false;
    };

    canvas.addEventListener('pointerdown', (event) => {
        drawing = true;
        canvas.setPointerCapture(event.pointerId);

        const point = pointIn(canvas, event);

        context.beginPath();
        context.moveTo(point.x, point.y);
        context.lineTo(point.x, point.y);
        context.stroke();
        inked = true;

        if (empty) {
            empty.hidden = true;
        }
    });

    canvas.addEventListener('pointermove', (event) => {
        if (!drawing) {
            return;
        }

        const point = pointIn(canvas, event);

        context.lineTo(point.x, point.y);
        context.stroke();
    });

    const stop = () => {
        drawing = false;
    };

    canvas.addEventListener('pointerup', stop);
    canvas.addEventListener('pointercancel', stop);

    form.querySelector('[data-signature-clear]')?.addEventListener('click', reset);

    let width = canvas.getBoundingClientRect().width;

    window.addEventListener('resize', () => {
        const current = canvas.getBoundingClientRect().width;

        if (current !== width) {
            width = current;
            reset();
        }
    });

    form.addEventListener('submit', (event) => {
        if (event.submitter?.value !== 'drawn') {
            input.value = '';

            return;
        }

        if (!inked) {
            event.preventDefault();

            if (empty) {
                empty.hidden = false;
            }

            return;
        }

        input.value = canvas.toDataURL('image/png');
    });
}

export function registerSignaturePads() {
    document.querySelectorAll('[data-signature-form]').forEach(registerPad);
}
