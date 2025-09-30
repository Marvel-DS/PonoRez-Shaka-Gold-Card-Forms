const listeners = new Map();

export function on(eventName, handler) {
    if (!listeners.has(eventName)) {
        listeners.set(eventName, new Set());
    }

    const handlers = listeners.get(eventName);
    handlers.add(handler);

    return () => off(eventName, handler);
}

export function once(eventName, handler) {
    const unsubscribe = on(eventName, (payload) => {
        unsubscribe();
        handler(payload);
    });

    return unsubscribe;
}

export function off(eventName, handler) {
    const handlers = listeners.get(eventName);
    if (!handlers) {
        return;
    }

    handlers.delete(handler);

    if (handlers.size === 0) {
        listeners.delete(eventName);
    }
}

export function emit(eventName, detail) {
    const handlers = listeners.get(eventName);
    if (!handlers) {
        return;
    }

    handlers.forEach((handler) => {
        try {
            handler(detail);
        } catch (error) {
            console.error(`Error in event handler for ${eventName}`, error);
        }
    });
}
