/**
 * utils.js
 * Small DOM and formatting helpers.
 */

export function qs(selector, scope = document) {
  return scope.querySelector(selector);
}

export function qsa(selector, scope = document) {
  return Array.from(scope.querySelectorAll(selector));
}

export function formatDate(date) {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

/**
 * Debounce - delays execution until after wait ms since last call.
 */
export function debounce(fn, wait = 300) {
  let t;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...args), wait);
  };
}

/**
 * Throttle - ensures fn only runs at most once every wait ms.
 */
export function throttle(fn, wait = 300) {
  let last = 0;
  return (...args) => {
    const now = Date.now();
    if (now - last >= wait) {
      last = now;
      fn(...args);
    }
  };
}

/**
 * Safe JSON parse with fallback.
 */
export function safeJson(str, fallback = {}) {
  try {
    return JSON.parse(str);
  } catch {
    return fallback;
  }
}

/**
 * Toggle class conditionally.
 */
export function toggleClass(el, className, condition) {
  if (!el) return;
  el.classList[condition ? 'add' : 'remove'](className);
}