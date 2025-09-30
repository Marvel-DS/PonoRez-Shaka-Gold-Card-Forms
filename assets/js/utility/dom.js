export const qs = (selector, context = document) => context.querySelector(selector);

export const qsa = (selector, context = document) => Array.from(context.querySelectorAll(selector));

export function toggleHidden(element, shouldHide) {
    if (!element) {
        return;
    }

    const hidden = Boolean(shouldHide);
    element.classList.toggle('hidden', hidden);
    if (hidden) {
        element.setAttribute('hidden', 'hidden');
    } else {
        element.removeAttribute('hidden');
    }
}

export function setText(element, value) {
    if (!element) {
        return;
    }
    element.textContent = value;
}

export function clearChildren(element) {
    if (!element) {
        return;
    }
    while (element.firstChild) {
        element.removeChild(element.firstChild);
    }
}

export function createElement(tagName, options = {}) {
    const element = document.createElement(tagName);
    const { className, text, attributes } = options;

    if (className) {
        element.className = className;
    }

    if (text !== undefined) {
        element.textContent = text;
    }

    if (attributes && typeof attributes === 'object') {
        Object.entries(attributes).forEach(([key, value]) => {
            if (value !== undefined && value !== null) {
                element.setAttribute(key, String(value));
            }
        });
    }

    return element;
}

export function toNumber(value, fallback = 0) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
}

export function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
}
