/**
 * Minimal signature pad: draws with mouse, touch or pen and writes a PNG data URL into a hidden input.
 */
const pad = document.getElementById('signature-pad');
const input = document.getElementById('signature');
const clear = document.getElementById('signature-clear');
const context = pad.getContext('2d');

let drawing = false;

function resize() {
    const ratio = window.devicePixelRatio || 1;
    const saved = input.value;

    pad.width = pad.offsetWidth * ratio;
    pad.height = pad.offsetHeight * ratio;
    context.scale(ratio, ratio);
    context.lineWidth = 2;
    context.lineCap = 'round';
    context.strokeStyle = '#111827';

    if (saved) {
        const image = new Image();
        image.onload = () => context.drawImage(image, 0, 0, pad.offsetWidth, pad.offsetHeight);
        image.src = saved;
    }
}

function point(event) {
    const box = pad.getBoundingClientRect();

    return { x: event.clientX - box.left, y: event.clientY - box.top };
}

pad.addEventListener('pointerdown', (event) => {
    drawing = true;
    pad.setPointerCapture(event.pointerId);
    const { x, y } = point(event);
    context.beginPath();
    context.moveTo(x, y);
});

pad.addEventListener('pointermove', (event) => {
    if (!drawing) {
        return;
    }

    const { x, y } = point(event);
    context.lineTo(x, y);
    context.stroke();
});

['pointerup', 'pointercancel'].forEach((name) =>
    pad.addEventListener(name, () => {
        if (drawing) {
            drawing = false;
            input.value = pad.toDataURL('image/png');
        }
    }),
);

clear.addEventListener('click', () => {
    context.clearRect(0, 0, pad.width, pad.height);
    input.value = '';
});

resize();
