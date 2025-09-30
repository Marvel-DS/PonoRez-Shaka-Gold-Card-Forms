export const OVERLAY_EVENT_COMPLETE = 'checkout:complete';
export const OVERLAY_EVENT_ERROR = 'checkout:error';
export const OVERLAY_EVENT_CLOSE = 'checkout:close';

export function parseOverlayMessage(event) {
    if (!event || typeof event.data !== 'object' || event.data === null) {
        return null;
    }

    const { type, payload } = event.data;
    if (typeof type !== 'string' || !type.startsWith('checkout:')) {
        return null;
    }

    return { type, payload };
}

export function postOverlayMessage(targetWindow, type, payload = {}) {
    if (!targetWindow || typeof targetWindow.postMessage !== 'function') {
        return;
    }
    targetWindow.postMessage({ type, payload }, '*');
}
