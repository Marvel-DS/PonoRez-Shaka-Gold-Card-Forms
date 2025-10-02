/**
 * api.js
 * Fetch wrapper for backend endpoints (PHP controllers).
 * Uses relative paths so project works under any folder name.
 */

function buildUrl(endpoint) {
  // Support absolute URLs (http...) or relative endpoints
  if (endpoint.startsWith("http")) {
    return endpoint;
  }

  // Base path injected by PHP (fallback: current directory)
  const base = window.SGC_BASE || ".";
  return `${base}/api/${endpoint}`;
}

export async function fetchData(endpoint, options = {}) {
  const url = buildUrl(endpoint);

  const opts = {
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    ...options,
  };

  if (opts.body && typeof opts.body === 'object') {
    opts.body = JSON.stringify(opts.body);
  }

  try {
    const response = await fetch(url, opts);
    if (!response.ok) {
      throw new Error(`API error: ${response.status} ${response.statusText}`);
    }
    return await response.json();
  } catch (err) {
    console.error("fetchData failed:", err);
    return null;
  }
}