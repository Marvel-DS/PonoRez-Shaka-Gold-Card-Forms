const defaultHeaders = {
    Accept: 'application/json',
};

function buildRequestOptions(method = 'GET', data, signal) {
    const options = {
        method,
        headers: { ...defaultHeaders },
        credentials: 'same-origin',
        cache: 'no-store',
    };

    if (signal) {
        options.signal = signal;
    }

    if (data !== undefined && data !== null) {
        options.body = typeof data === 'string' ? data : JSON.stringify(data);
        options.headers['Content-Type'] = 'application/json';
    }

    return options;
}

async function parseJsonResponse(response) {
    const contentType = response.headers.get('content-type') || '';
    const isJson = contentType.includes('application/json');
    if (!isJson) {
        const text = await response.text();
        return { status: response.ok ? 'ok' : 'error', message: text };
    }

    try {
        return await response.json();
    } catch (error) {
        console.error('Failed to parse JSON response', error);
        return { status: 'error', message: 'Invalid JSON response.' };
    }
}

export async function requestJson(url, { method = 'GET', data, signal } = {}) {
    const options = buildRequestOptions(method, data, signal);

    const response = await fetch(url, options);
    const payload = await parseJsonResponse(response);

    if (!response.ok || (payload && payload.status && payload.status !== 'ok')) {
        const message = payload && typeof payload.message === 'string'
            ? payload.message
            : `Request failed with status ${response.status}`;
        const error = new Error(message);
        error.status = response.status;
        error.payload = payload;
        throw error;
    }

    return payload;
}

export async function postJson(url, data, options = {}) {
    return requestJson(url, { ...options, method: 'POST', data });
}
