import { resolveApiUrl } from '../core/config.js';
import { requestJson } from '../core/api-client.js';
import { getState, setState, subscribe } from '../core/store.js';
import { qs, qsa, clamp } from '../utility/dom.js';
import { formatCurrency } from '../utility/formating.js';
import { showError } from './alerts.js';
import { resolveGuestRange } from './guest-types-range.js';

let root;
let debounceTimer;
let controller;
let lastSignature = null;

const FETCH_DEBOUNCE_MS = 250;
const DEFAULT_GUEST_RANGE = 10;

function showPriceSpinner(priceElement) {
    if (!priceElement) {
        return;
    }

    let spinnerWrapper = priceElement.querySelector('[data-price-spinner]');
    if (!spinnerWrapper) {
        const spinner = document.createElement('span');
        spinner.className = 'h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-blue-600';
        spinner.setAttribute('aria-hidden', 'true');

        const srText = document.createElement('span');
        srText.className = 'sr-only';
        srText.textContent = 'Price available at checkout';

        spinnerWrapper = document.createElement('span');
        spinnerWrapper.dataset.priceSpinner = '';
        spinnerWrapper.className = 'inline-flex items-center justify-end';
        spinnerWrapper.append(spinner, srText);
    }

    priceElement.textContent = '';
    priceElement.appendChild(spinnerWrapper);
}

function hidePriceSpinner(priceElement) {
    if (!priceElement) {
        return;
    }

    const spinnerWrapper = priceElement.querySelector('[data-price-spinner]');
    if (spinnerWrapper) {
        spinnerWrapper.remove();
    }
}

function normaliseGuestTypeConfig(rawConfig) {
    const safeObject = (value) => (value && typeof value === 'object' ? value : {});

    if (!rawConfig || typeof rawConfig !== 'object') {
        return {
            ids: [],
            labels: {},
            descriptions: {},
            min: {},
            max: {},
        };
    }

    const ids = Array.isArray(rawConfig.ids) ? rawConfig.ids.map((value) => String(value)) : [];

    return {
        ids,
        labels: safeObject(rawConfig.labels),
        descriptions: safeObject(rawConfig.descriptions),
        min: safeObject(rawConfig.min),
        max: safeObject(rawConfig.max),
    };
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

function mergeGuestTypeDetails(state, apiDetails) {
    const config = normaliseGuestTypeConfig(state.bootstrap?.activity?.guestTypes);
    const source = new Map();

    (Array.isArray(apiDetails) ? apiDetails : []).forEach((detail) => {
        if (!detail || detail.id === undefined) {
            return;
        }
        source.set(String(detail.id), detail);
    });

    if (config.ids.length === 0 && source.size > 0) {
        return Array.from(source.values()).map((detail) => {
            const minCandidate = Number(detail.min);
            const maxCandidate = Number(detail.max);
            const min = Number.isFinite(minCandidate) ? Math.max(0, Math.floor(minCandidate)) : 0;
            let max = Number.isFinite(maxCandidate) ? Math.max(min, Math.floor(maxCandidate)) : min;
            if (max < min) {
                max = min;
            }

            const price = Number(detail.price);
            return {
                id: String(detail.id),
                label: detail.label ? String(detail.label) : String(detail.id),
                description: detail.description ? String(detail.description) : null,
                price: Number.isFinite(price) ? price : null,
                min,
                max,
            };
        });
    }

    return config.ids.map((id) => {
        const detail = source.get(id) || null;

        const configuredLabel = config.labels[id];
        const configuredDescription = config.descriptions[id];

        const label = configuredLabel !== undefined && configuredLabel !== null && configuredLabel !== ''
            ? String(configuredLabel)
            : detail && detail.label ? String(detail.label) : id;

        const description = configuredDescription !== undefined && configuredDescription !== null && configuredDescription !== ''
            ? String(configuredDescription)
            : detail && detail.description ? String(detail.description) : '';

        const minConfig = config.min[id];
        const maxConfig = config.max[id];

        const minCandidate = minConfig !== undefined ? Number(minConfig)
            : detail && detail.min !== undefined ? Number(detail.min)
                : 0;

        const maxCandidate = maxConfig !== undefined ? Number(maxConfig)
            : detail && detail.max !== undefined ? Number(detail.max)
                : minCandidate;

        const min = Number.isFinite(minCandidate) ? Math.max(0, Math.floor(minCandidate)) : 0;
        let max = Number.isFinite(maxCandidate) ? Math.max(min, Math.floor(maxCandidate)) : min;
        if (max < min) {
            max = min;
        }

        const rawPrice = detail && detail.price !== undefined ? Number(detail.price) : null;
        const price = Number.isFinite(rawPrice) ? rawPrice : null;

        return {
            id,
            label,
            description: description || null,
            price,
            min,
            max,
        };
    });
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

    const url = buildRequestUrl();
    if (!url) {
        return;
    }

    updateSignature();

    if (controller) {
        controller.abort();
    }

    controller = new AbortController();

    updateLoading(true);

    try {
        const payload = await requestJson(url, { signal: controller.signal });
        const merged = mergeGuestTypeDetails(state, payload.guestTypes);
        setState({ guestTypeDetails: merged });
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }
        console.error('Unable to load guest types', error);
        showError(error.message || 'Unable to load guest type details.');
        lastSignature = null;
    } finally {
        controller = null;
        updateLoading(false);
    }
}

function debouncedFetch() {
    window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(fetchGuestTypes, FETCH_DEBOUNCE_MS);
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

function buildSelectOptions(select, min, max) {
    if (!select) {
        return;
    }

    const start = Number.isFinite(min) ? min : 0;
    const end = Number.isFinite(max) ? Math.max(start, max) : start;
    const optionCount = end - start + 1;

    const existingValues = Array.from(select.options, (option) => Number(option.value));
    const isSequential = existingValues.length === optionCount
        && existingValues[0] === start
        && existingValues.every((value, index) => value === start + index);

    if (isSequential) {
        return;
    }

    select.innerHTML = '';
    for (let value = start; value <= end; value += 1) {
        const option = document.createElement('option');
        option.value = String(value);
        option.textContent = String(value);
        select.appendChild(option);
    }
}

function handleSelectChange(event, container) {
    const select = event.currentTarget;
    if (!select || !container) {
        return;
    }

    const id = container.dataset.guestType;
    if (!id) {
        return;
    }

    const min = Number(container.dataset.min ?? 0);
    const maxRaw = container.dataset.max !== undefined ? Number(container.dataset.max) : Number.POSITIVE_INFINITY;
    const max = Number.isFinite(maxRaw) ? maxRaw : Number.POSITIVE_INFINITY;

    const value = Number(select.value || 0);
    const bounded = ensureWithinBounds(value, Number.isFinite(min) ? min : 0, max);
    if (Number(select.value) !== bounded) {
        select.value = String(bounded);
    }

    updateGuestCount(id, bounded);
}

function handleCheckboxChange(event, container) {
    const checkbox = event.currentTarget;
    if (!checkbox || !container) {
        return;
    }

    const id = container.dataset.guestType;
    if (!id) {
        return;
    }

    const minRaw = Number(container.dataset.min ?? 0);
    const min = Number.isFinite(minRaw) ? minRaw : 0;
    const maxRaw = container.dataset.max !== undefined ? Number(container.dataset.max) : Number.POSITIVE_INFINITY;
    const max = Number.isFinite(maxRaw) ? maxRaw : Number.POSITIVE_INFINITY;

    const checkedValueRaw = Number(checkbox.dataset.checkedValue ?? 1);
    const uncheckedValueRaw = Number(checkbox.dataset.uncheckedValue ?? 0);
    const checkedValue = Number.isFinite(checkedValueRaw) ? checkedValueRaw : 1;
    const uncheckedValue = Number.isFinite(uncheckedValueRaw) ? uncheckedValueRaw : 0;

    const value = checkbox.checked ? checkedValue : uncheckedValue;
    const bounded = ensureWithinBounds(value, min, max);

    const shouldCheck = bounded === checkedValue;
    if (checkbox.checked !== shouldCheck) {
        checkbox.checked = shouldCheck;
    }

    const hiddenInput = qs('[data-guest-checkbox-input]', container);
    if (hiddenInput) {
        hiddenInput.value = String(bounded);
    }

    updateGuestCount(id, bounded);
}

function syncFromState(state) {
    if (!root) {
        return;
    }

    const details = state.guestTypeDetails || [];
    const detailMap = new Map(details.map((item) => [String(item.id), item]));
    const currencyCode = state.currency?.code || 'USD';
    const currencyLocale = state.currency?.locale || 'en-US';
    const config = normaliseGuestTypeConfig(state.bootstrap?.activity?.guestTypes);

    qsa('[data-guest-type]', root).forEach((container) => {
        const id = container.dataset.guestType;
        if (!id) {
            return;
        }

        const select = qs('[data-guest-select]', container);
        const checkbox = qs('[data-guest-checkbox]', container);
        const checkboxInput = qs('[data-guest-checkbox-input]', container);
        const labelElement = qs('[data-guest-label]', container);
        const descriptionElement = qs('[data-guest-description]', container);
        const priceElement = qs('[data-guest-price]', container);

        const detail = detailMap.get(id) || null;

        const configuredLabel = config.labels[id];
        const configuredDescription = config.descriptions[id];

        const labelText = configuredLabel !== undefined && configuredLabel !== null && configuredLabel !== ''
            ? String(configuredLabel)
            : detail && detail.label ? String(detail.label) : id;

        const descriptionText = configuredDescription !== undefined && configuredDescription !== null && configuredDescription !== ''
            ? String(configuredDescription)
            : detail && detail.description ? String(detail.description) : '';

        const min = detail && detail.min !== undefined ? Number(detail.min)
            : config.min[id] !== undefined ? Number(config.min[id])
                : Number(container.dataset.min ?? 0);

        const hasExplicitMax = (detail && detail.max !== undefined) || (config.max[id] !== undefined);
        const max = detail && detail.max !== undefined ? Number(detail.max)
            : config.max[id] !== undefined ? Number(config.max[id])
                : Number(container.dataset.max ?? min);

        const previousMax = Number(container.dataset.max);
        const fallbackAttribute = Number(container.dataset.fallbackMax);
        const fallbackCandidate = Number.isFinite(fallbackAttribute)
            ? fallbackAttribute
            : Number.isFinite(previousMax)
                ? previousMax
                : Number.NaN;

        const { min: boundedMin, max: boundedMax, fallbackMax } = resolveGuestRange({
            min,
            max,
            fallbackMax: fallbackCandidate,
            hasExplicitMax,
            defaultRange: DEFAULT_GUEST_RANGE,
        });

        buildSelectOptions(select, boundedMin, boundedMax);

        const stateValue = Number(state.guestCounts?.[id] ?? boundedMin);
        const boundedValue = ensureWithinBounds(stateValue, boundedMin, boundedMax);

        if (select && Number(select.value) !== boundedValue) {
            select.value = String(boundedValue);
        }

        if (checkbox) {
            const checkedValueRaw = Number(checkbox.dataset.checkedValue ?? boundedMin);
            const checkedValue = Number.isFinite(checkedValueRaw) ? checkedValueRaw : boundedMin;

            checkbox.checked = boundedValue === checkedValue;
            checkbox.value = String(checkedValue);

            if (checkboxInput) {
                checkboxInput.value = String(boundedValue);
            }

            if (boundedMin === boundedMax) {
                checkbox.indeterminate = false;
            }
        }

        container.dataset.min = String(boundedMin);
        container.dataset.max = String(boundedMax);
        container.dataset.fallbackMax = String(fallbackMax);

        if (labelElement) {
            labelElement.textContent = labelText;
        }

        if (descriptionElement) {
            if (descriptionText) {
                descriptionElement.textContent = descriptionText;
                descriptionElement.classList.remove('hidden');
            } else {
                descriptionElement.textContent = '';
                descriptionElement.classList.add('hidden');
            }
        }

        if (priceElement) {
            if (!detail || detail.price === null || detail.price === undefined || Number.isNaN(Number(detail.price))) {
                showPriceSpinner(priceElement);
            } else if (Number(detail.price) === 0) {
                hidePriceSpinner(priceElement);
                priceElement.textContent = 'Included';
            } else {
                hidePriceSpinner(priceElement);
                priceElement.textContent = formatCurrency(Number(detail.price), {
                    currency: currencyCode,
                    locale: currencyLocale,
                });
            }
        }
    });
}

export function initGuestTypes() {
    root = qs('[data-component="guest-types"]');
    if (!root) {
        return;
    }

    qsa('[data-guest-type]', root).forEach((container) => {
        const select = qs('[data-guest-select]', container);
        if (select) {
            select.addEventListener('change', (event) => handleSelectChange(event, container));
        }

        const checkbox = qs('[data-guest-checkbox]', container);
        if (checkbox) {
            checkbox.addEventListener('change', (event) => handleCheckboxChange(event, container));
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

    const initialCounts = qsa('[data-guest-type]', root).reduce((accumulator, container) => {
        const id = container.dataset.guestType;
        const select = qs('[data-guest-select]', container);
        const checkbox = qs('[data-guest-checkbox]', container);
        const checkboxInput = qs('[data-guest-checkbox-input]', container);

        if (!id) {
            return accumulator;
        }

        if (select) {
            accumulator[id] = Number(select.value || 0);
            return accumulator;
        }

        if (checkboxInput) {
            accumulator[id] = Number(checkboxInput.value || 0);
            return accumulator;
        }

        if (checkbox) {
            const checkedValueRaw = Number(checkbox.dataset.checkedValue ?? 1);
            const uncheckedValueRaw = Number(checkbox.dataset.uncheckedValue ?? 0);
            const checkedValue = Number.isFinite(checkedValueRaw) ? checkedValueRaw : 1;
            const uncheckedValue = Number.isFinite(uncheckedValueRaw) ? uncheckedValueRaw : 0;
            accumulator[id] = checkbox.checked ? checkedValue : uncheckedValue;
        }

        return accumulator;
    }, {});

    if (Object.keys(initialCounts).length > 0) {
        setState({ guestCounts: initialCounts });
    }

    fetchGuestTypes();
}

export default initGuestTypes;
