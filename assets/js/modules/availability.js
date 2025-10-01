import { resolveApiUrl } from '../core/config.js';
import { requestJson } from '../core/api-client.js';
import { getState, setState, subscribe } from '../core/store.js';
import { qs, clearChildren, toggleHidden, createElement } from '../utility/dom.js';
import { formatDateLong } from '../utility/formating.js';
import { pluralize } from '../utility/strings.js';
import { showError } from './alerts.js';

let availabilityPanel;
let timeslotList;
let summaryBanner;
let loadingState;
let emptyState;

let fetchTimer;
let controller;
let lastSignature = null;
let lastVisibleMonth;

function normaliseId(value) {
    if (value === null || value === undefined) {
        return null;
    }

    const stringValue = String(value).trim();
    return stringValue === '' ? null : stringValue;
}

function getConfiguredActivityOrder(state) {
    const ids = state.bootstrap?.activity?.ids;
    if (!Array.isArray(ids)) {
        return [];
    }

    return ids
        .map((id) => normaliseId(id))
        .filter((id) => id !== null);
}

function getDepartureLabelMap(state) {
    const labels = state.bootstrap?.activity?.departureLabels;
    if (!labels || typeof labels !== 'object') {
        return {};
    }

    return Object.entries(labels).reduce((accumulator, [key, value]) => {
        const normalisedKey = normaliseId(key);
        if (normalisedKey === null) {
            return accumulator;
        }

        accumulator[normalisedKey] = value !== undefined && value !== null ? String(value) : `Departure ${normalisedKey}`;
        return accumulator;
    }, {});
}

function getAvailableIdsFromMetadata(metadata, date) {
    if (!metadata || typeof metadata !== 'object' || !date) {
        return [];
    }

    const extended = metadata.extended;
    if (!extended || typeof extended !== 'object') {
        return [];
    }

    const entry = extended[date];
    if (!Array.isArray(entry)) {
        return [];
    }

    return entry
        .map((id) => normaliseId(id))
        .filter((id) => id !== null);
}

function deriveTimeslotsFromSources(state, payloadTimeslots, metadata) {
    if (Array.isArray(payloadTimeslots) && payloadTimeslots.length > 0) {
        const availableIds = getAvailableIdsFromMetadata(metadata, state.selectedDate);
        if (availableIds.length === 0) {
            return payloadTimeslots;
        }

        const availableSet = new Set(availableIds);
        const filtered = payloadTimeslots.filter((slot) => availableSet.has(slot.id));
        if (filtered.length > 0) {
            return filtered;
        }

        return payloadTimeslots;
    }

    const availableIds = getAvailableIdsFromMetadata(metadata, state.selectedDate);
    if (availableIds.length === 0) {
        return [];
    }

    const uniqueIds = Array.from(new Set(availableIds));
    const order = getConfiguredActivityOrder(state);
    const orderIndex = new Map();
    order.forEach((id, index) => {
        if (!orderIndex.has(id)) {
            orderIndex.set(id, index);
        }
    });

    const labels = getDepartureLabelMap(state);

    uniqueIds.sort((a, b) => {
        const orderA = orderIndex.has(a) ? orderIndex.get(a) : Number.POSITIVE_INFINITY;
        const orderB = orderIndex.has(b) ? orderIndex.get(b) : Number.POSITIVE_INFINITY;

        if (orderA !== orderB) {
            return orderA - orderB;
        }

        const numericA = Number.parseInt(a, 10);
        const numericB = Number.parseInt(b, 10);

        if (Number.isFinite(numericA) && Number.isFinite(numericB) && numericA !== numericB) {
            return numericA - numericB;
        }

        return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
    });

    return uniqueIds.map((id) => ({
        id,
        label: labels[id] || `Departure ${id}`,
        available: null,
    }));
}

function isLastDayOfMonth(isoDate) {
    if (typeof isoDate !== 'string' || isoDate.length < 10) {
        return false;
    }

    const date = new Date(`${isoDate}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
        return false;
    }

    const nextDay = new Date(date);
    nextDay.setDate(date.getDate() + 1);
    return nextDay.getDate() === 1;
}

function mapCalendar(calendar = []) {
    return calendar.reduce((accumulator, entry) => {
        if (!entry || !entry.date) {
            return accumulator;
        }

        accumulator.push({
            date: String(entry.date),
            status: String(entry.status || 'unknown').toLowerCase(),
        });

        return accumulator;
    }, []);
}

function mapTimeslots(timeslots = []) {
    return timeslots.reduce((accumulator, slot) => {
        if (!slot || slot.id === undefined) {
            return accumulator;
        }

        const id = String(slot.id);
        const label = slot.label ? String(slot.label) : id;
        const available = slot.available !== undefined && slot.available !== null
            ? Number(slot.available)
            : null;

        accumulator.push({ id, label, available });
        return accumulator;
    }, []);
}

function buildAvailabilityUrl() {
    const state = getState();
    const base = resolveApiUrl('availability');
    if (!base) {
        return null;
    }

    const url = new URL(base);
    if (state.selectedDate) {
        url.searchParams.set('date', state.selectedDate);
    }

    if (state.visibleMonth) {
        url.searchParams.set('month', state.visibleMonth);
    }

    const activityIds = state.bootstrap?.activity?.ids;
    if (Array.isArray(activityIds) && activityIds.length > 0) {
        url.searchParams.set('activityIds', JSON.stringify(activityIds));
    }

    const guestCounts = state.guestCounts || {};
    if (Object.keys(guestCounts).length > 0) {
        url.searchParams.set('guestCounts', JSON.stringify(guestCounts));
    }

    return url.toString();
}

function setLoading(isLoading) {
    setState((current) => ({
        loading: { ...current.loading, availability: isLoading },
    }));
}

function describeAvailability(timeslot) {
    if (timeslot.available === null || timeslot.available === undefined) {
        return 'Availability will be confirmed during checkout.';
    }

    if (timeslot.available <= 0) {
        return 'Sold out';
    }

    if (timeslot.available <= 4) {
        return `${timeslot.available} ${pluralize('spot', timeslot.available)} left`;
    }

    return `${timeslot.available} seats available`;
}

function renderTimeslots(state) {
    if (!availabilityPanel || !timeslotList) {
        return;
    }

    const { timeslots, selectedTimeslotId } = state;
    const isLoading = state.loading?.availability;
    const hasTimeslots = Array.isArray(timeslots) && timeslots.length > 0;

    toggleHidden(loadingState, !isLoading);
    toggleHidden(emptyState, isLoading || hasTimeslots);
    toggleHidden(timeslotList, isLoading || !hasTimeslots);

    if (!hasTimeslots) {
        clearChildren(timeslotList);
    }

    if (hasTimeslots) {
        clearChildren(timeslotList);

        timeslots.forEach((timeslot) => {
            const item = createElement('li', { className: 'flex items-stretch' });

            const label = createElement('label', {
                className: 'flex w-full items-center justify-between gap-4 px-4 py-3',
                attributes: { 'data-timeslot-id': timeslot.id },
            });

            const radioWrapper = createElement('div', { className: 'flex items-center gap-3' });
            const radio = createElement('input', {
                attributes: {
                    type: 'radio',
                    name: 'timeslotId',
                    value: timeslot.id,
                    class: 'h-4 w-4 border-slate-300 text-blue-600 focus:ring-blue-500',
                    'aria-label': `Select ${timeslot.label}`,
                },
            });

            if (timeslot.id === selectedTimeslotId) {
                radio.checked = true;
            }

            if (timeslot.available !== null && timeslot.available <= 0) {
                radio.disabled = true;
            }

            const labelText = createElement('span', {
                className: 'text-sm font-medium text-slate-900',
                text: timeslot.label,
            });

            radioWrapper.appendChild(radio);
            radioWrapper.appendChild(labelText);

            const availabilityText = createElement('span', {
                className: 'text-xs text-slate-500',
                text: describeAvailability(timeslot),
            });

            label.appendChild(radioWrapper);
            label.appendChild(availabilityText);
            item.appendChild(label);

            timeslotList.appendChild(item);
        });
    }

    if (summaryBanner) {
        const selected = (state.timeslots || []).find((slot) => slot.id === state.selectedTimeslotId);
        const readableDate = formatDateLong(state.selectedDate, state.currency?.locale || 'en-US');

        if (selected) {
            summaryBanner.textContent = `Selected departure: ${selected.label} on ${readableDate}`;
        } else if (hasTimeslots) {
            summaryBanner.textContent = 'Pick a departure time to continue.';
        } else {
            summaryBanner.textContent = 'Select a date to load available timeslots.';
        }
    }
}

async function fetchAvailability() {
    const state = getState();
    const signature = JSON.stringify({
        date: state.selectedDate,
        guestCounts: state.guestCounts,
    });

    if (signature === lastSignature) {
        return;
    }

    lastSignature = signature;

    const url = buildAvailabilityUrl();
    if (!url) {
        return;
    }

    if (controller) {
        controller.abort();
    }

    controller = new AbortController();
    setLoading(true);

    try {
        const payload = await requestJson(url, { signal: controller.signal });
        const calendarDays = mapCalendar(payload.calendar);
        const payloadTimeslots = mapTimeslots(payload.timeslots);
        const metadata = payload.metadata || {};

        setState((current) => {
            const derivedTimeslots = deriveTimeslotsFromSources(current, payloadTimeslots, metadata);
            const availableIds = derivedTimeslots.map((slot) => slot.id);
            let selectedTimeslotId = current.selectedTimeslotId;

            if (!selectedTimeslotId || !availableIds.includes(selectedTimeslotId)) {
                const firstAvailable = derivedTimeslots.find((slot) => slot.available === null || slot.available > 0);
                selectedTimeslotId = firstAvailable ? firstAvailable.id : null;
            }

            const updates = {
                calendarDays,
                timeslots: derivedTimeslots,
                availabilityMetadata: metadata,
                selectedTimeslotId,
                loading: { ...current.loading, availability: false },
            };

            const firstAvailableDate = typeof metadata.firstAvailableDate === 'string' && metadata.firstAvailableDate !== ''
                ? metadata.firstAvailableDate
                : null;
            const selectedStatus = typeof metadata.selectedDateStatus === 'string'
                ? metadata.selectedDateStatus.toLowerCase()
                : '';
            const envCurrentDate = current.bootstrap?.environment?.currentDate;

            const shouldAdvanceToFirstAvailable = Boolean(
                firstAvailableDate
                && envCurrentDate
                && current.selectedDate === envCurrentDate
                && isLastDayOfMonth(envCurrentDate)
                && !['available', 'limited'].includes(selectedStatus)
            );

            if (shouldAdvanceToFirstAvailable) {
                updates.selectedDate = firstAvailableDate;
                updates.visibleMonth = firstAvailableDate.slice(0, 7);
                updates.selectedTimeslotId = null;
                updates.timeslots = [];
                updates.calendarDays = [];
            }

            return updates;
        });

    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }
        console.error('Availability request failed', error);
        showError(error.message || 'Unable to load availability.');
        setState((current) => ({
            loading: { ...current.loading, availability: false },
        }));
    }
}

function scheduleFetch({ immediate = false } = {}) {
    window.clearTimeout(fetchTimer);

    if (immediate) {
        fetchAvailability();
        return;
    }

    fetchTimer = window.setTimeout(fetchAvailability, 300);
}

function handleTimeslotChange(event) {
    const target = event.target;
    if (!target || target.name !== 'timeslotId') {
        return;
    }

    setState({ selectedTimeslotId: target.value });
}

export function initAvailability() {
    availabilityPanel = qs('[data-component="timeslots"]');
    if (!availabilityPanel) {
        return;
    }

    timeslotList = qs('[data-timeslot-list]', availabilityPanel);
    summaryBanner = qs('[data-state="summary"]', availabilityPanel);
    loadingState = qs('[data-state="loading"]', availabilityPanel);
    emptyState = qs('[data-state="empty"]', availabilityPanel);

    if (timeslotList) {
        timeslotList.addEventListener('change', handleTimeslotChange);
    }

    subscribe((state) => {
        const visibleMonthChanged = lastVisibleMonth && state.visibleMonth !== lastVisibleMonth;

        renderTimeslots(state);

        lastVisibleMonth = state.visibleMonth;

        scheduleFetch({ immediate: Boolean(visibleMonthChanged) });
    });

    lastVisibleMonth = getState().visibleMonth;
    scheduleFetch();
}

export default initAvailability;
