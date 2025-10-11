import { parseOverlayMessage } from './overlay-messages.js';

let overlay;
let backdrop;
let closeButton;
let frame;
let loadingState;
let messageContainer;
let errorBox;
let successBox;

let isOpen = false;
let hasMessageListener = false;

function ensureElements() {
    if (overlay) {
        return;
    }

    overlay = document.querySelector('[data-overlay="checkout"]');
    if (!overlay) {
        return;
    }

    backdrop = overlay.querySelector('[data-overlay-backdrop]');
    closeButton = overlay.querySelector('[data-overlay-close]');
    frame = overlay.querySelector('iframe[data-overlay-frame]');
    loadingState = overlay.querySelector('[data-overlay-loading]');
    messageContainer = overlay.querySelector('[data-overlay-message]');
    errorBox = overlay.querySelector('[data-overlay-error]');
    successBox = overlay.querySelector('[data-overlay-success]');

    if (closeButton) {
        closeButton.addEventListener('click', closeOverlay);
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeOverlay);
    }

    if (!hasMessageListener) {
        window.addEventListener('message', handleOverlayMessage);
        hasMessageListener = true;
    }
}

function handleOverlayMessage(event) {
    const message = parseOverlayMessage(event);
    if (!message) {
        return;
    }

    if (message.type === 'checkout:complete') {
        showSuccess(message.payload?.message || 'Checkout complete. Thank you!');
    }

    if (message.type === 'checkout:error') {
        showError(message.payload?.message || 'Checkout failed.');
    }

    if (message.type === 'checkout:close') {
        closeOverlay();
    }
}

function toggleBodyScroll(disabled) {
    document.body.classList.toggle('overflow-hidden', disabled);
}

function toggleElement(element, shouldShow) {
    if (!element) {
        return;
    }
    element.classList.toggle('hidden', !shouldShow);
    if (!shouldShow) {
        element.setAttribute('hidden', 'hidden');
    } else {
        element.removeAttribute('hidden');
    }
}

function showError(message) {
    if (messageContainer) {
        toggleElement(messageContainer, true);
    }
    if (errorBox) {
        errorBox.textContent = message;
        toggleElement(errorBox, true);
    }
    if (successBox) {
        toggleElement(successBox, false);
    }
}

function showSuccess(message) {
    if (messageContainer) {
        toggleElement(messageContainer, true);
    }
    if (successBox) {
        successBox.textContent = message;
        toggleElement(successBox, true);
    }
    if (errorBox) {
        toggleElement(errorBox, false);
    }
}

function resetMessages() {
    if (messageContainer) {
        toggleElement(messageContainer, false);
    }
    if (errorBox) {
        toggleElement(errorBox, false);
    }
    if (successBox) {
        toggleElement(successBox, false);
    }
}

export function openOverlay({ url, message, reservationId, totalPrice } = {}) {
    ensureElements();
    if (!overlay) {
        return false;
    }

    isOpen = true;
    overlay.classList.remove('hidden');
    overlay.setAttribute('aria-hidden', 'false');
    toggleBodyScroll(true);
    resetMessages();

    if (loadingState) {
        toggleElement(loadingState, Boolean(url));
    }

    if (frame) {
        frame.src = url || 'about:blank';
        if (url) {
            frame.addEventListener('load', () => toggleElement(loadingState, false), { once: true });
        } else {
            toggleElement(loadingState, false);
        }
    }

    if (reservationId) {
        const parts = [`Reservation ${reservationId} created.`];
        if (totalPrice) {
            parts.push(`Total: ${totalPrice}`);
        }
        showSuccess(parts.join(' '));
    } else if (message) {
        showSuccess(message);
    }

    return true;
}

export function closeOverlay() {
    if (!overlay || !isOpen) {
        return;
    }

    isOpen = false;
    overlay.classList.add('hidden');
    overlay.setAttribute('aria-hidden', 'true');
    toggleBodyScroll(false);
    resetMessages();

    if (frame) {
        frame.src = 'about:blank';
    }
}

export function initCheckoutOverlay() {
    ensureElements();
}

export default {
    initCheckoutOverlay,
    openOverlay,
    closeOverlay,
};
