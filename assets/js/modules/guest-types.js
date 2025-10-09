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
        spinner.className = 'h-4 w-4 animate-spin rounded-full border-2 border-slate-300';
        spinner.style.borderTopColor = 'var(--sgc-brand-primary, #1C55DB)';
        spinner.setAttribute('aria-hidden', 'true');

        const srText = document.createElement('span');
        srText.className = 'sr-only';
        srText.textContent = 'Price available at checkout';

        spinnerWrapper = document.createElement('span');
        spinnerWrapper.dataset.priceSpinner = '';
        spinnerWrapper.className = 'inline-flex items-center justify-end';
        spinnerWrapper.append(spinner, srText);
    } else {
        const spinner = spinnerWrapper.querySelector('span');
        if (spinner) {
            spinner.style.borderTopColor = 'var(--sgc-brand-primary, #1C55DB)';
        }
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

function getConfiguredGuestTypes(state) {
    return Array.isArray(state?.bootstrap?.activity?.guestTypes?.collection)
        ? state.bootstrap.activity.guestTypes.collection
        : [];
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
    const collection = getConfiguredGuestTypes(state);
    const source = new Map();

    (Array.isArray(apiDetails) ? apiDetails : []).forEach((detail) => {
        if (!detail || detail.id === undefined) {
            return;
        }
        source.set(String(detail.id), detail);
    });

    const resolveQuantity = (value, fallback = 0) => {
        const numeric = Number(value);
        return Number.isFinite(numeric) ? Math.max(0, Math.floor(numeric)) : fallback;
    };

    const buildFromEntry = (entry, detail) => {
        const id = String(entry.id);
        const minConfig = entry.minQuantity !== undefined && entry.minQuantity !== null
            ? resolveQuantity(entry.minQuantity, undefined)
            : undefined;
        const maxConfig = entry.maxQuantity !== undefined && entry.maxQuantity !== null
            ? resolveQuantity(entry.maxQuantity, undefined)
            : undefined;

        const minCandidate = minConfig ?? resolveQuantity(detail?.min, 0);
        let maxCandidate = maxConfig ?? resolveQuantity(detail?.max, minCandidate);

        if (!Number.isFinite(maxCandidate) || maxCandidate < minCandidate) {
            maxCandidate = minCandidate;
        }

        const label = typeof entry.label === 'string' && entry.label.trim() !== ''
            ? entry.label.trim()
            : detail && detail.label ? String(detail.label) : id;

        const description = typeof entry.description === 'string' && entry.description.trim() !== ''
            ? entry.description.trim()
            : detail && detail.description ? String(detail.description) : null;

        const price = Number.isFinite(Number(entry.price))
            ? Number(entry.price)
            : (detail && Number.isFinite(Number(detail.price)) ? Number(detail.price) : null);

        return {
            id,
            label,
            description: description || null,
            price,
            min: minCandidate,
            max: maxCandidate,
        };
    };

    if (collection.length > 0) {
        return collection.map((entry) => {
            if (!entry || entry.id === undefined) {
                return null;
            }

            const id = String(entry.id);
            if (id === '') {
                return null;
            }

            const detail = source.get(id) || null;
            const resolved = buildFromEntry(entry, detail);
            if (resolved.max <= resolved.min) {
                resolved.max = resolved.min + DEFAULT_GUEST_RANGE;
            }
            return resolved;
        }).filter(Boolean);
    }

    if (source.size === 0) {
        return [];
    }

    return Array.from(source.values()).map((detail) => {
        const min = resolveQuantity(detail.min, 0);
        let max = resolveQuantity(detail.max, min);
        if (max <= min) {
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
    const collection = getConfiguredGuestTypes(state);
    const collectionMap = new Map(
        collection
            .filter((entry) => entry && entry.id !== undefined)
            .map((entry) => [String(entry.id), entry])
    );

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

        const configEntry = collectionMap.get(id) || null;
        const configuredLabel = configEntry && typeof configEntry.label === 'string' ? configEntry.label.trim() : '';
        const configuredDescription = configEntry && typeof configEntry.description === 'string'
            ? configEntry.description.trim()
            : '';

        const labelText = configuredLabel !== ''
            ? configuredLabel
            : detail && detail.label ? String(detail.label) : id;

        const descriptionText = configuredDescription !== ''
            ? configuredDescription
            : detail && detail.description ? String(detail.description) : '';

        const min = detail && detail.min !== undefined ? Number(detail.min)
            : configEntry && configEntry.minQuantity !== undefined ? Number(configEntry.minQuantity)
                : Number(container.dataset.min ?? 0);

        const hasConfigMax = configEntry && configEntry.maxQuantity !== undefined && configEntry.maxQuantity !== null;
        const hasDetailMax = detail && detail.max !== undefined && detail.max !== null;
        const hasExplicitMax = hasDetailMax || hasConfigMax;

        const max = hasDetailMax ? Number(detail.max)
            : hasConfigMax ? Number(configEntry.maxQuantity)
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
