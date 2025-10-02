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
let metadataDetails;

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

function collectIdsFromValues(values) {
    if (!Array.isArray(values)) {
        return [];
    }

    const ids = [];

    values.forEach((value) => {
        let candidate = value;
        if (value && typeof value === 'object') {
            candidate = value.activityId ?? value.activityid ?? value.id ?? value.aid;
        }

        const normalised = normaliseId(candidate);
        if (normalised !== null) {
            ids.push(normalised);
        }
    });

    return ids;
}

function normalizeMetadataAvailabilityValue(value) {
    if (typeof value === 'boolean') {
        return value;
    }

    if (typeof value === 'number') {
        return value > 0;
    }

    if (typeof value === 'string') {
        const normalized = value.trim().toLowerCase();

        if (normalized === '') {
            return null;
        }

        const truthy = ['1', 'y', 'yes', 'true', 't', 'available', 'open'];
        if (truthy.includes(normalized)) {
            return true;
        }

        const falsy = ['0', 'n', 'no', 'false', 'f', 'unavailable', 'closed', 'sold_out', 'soldout'];
        if (falsy.includes(normalized)) {
            return false;
        }
    }

    return null;
}

function convertIdsForDisplay(ids) {
    if (!Array.isArray(ids)) {
        return [];
    }

    return ids.map((value) => {
        if (typeof value === 'number') {
            return value;
        }

        const normalised = normaliseId(value);
        if (normalised !== null && /^\d+$/.test(normalised)) {
            return Number.parseInt(normalised, 10);
        }

        return normalised ?? value;
    });
}

function normalizeActivityForDisplay(activity) {
    if (!activity || typeof activity !== 'object') {
        return activity;
    }

    const copy = { ...activity };

    const id = convertIdsForDisplay([
        copy.activityId ?? copy.activityid ?? copy.id ?? copy.aid,
    ])[0];

    if (id !== undefined) {
        copy.activityId = id;
    }

    if (copy.details && typeof copy.details === 'object' && !Array.isArray(copy.details)) {
        copy.details = { ...copy.details };
    }

    if (copy.available !== undefined) {
        const availability = normalizeMetadataAvailabilityValue(copy.available);
        if (availability !== null) {
            copy.available = availability;
        }
    }

    return copy;
}

function normalizeTimesForDisplay(value) {
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
        return undefined;
    }

    const normalized = {};

    Object.entries(value).forEach(([key, timeValue]) => {
        const [id] = convertIdsForDisplay([key]);
        if (id === undefined) {
            return;
        }

        if (timeValue === null || timeValue === undefined) {
            return;
        }

        normalized[id] = typeof timeValue === 'string' ? timeValue : String(timeValue);
    });

    return Object.keys(normalized).length > 0 ? normalized : undefined;
}

function normalizeExtendedEntryForDisplay(entry, fallbackIds) {
    if (Array.isArray(entry)) {
        const ids = convertIdsForDisplay(collectIdsFromValues(entry));
        return {
            activityIds: ids.length > 0 ? Array.from(new Set(ids)) : convertIdsForDisplay(fallbackIds),
        };
    }

    if (!entry || typeof entry !== 'object') {
        return {
            activityIds: convertIdsForDisplay(fallbackIds),
        };
    }

    const normalized = { ...entry };

    if (Array.isArray(entry.activityIds)) {
        normalized.activityIds = Array.from(new Set(convertIdsForDisplay(entry.activityIds)));
    } else if (Array.isArray(entry.aids)) {
        normalized.activityIds = Array.from(new Set(convertIdsForDisplay(entry.aids)));
    } else if (Array.isArray(entry.ids)) {
        normalized.activityIds = Array.from(new Set(convertIdsForDisplay(entry.ids)));
    } else {
        normalized.activityIds = convertIdsForDisplay(fallbackIds);
    }

    if (Array.isArray(entry.activities)) {
        normalized.activities = entry.activities.map((activity) => normalizeActivityForDisplay(activity));
    } else if (entry.activities && typeof entry.activities === 'object') {
        normalized.activities = Object.values(entry.activities).map((activity) => normalizeActivityForDisplay(activity));
    }

    if (entry.times && typeof entry.times === 'object' && !Array.isArray(entry.times)) {
        const times = normalizeTimesForDisplay(entry.times);
        if (times) {
            normalized.times = times;
        }
    }

    return normalized;
}

function extractActivitiesFromMetadataEntry(entry) {
    const map = new Map();

    if (!entry || typeof entry !== 'object') {
        return map;
    }

    let activities = [];
    if (Array.isArray(entry.activities)) {
        activities = entry.activities;
    } else if (entry.activities && typeof entry.activities === 'object') {
        activities = Object.values(entry.activities);
    }

    activities.forEach((activity) => {
        if (!activity || typeof activity !== 'object') {
            return;
        }

        const id = normaliseId(activity.activityId ?? activity.activityid ?? activity.id ?? activity.aid);
        if (id === null) {
            return;
        }

        const normalized = { activityId: id };

        if (typeof activity.activityName === 'string' && activity.activityName.trim() !== '') {
            normalized.activityName = activity.activityName.trim();
        } else if (typeof activity.activityname === 'string' && activity.activityname.trim() !== '') {
            normalized.activityName = activity.activityname.trim();
        }

        const availabilityKeys = ['available', 'isAvailable', 'availableFlag', 'status'];
        for (const key of availabilityKeys) {
            if (!(key in activity)) {
                continue;
            }

            const availability = normalizeMetadataAvailabilityValue(activity[key]);
            if (availability !== null) {
                normalized.available = availability;
                break;
            }
        }

        if (activity.details && typeof activity.details === 'object' && !Array.isArray(activity.details)) {
            normalized.details = { ...activity.details };
        }

        map.set(id, normalized);
    });

    return map;
}

function extractTimesMapFromMetadataEntry(entry) {
    const map = new Map();

    if (!entry || typeof entry !== 'object') {
        return map;
    }

    const { times } = entry;
    if (!times || typeof times !== 'object' || Array.isArray(times)) {
        return map;
    }

    Object.entries(times).forEach(([key, value]) => {
        if (value === null || value === undefined) {
            return;
        }

        const normalisedKey = normaliseId(key);
        if (normalisedKey === null) {
            return;
        }

        map.set(normalisedKey, typeof value === 'string' ? value : String(value));
    });

    return map;
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

    if (Array.isArray(entry)) {
        return Array.from(new Set(collectIdsFromValues(entry)));
    }

    if (entry && typeof entry === 'object') {
        const ids = [
            ...collectIdsFromValues(entry.activityIds),
            ...collectIdsFromValues(entry.aids),
            ...collectIdsFromValues(entry.ids),
        ];

        if (ids.length === 0) {
            if (Array.isArray(entry.activities)) {
                ids.push(...collectIdsFromValues(entry.activities));
            } else if (entry.activities && typeof entry.activities === 'object') {
                ids.push(...collectIdsFromValues(Object.values(entry.activities)));
            }
        }

        return Array.from(new Set(ids));
    }

    return [];
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

function normaliseTimeslotDetails(details) {
    if (!details || typeof details !== 'object') {
        return {};
    }

    return Object.entries(details).reduce((accumulator, [key, value]) => {
        if (value === null || value === undefined) {
            return accumulator;
        }

        const stringKey = String(key);
        if (stringKey === '') {
            return accumulator;
        }

        const stringValue = String(value);
        if (stringValue === '') {
            return accumulator;
        }

        accumulator[stringKey] = stringValue;
        return accumulator;
    }, {});
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
        const details = normaliseTimeslotDetails(slot.details);

        accumulator.push({ id, label, available, details });
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

function buildDeparturesForMetadata(state, ids, metadataEntry) {
    if (!Array.isArray(ids) || ids.length === 0) {
        return [];
    }

    const knownTimeslots = new Map();
    (state.timeslots || []).forEach((slot) => {
        if (!slot || !slot.id) {
            return;
        }

        knownTimeslots.set(slot.id, {
            label: slot.label,
            details: slot.details && typeof slot.details === 'object' ? slot.details : undefined,
        });
    });

    const configuredLabels = getDepartureLabelMap(state);
    const metadataActivities = extractActivitiesFromMetadataEntry(metadataEntry);
    const metadataTimes = extractTimesMapFromMetadataEntry(metadataEntry);

    return ids.map((id) => {
        const normalizedId = normaliseId(id);
        const lookupId = normalizedId ?? id;
        const timeslot = knownTimeslots.get(lookupId);
        const metadataActivity = metadataActivities.get(lookupId);
        const timeFromMetadata = normalizedId ? metadataTimes.get(normalizedId) : undefined;

        const label = timeslot?.label
            || metadataActivity?.activityName
            || configuredLabels[lookupId]
            || `Departure ${lookupId}`;

        const metadataDetails = metadataActivity?.details && typeof metadataActivity.details === 'object'
            && !Array.isArray(metadataActivity.details)
            ? { ...metadataActivity.details }
            : undefined;
        let details = timeslot?.details && Object.keys(timeslot.details).length > 0
            ? { ...timeslot.details }
            : (metadataDetails ? { ...metadataDetails } : undefined);

        if (timeFromMetadata && (!details || details.times === undefined)) {
            details = { ...(details ?? {}), times: timeFromMetadata };
        }

        const departure = { id: lookupId, label };

        if (metadataActivity?.activityName) {
            departure.activityName = metadataActivity.activityName;
        }

        if (metadataActivity && typeof metadataActivity.available === 'boolean') {
            departure.available = metadataActivity.available;
        }

        if (details) {
            departure.details = details;
        }

        return departure;
    });
}

function formatMetadataForDisplay(state) {
    const metadata = state.availabilityMetadata;
    const selectedDate = state.selectedDate;

    if (!metadata || typeof metadata !== 'object') {
        return '';
    }

    const snapshot = { ...metadata };

    if (selectedDate) {
        snapshot.selectedDate = selectedDate;
    }

    const extended = metadata.extended;
    if (extended && typeof extended === 'object' && selectedDate) {
        const entry = extended[selectedDate];
        const availableIds = getAvailableIdsFromMetadata(metadata, selectedDate);
        const departures = buildDeparturesForMetadata(state, availableIds, entry);

        const normalizedEntry = normalizeExtendedEntryForDisplay(entry, availableIds);

        if (departures.length > 0) {
            normalizedEntry.departures = departures;
        }

        snapshot.extendedForSelectedDate = normalizedEntry;
    }

    try {
        return JSON.stringify(snapshot, null, 2);
    } catch (error) {
        console.warn('Unable to stringify availability metadata', error);
    }

    return '';
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

    if (metadataDetails) {
        const metadata = state.availabilityMetadata || {};
        const hasMetadata = metadata && typeof metadata === 'object' && Object.keys(metadata).length > 0;

        if (hasMetadata) {
            const formatted = formatMetadataForDisplay(state);
            metadataDetails.textContent = formatted;
            toggleHidden(metadataDetails, formatted === '');
        } else {
            metadataDetails.textContent = '';
            toggleHidden(metadataDetails, true);
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
    metadataDetails = qs('[data-availability-metadata]', availabilityPanel);

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
