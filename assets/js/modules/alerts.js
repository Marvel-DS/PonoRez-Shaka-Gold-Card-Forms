import { qs } from '../utility/dom.js';

const variantStyles = {
    info: 'border-blue-200 bg-blue-50 text-blue-800',
    success: 'border-green-200 bg-green-50 text-green-800',
    warning: 'border-amber-200 bg-amber-50 text-amber-800',
    error: 'border-red-200 bg-red-50 text-red-700',
};

let container;

function ensureContainer() {
    if (!container) {
        container = qs('[data-component="alerts"]');
    }
    return container;
}

function dismissAlert(element) {
    if (!element) {
        return;
    }
    element.classList.add('opacity-0', 'transition-opacity', 'duration-150');
    window.setTimeout(() => {
        element.remove();
    }, 180);
}

function createAlert({ title, message, variant = 'info', dismissible = true }) {
    const wrapper = document.createElement('div');
    wrapper.className = `flex items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm shadow-sm ${variantStyles[variant] || variantStyles.info}`;

    const content = document.createElement('div');
    content.className = 'space-y-1';

    if (title) {
        const heading = document.createElement('p');
        heading.className = 'font-semibold';
        heading.textContent = title;
        content.appendChild(heading);
    }

    const body = document.createElement('p');
    body.className = 'leading-snug';
    body.textContent = message;
    content.appendChild(body);

    wrapper.appendChild(content);

    if (dismissible) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'text-xs font-medium uppercase tracking-wide opacity-80 hover:opacity-100';
        button.textContent = 'Dismiss';
        button.addEventListener('click', () => dismissAlert(wrapper));
        wrapper.appendChild(button);
    }

    if (dismissible) {
        window.setTimeout(() => dismissAlert(wrapper), 8000);
    }

    return wrapper;
}

export function initAlerts() {
    ensureContainer();
}

export function clearAlerts() {
    const element = ensureContainer();
    if (!element) {
        return;
    }
    element.innerHTML = '';
}

export function showAlert({ title, message, variant = 'info', dismissible = true }) {
    const element = ensureContainer();
    if (!element) {
        return;
    }

    const alert = createAlert({ title, message, variant, dismissible });
    element.appendChild(alert);
}

export function showError(message, options = {}) {
    showAlert({
        title: options.title || 'Something went wrong',
        message,
        variant: 'error',
        dismissible: options.dismissible !== false,
    });
}

export function showSuccess(message, options = {}) {
    showAlert({
        title: options.title || 'Success',
        message,
        variant: 'success',
        dismissible: options.dismissible !== false,
    });
}
