const bootstrapElement = document.getElementById('sgc-bootstrap');

let bootstrap = typeof window !== 'undefined' ? window.__SGC_BOOTSTRAP__ : {};
if (!bootstrap || typeof bootstrap !== 'object') {
    bootstrap = {};
}

if (Object.keys(bootstrap).length === 0 && bootstrapElement) {
    try {
        bootstrap = JSON.parse(bootstrapElement.textContent || '{}');
    } catch (error) {
        console.error('Failed to parse bootstrap payload', error);
        bootstrap = {};
    }
}

const apiEndpoints = typeof window !== 'undefined' ? window.__SGC_API_ENDPOINTS__ : {};

export function getBootstrap() {
    return bootstrap;
}

export function getApiEndpoints() {
    return { ...apiEndpoints };
}

export function resolveApiUrl(key, params = {}) {
    if (!apiEndpoints || typeof apiEndpoints !== 'object') {
        return null;
    }

    const base = apiEndpoints[key];
    if (!base) {
        return null;
    }

    let url;
    try {
        url = new URL(base, window.location.origin);
    } catch (error) {
        console.error('Invalid API endpoint', base, error);
        return null;
    }

    Object.entries(params).forEach(([name, value]) => {
        if (value === undefined || value === null || value === '') {
            return;
        }
        url.searchParams.set(name, String(value));
    });

    return url.toString();
}

export function updateBootstrap(partial) {
    bootstrap = { ...bootstrap, ...partial };
}
