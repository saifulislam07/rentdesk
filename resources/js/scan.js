import { Html5Qrcode } from 'html5-qrcode';

const root = document.getElementById('scanner');
const message = document.getElementById('scan-message');
const form = document.getElementById('manual-scan');

/**
 * Pull the QR token out of a scanned value, which is either the unit's scan URL or the bare token.
 */
function tokenFrom(text) {
    const value = text.trim();

    try {
        const parts = new URL(value).pathname.split('/').filter(Boolean);

        return parts[parts.length - 1];
    } catch {
        return value;
    }
}

function open(text) {
    const token = tokenFrom(text);

    if (token) {
        window.location.assign(root.dataset.scanUrl.replace('__TOKEN__', encodeURIComponent(token)));
    }
}

form.addEventListener('submit', (event) => {
    event.preventDefault();
    open(new FormData(form).get('code') ?? '');
});

const scanner = new Html5Qrcode('scanner');

scanner
    .start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 240, height: 240 } },
        (text) => {
            scanner.stop().finally(() => open(text));
        },
    )
    .catch(() => {
        message.textContent = root.dataset.cameraError;
    });
