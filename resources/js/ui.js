import Swal from 'sweetalert2';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

/**
 * Text and formats the server hands to the page (see the js-config component).
 */
const config = window.rentdesk ?? {};

const dialog = Swal.mixin({
    confirmButtonColor: '#2563eb',
    cancelButtonColor: '#6b7280',
    reverseButtons: document.documentElement.dir === 'rtl',
});

const toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    showCloseButton: true,
    timer: 5000,
    timerProgressBar: true,
    didOpen: (element) => {
        element.addEventListener('mouseenter', Swal.stopTimer);
        element.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

/**
 * Show the messages the server flashed (status and error) as toasts.
 */
function showFlashMessages() {
    document.querySelectorAll('[data-toast]').forEach((element, index) => {
        setTimeout(() => toast.fire({ icon: element.dataset.toast, title: element.dataset.message, customClass: { popup: `rd-toast rd-toast-${element.dataset.toast}` } }), index * 300);
    });
}

/**
 * Ask before submitting a form marked with data-confirm="message".
 */
function confirmBeforeSubmitting() {
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed) {
            return;
        }

        event.preventDefault();
        const submitter = event.submitter;

        dialog
            .fire({
                icon: 'warning',
                title: form.dataset.confirm,
                showCancelButton: true,
                confirmButtonText: config.text?.yes ?? 'Yes',
                cancelButtonText: config.text?.cancel ?? 'Cancel',
                focusCancel: true,
            })
            .then((result) => {
                if (result.isConfirmed) {
                    form.dataset.confirmed = '1';
                    form.requestSubmit(submitter);
                }
            });
    });
}

const calendarLocales = import.meta.glob('../../node_modules/flatpickr/dist/esm/l10n/*.js');

/**
 * Load the calendar's month and weekday names for the page language, when flatpickr has them.
 */
async function localeFor(language) {
    for (const code of [language, language.split('-')[0]]) {
        const load = calendarLocales[`../../node_modules/flatpickr/dist/esm/l10n/${code}.js`];

        if (load) {
            return (await load()).default[code];
        }
    }

    return null;
}

/**
 * Replace the browser's date and date-time inputs with a picker that follows the date format chosen in Settings.
 * The value submitted stays in ISO form, so the server needs no parsing.
 */
async function enhanceDateInputs() {
    const inputs = document.querySelectorAll('input[type="date"], input[type="datetime-local"]');

    if (inputs.length === 0) {
        return;
    }

    const locale = await localeFor(config.locale ?? 'en');
    const dateFormat = config.dateFormat ?? 'Y-m-d';
    const timeFormat = config.timeFormat ?? 'H:i';

    inputs.forEach((input) => {
        const withTime = input.type === 'datetime-local';

        flatpickr(input, {
            locale: locale ?? 'default',
            enableTime: withTime,
            time_24hr: timeFormat.includes('H'),
            dateFormat: withTime ? 'Y-m-d\\TH:i' : 'Y-m-d',
            altInput: true,
            altFormat: withTime ? `${dateFormat} ${timeFormat}` : dateFormat,
            altInputClass: input.className,
            allowInput: false,
            disableMobile: true,
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    showFlashMessages();
    confirmBeforeSubmitting();
    enhanceDateInputs();
});

/**
 * Put a red asterisk on the label of every required field.
 */
function markRequiredLabels() {
    document.querySelectorAll('input[required], select[required], textarea[required]').forEach((control) => {
        if (!control.id || control.type === 'checkbox' || control.type === 'radio') {
            return;
        }

        document.querySelectorAll(`label[for="${CSS.escape(control.id)}"]`).forEach((label) => {
            if (label.classList.contains('sr-only') || label.querySelector('[data-required-mark]')) {
                return;
            }

            const mark = document.createElement('span');
            mark.dataset.requiredMark = '';
            mark.className = 'ms-0.5 text-red-600';
            mark.setAttribute('aria-hidden', 'true');
            mark.textContent = '*';
            label.append(mark);
        });
    });
}

/**
 * Show validation problems under the field instead of in the browser's pop-up bubble.
 */
function showErrorsUnderFields() {
    const messages = new WeakMap();

    const clear = (control) => {
        control.removeAttribute('aria-invalid');
        control.classList.remove('!border-red-500', '!ring-red-500');
        messages.get(control)?.remove();
        messages.delete(control);
    };

    const show = (control) => {
        clear(control);
        control.setAttribute('aria-invalid', 'true');
        control.classList.add('!border-red-500', '!ring-red-500');

        const message = document.createElement('p');
        message.className = 'mt-1 text-sm text-red-600';
        message.textContent = control.validity.valueMissing ? (config.text?.required ?? 'This field is required.') : control.validationMessage;
        control.insertAdjacentElement('afterend', message);
        messages.set(control, message);
    };

    document.querySelectorAll('form').forEach((form) => {
        form.noValidate = true;
    });

    document.addEventListener(
        'submit',
        (event) => {
            const form = event.target;

            if (!(form instanceof HTMLFormElement) || event.submitter?.formNoValidate) {
                return;
            }

            const invalid = [...form.elements].filter((control) => control.willValidate && !control.checkValidity());

            [...form.elements].forEach((control) => clear(control));

            if (invalid.length === 0) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            invalid.forEach(show);
            invalid[0].focus();
        },
        true,
    );

    ['input', 'change'].forEach((name) => {
        document.addEventListener(name, (event) => {
            if (event.target.getAttribute?.('aria-invalid') === 'true' && event.target.checkValidity()) {
                clear(event.target);
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    markRequiredLabels();
    showErrorsUnderFields();
});
