import { resolveApiUrl } from '../core/config.js';
import { requestJson } from '../core/api-client.js';
import { getState, setState, subscribe } from '../core/store.js';
import { qs, qsa, clamp } from '../utility/dom.js';
import { formatCurrency } from '../utility/formating.js';
import { showError } from './alerts.js';

let root;
let debounceTimer;
let controller;
let lastSignature = null;

function getPriceMap(details) {
    return details.reduce((accumulator, detail) => {
        if (!detail || detail.id === undefined) {
            return accumulator;
        }
        accumulator[String(detail.id)] = detail.price;
        return accumulator;
    }, {});
}

function updateLoading(isLoading) {
    setState((current) => ({
        loading: { ...current.loading, guestTypes: isLoading },
    }));

    if (!root) {
        return;
    }
    root.classList.toggle('opacity-60', isLoading);
    root.classList.toggle('pointer-events-none', isLoading);
}

function updateSignature() {
    const state = getState();
    lastSignature = JSON.stringify({
        date: state.selectedDate,
        guestCounts: state.guestCounts,
    });
}

function buildRequestUrl() {
    const state = getState();
    const base = resolveApiUrl('guestTypes');
    if (!base) {
        return null;
    }

    const url = new URL(base);
    if (state.selectedDate) {
        url.searchParams.set('date', state.selectedDate);
    }

    const counts = state.guestCounts || {};
    if (Object.keys(counts).length > 0) {
        url.searchParams.set('guestCounts', JSON.stringify(counts));
    }

    return url.toString();
}

async function fetchGuestTypes() {
    const state = getState();
    const signature = JSON.stringify({
        date: state.selectedDate,
        guestCounts: state.guestCounts,
    });

    if (signature === lastSignature) {
        return;
    }

    updateSignature();

    const url = buildRequestUrl();
    if (!url) {
        return;
    }

    if (controller) {
        controller.abort();
    }

    controller = new AbortController();

    updateLoading(true);

    try {
        const payload = await requestJson(url, { signal: controller.signal });
        const details = Array.isArray(payload.guestTypes) ? payload.guestTypes : [];
        setState((current) => ({
            guestTypeDetails: details,
            loading: { ...current.loading, guestTypes: false },
        }));
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }
        console.error('Unable to load guest types', error);
        showError(error.message || 'Unable to load guest type details.');
        setState((current) => ({
            loading: { ...current.loading, guestTypes: false },
        }));
    }
}

function debouncedFetch() {
    window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(fetchGuestTypes, 250);
}

function ensureWithinBounds(value, min, max) {
    if (Number.isFinite(max) && max >= 0) {
        return clamp(value, min, max);
    }
    return Math.max(value, min);
}

function updateGuestCount(id, nextValue) {
    setState((current) => ({
        guestCounts: {
            ...current.guestCounts,
            [id]: nextValue,
        },
    }));

    debouncedFetch();
}

function handleCounterButton(event, container) {
    const button = event.currentTarget;
    const action = button.dataset.action;
    const input = qs('input[type="number"]', container);
    if (!input) {
        return;
    }

    const id = container.dataset.guestType;
    const min = Number(container.dataset.min || input.min || 0);
    const max = container.dataset.max !== undefined ? Number(container.dataset.max) : Number(input.max || Infinity);
    const currentValue = Number(input.value || 0);
    const delta = action === 'decrement' ? -1 : 1;
    const nextValue = ensureWithinBounds(currentValue + delta, min, max);

    input.value = String(nextValue);
    updateGuestCount(id, nextValue);
}

function handleInputChange(event, container) {
    const input = event.currentTarget;
    const id = container.dataset.guestType;
    const min = Number(container.dataset.min || input.min || 0);
    const maxRaw = container.dataset.max !== undefined ? Number(container.dataset.max) : undefined;
    const max = Number.isFinite(maxRaw) ? maxRaw : (input.max !== '' ? Number(input.max) : undefined);
    const value = Number(input.value || 0);
    const nextValue = ensureWithinBounds(value, min, Number.isFinite(max) ? max : Number.POSITIVE_INFINITY);
    input.value = String(nextValue);
    updateGuestCount(id, nextValue);
}

function syncFromState(state) {
    const priceLookup = getPriceMap(state.guestTypeDetails || []);

    qsa('[data-guest-type]', root).forEach((container) => {
        const id = container.dataset.guestType;
        const input = qs('input[type="number"]', container);
        const priceElement = qs('[data-guest-price]', container);

        if (!id || !input) {
            return;
        }

        const detail = (state.guestTypeDetails || []).find((item) => String(item.id) === id) || null;
        const min = detail && detail.min !== undefined
            ? Number(detail.min)
            : Number(container.dataset.min || input.min || 0);
        const max = detail && detail.max !== undefined
            ? Number(detail.max)
            : (container.dataset.max !== undefined ? Number(container.dataset.max) : Number(input.max || Infinity));
        const stateValue = Number(state.guestCounts[id] ?? min);
        const boundedValue = ensureWithinBounds(stateValue, min, max);

        if (Number(input.value || 0) !== boundedValue) {
            input.value = String(boundedValue);
        }

        if (priceElement) {
            const price = priceLookup[id];
            if (price === null || price === undefined) {
                priceElement.textContent = 'Price available at checkout';
            } else if (Number(price) === 0) {
                priceElement.textContent = 'Included';
            } else {
                priceElement.textContent = `${formatCurrency(price, {
                    currency: state.currency.code,
                    locale: state.currency.locale,
                })} each`;
            }
        }

        if (Number.isFinite(max) && max >= 0 && max !== Number.POSITIVE_INFINITY) {
            container.dataset.max = String(max);
            input.max = String(max);
        }

        container.dataset.min = String(min);
        input.min = String(min);
    });
}

export function initGuestTypes() {
    root = qs('[data-component="guest-types"]');
    if (!root) {
        return;
    }

    qsa('[data-guest-type]', root).forEach((container) => {
        const decrement = qs('[data-action="decrement"]', container);
        const increment = qs('[data-action="increment"]', container);
        const input = qs('input[type="number"]', container);

        if (decrement) {
            decrement.addEventListener('click', (event) => handleCounterButton(event, container));
        }

        if (increment) {
            increment.addEventListener('click', (event) => handleCounterButton(event, container));
        }

        if (input) {
            input.addEventListener('change', (event) => handleInputChange(event, container));
            input.addEventListener('blur', (event) => handleInputChange(event, container));
        }
    });

    subscribe((state) => {
        syncFromState(state);

        const signature = JSON.stringify({
            date: state.selectedDate,
            guestCounts: state.guestCounts,
        });

        if (signature !== lastSignature) {
            debouncedFetch();
        }
    });

    // Seed state from initial DOM values.
    const initialCounts = qsa('[data-guest-type]', root).reduce((accumulator, container) => {
        const id = container.dataset.guestType;
        const input = qs('input[type="number"]', container);
        if (!id || !input) {
            return accumulator;
        }
        accumulator[id] = Number(input.value || 0);
        return accumulator;
    }, {});

    if (Object.keys(initialCounts).length > 0) {
        setState({ guestCounts: initialCounts });
    }

    fetchGuestTypes();
}

export default initGuestTypes;
