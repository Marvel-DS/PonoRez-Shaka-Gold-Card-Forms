import { resolveApiUrl } from '../core/config.js';
import { requestJson } from '../core/api-client.js';
import { getState, setState, subscribe } from '../core/store.js';
import { qs, clearChildren, toggleHidden, createElement } from '../utility/dom.js';
import { formatDateLong } from '../utility/formating.js';
import { pluralize } from '../utility/strings.js';
import { showError } from './alerts.js';
import {
    computeGuestBreakdown,
    computePricingTotals,
    formatCurrencyForState,
} from './pricing-utils.js';

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

function collectAvailableIdsFromValues(values) {
    if (!Array.isArray(values)) {
        return [];
    }

    return collectIdsFromValues(values.filter((value) => {
        if (!value || typeof value !== 'object' || Array.isArray(value)) {
            return true;
        }

        if ('available' in value && value.available === false) {
            return false;
        }

        return true;
    }));
}

const METADATA_LABEL_KEYS = [
    'times',
    'time',
    'departure',
    'departureTime',
    'checkIn',
    'checkin',
    'checkintime',
    'check_in',
    'label',
    'name',
    'title',
    'displayName',
    'display',
];

function isFallbackDepartureLabel(label, id) {
    if (typeof label !== 'string') {
        return false;
    }

    const trimmed = label.trim();
    if (trimmed === '') {
        return false;
    }

    if (id === null || id === undefined) {
        return false;
    }

    const normalisedId = String(id).trim();
    if (normalisedId === '') {
        return false;
    }

    return trimmed.toLowerCase() === `departure ${normalisedId}`.toLowerCase();
}

function resolveLabelFromDetails(details, id) {
    if (!details || typeof details !== 'object') {
        return undefined;
    }

    for (const key of METADATA_LABEL_KEYS) {
        if (!(key in details)) {
            continue;
        }

        const value = details[key];
        const normalized = normaliseTimesValue(value);
        if (normalized !== undefined && !isFallbackDepartureLabel(normalized, id)) {
            return normalized;
        }
    }

    return undefined;
}

function resolveLabelFromCandidate(candidate, id) {
    if (!candidate || typeof candidate !== 'object') {
        return undefined;
    }

    const fromDetails = resolveLabelFromDetails(candidate.details, id);
    if (fromDetails !== undefined) {
        return fromDetails;
    }

    for (const key of METADATA_LABEL_KEYS) {
        if (!(key in candidate)) {
            continue;
        }

        const value = candidate[key];
        const normalized = normaliseTimesValue(value);
        if (normalized !== undefined && !isFallbackDepartureLabel(normalized, id)) {
            return normalized;
        }
    }

    return undefined;
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

function extractActivitiesFromMetadataEntry(entry) {
    const map = new Map();

    if (!entry || typeof entry !== 'object') {
        return map;
    }

    const sources = [];

    const addSource = (value) => {
        if (Array.isArray(value)) {
            value.forEach((item) => {
                sources.push(item);
            });
            return;
        }

        if (value && typeof value === 'object') {
            Object.values(value).forEach((item) => {
                sources.push(item);
            });
            return;
        }

        if (value !== undefined && value !== null) {
            sources.push(value);
        }
    };

    addSource(entry.activities);
    addSource(entry.activityDetails);
    addSource(entry.activitydetails);
    addSource(entry.activity_info);
    addSource(entry.activityInfo);
    addSource(entry.departures);
    addSource(entry.departureDetails);
    addSource(entry.departuredetails);
    addSource(entry.departure_info);
    addSource(entry.departureInfo);

    sources.forEach((activity) => {
        if (activity === null || activity === undefined) {
            return;
        }

        if (typeof activity !== 'object' || Array.isArray(activity)) {
            const id = normaliseId(activity);
            if (id !== null && !map.has(id)) {
                map.set(id, { activityId: id });
            }
            return;
        }

        const id = normaliseId(
            activity.activityId
            ?? activity.activityid
            ?? activity.id
            ?? activity.aid
            ?? activity.departureId
            ?? activity.departureid
            ?? activity.departure_id,
        );
        if (id === null) {
            return;
        }

        const existing = map.get(id);
        const normalized = existing ? { ...existing } : { activityId: id };

        const nameFields = ['activityName', 'activityname', 'name', 'label', 'title', 'displayName', 'display'];
        for (const field of nameFields) {
            const value = activity[field];
            if (typeof value !== 'string') {
                continue;
            }

            const trimmed = value.trim();
            if (trimmed === '') {
                continue;
            }

            if (!normalized.activityName || isFallbackDepartureLabel(normalized.activityName, id)) {
                normalized.activityName = trimmed;
                break;
            }
        }

        const availabilityKeys = ['available', 'isAvailable', 'availableFlag', 'status', 'availability', 'availabilityStatus'];
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

        let details = normalized.details && typeof normalized.details === 'object' && !Array.isArray(normalized.details)
            ? { ...normalized.details }
            : undefined;

        if (activity.details && typeof activity.details === 'object' && !Array.isArray(activity.details)) {
            const sanitizedDetails = normaliseTimeslotDetails(activity.details);
            if (sanitizedDetails && Object.keys(sanitizedDetails).length > 0) {
                details = { ...(details ?? {}), ...sanitizedDetails };
            }
        }

        const descriptionDetails = extractDetailsFromDescription(activity.description);
        if (descriptionDetails && Object.keys(descriptionDetails).length > 0) {
            details = { ...(details ?? {}) };
            Object.entries(descriptionDetails).forEach(([key, value]) => {
                if (key === 'times') {
                    if (details.times === undefined || isFallbackDepartureLabel(details.times, id)) {
                        details.times = value;
                    }
                    if (!normalized.activityName || isFallbackDepartureLabel(normalized.activityName, id)) {
                        normalized.activityName = value;
                    }
                    return;
                }

                if (details[key] === undefined) {
                    details[key] = value;
                }
            });
        }

        METADATA_LABEL_KEYS.forEach((key) => {
            if (!(key in activity)) {
                return;
            }

            const value = normaliseTimesValue(activity[key]);
            if (value === undefined) {
                return;
            }

            if (!details) {
                details = {};
            }

            if (['label', 'name', 'title', 'displayName', 'display'].includes(key)) {
                if (details.times === undefined && !isFallbackDepartureLabel(value, id)) {
                    details.times = value;
                }

                if (!normalized.activityName || isFallbackDepartureLabel(normalized.activityName, id)) {
                    normalized.activityName = value;
                }

                return;
            }

            if (details[key] === undefined) {
                details[key] = value;
            }
        });

        const labelFromCandidate = resolveLabelFromCandidate(activity, id);
        if (labelFromCandidate !== undefined) {
            if (!details) {
                details = {};
            }

            if (details.times === undefined) {
                details.times = labelFromCandidate;
            }

            if (!normalized.activityName || isFallbackDepartureLabel(normalized.activityName, id)) {
                normalized.activityName = labelFromCandidate;
            }
        }

        if (details && Object.keys(details).length > 0) {
            normalized.details = details;
        } else {
            delete normalized.details;
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

    const record = (id, value) => {
        const normalisedId = normaliseId(id);
        if (normalisedId === null) {
            return;
        }

        const label = normaliseTimesValue(value);
        if (label === undefined || isFallbackDepartureLabel(label, normalisedId)) {
            return;
        }

        if (!map.has(normalisedId)) {
            map.set(normalisedId, label);
        }
    };

    const { times } = entry;
    if (times && typeof times === 'object' && !Array.isArray(times)) {
        Object.entries(times).forEach(([key, value]) => {
            record(key, value);
        });
    }

    const fallbackIds = [];
    if (Array.isArray(entry.activityIds)) {
        fallbackIds.push(...collectIdsFromValues(entry.activityIds));
    }
    if (Array.isArray(entry.aids)) {
        fallbackIds.push(...collectIdsFromValues(entry.aids));
    }
    if (Array.isArray(entry.ids)) {
        fallbackIds.push(...collectIdsFromValues(entry.ids));
    }

    const fallbackByIndex = (index) => fallbackIds[index];

    const processCandidate = (candidate, index) => {
        if (candidate === null || candidate === undefined) {
            return;
        }

        if (typeof candidate !== 'object' || Array.isArray(candidate)) {
            const fallbackId = index !== undefined ? fallbackByIndex(index) : undefined;
            const label = normaliseTimesValue(candidate);
            if (fallbackId !== undefined && label !== undefined && !isFallbackDepartureLabel(label, fallbackId)) {
                record(fallbackId, label);
            }
            return;
        }

        const resolvedId = normaliseId(
            candidate.activityId
            ?? candidate.activityid
            ?? candidate.id
            ?? candidate.aid
            ?? candidate.departureId
            ?? candidate.departureid
            ?? candidate.departure_id,
        ) ?? (index !== undefined ? fallbackByIndex(index) : undefined);

        if (resolvedId === null || resolvedId === undefined) {
            return;
        }

        let sanitizedDetails;
        if (candidate.details && typeof candidate.details === 'object' && !Array.isArray(candidate.details)) {
            sanitizedDetails = normaliseTimeslotDetails(candidate.details);
        }

        const descriptionDetails = extractDetailsFromDescription(candidate.description);
        if (descriptionDetails && Object.keys(descriptionDetails).length > 0) {
            sanitizedDetails = { ...(sanitizedDetails ?? {}) };
            Object.entries(descriptionDetails).forEach(([key, value]) => {
                if (key === 'times') {
                    if (sanitizedDetails.times === undefined || isFallbackDepartureLabel(sanitizedDetails.times, resolvedId)) {
                        sanitizedDetails.times = value;
                    }
                    return;
                }

                if (sanitizedDetails[key] === undefined) {
                    sanitizedDetails[key] = value;
                }
            });
        }

        const decorated = sanitizedDetails ? { ...candidate, details: sanitizedDetails } : candidate;
        const label = resolveLabelFromDetails(sanitizedDetails, resolvedId) ?? resolveLabelFromCandidate(decorated, resolvedId);

        if (label !== undefined) {
            record(resolvedId, label);
        }
    };

    const candidateSources = [
        entry.departures,
        entry.departureDetails,
        entry.departuredetails,
        entry.departure_info,
        entry.departureInfo,
        entry.activities,
        entry.activityDetails,
        entry.activitydetails,
    ];

    candidateSources.forEach((source) => {
        if (!source) {
            return;
        }

        if (Array.isArray(source)) {
            source.forEach((candidate, index) => {
                processCandidate(candidate, index);
            });
            return;
        }

        if (typeof source === 'object') {
            Object.values(source).forEach((candidate) => {
                processCandidate(candidate);
            });
        }
    });

    return map;
}

function normaliseTimesValue(value) {
    return stringifyTimeslotDetailValue(value);
}

function getMetadataEntryForDate(metadata, date) {
    if (!metadata || typeof metadata !== 'object' || !date) {
        return undefined;
    }

    const { extended } = metadata;
    if (!extended || typeof extended !== 'object') {
        return undefined;
    }

    if (!(date in extended)) {
        return undefined;
    }

    return extended[date];
}

function enhanceTimeslotsWithMetadata(state, timeslots, metadata, fallbackMetadata) {
    if (!Array.isArray(timeslots) || timeslots.length === 0) {
        return timeslots;
    }

    const metadataEntry = getMetadataEntryForDate(metadata, state.selectedDate)
        ?? getMetadataEntryForDate(fallbackMetadata, state.selectedDate);

    if (!metadataEntry) {
        return timeslots;
    }

    const metadataActivities = extractActivitiesFromMetadataEntry(metadataEntry);
    const metadataTimes = extractTimesMapFromMetadataEntry(metadataEntry);
    const configuredLabels = getDepartureLabelMap(state);

    let changed = false;

    const enhanced = timeslots.map((slot) => {
        if (!slot || slot.id === undefined || slot.id === null) {
            return slot;
        }

        const normalizedId = normaliseId(slot.id);
        const lookupId = normalizedId ?? slot.id;

        const metadataActivity = metadataActivities.get(lookupId);
        const timeFromMetadata = normalizedId !== null
            ? (metadataTimes.get(normalizedId) ?? metadataTimes.get(lookupId))
            : metadataTimes.get(lookupId);
        const labelFromMetadataActivity = resolveLabelFromCandidate(metadataActivity, lookupId);
        const labelFromMetadataMap = timeFromMetadata ? normaliseTimesValue(timeFromMetadata) : undefined;

        const metadataActivityName = typeof metadataActivity?.activityName === 'string'
            && !isFallbackDepartureLabel(metadataActivity.activityName, lookupId)
            ? metadataActivity.activityName
            : undefined;

        const replacementLabel = labelFromMetadataActivity
            || (labelFromMetadataMap && !isFallbackDepartureLabel(labelFromMetadataMap, lookupId)
                ? labelFromMetadataMap
                : undefined)
            || metadataActivityName
            || configuredLabels[lookupId];

        if (!replacementLabel || replacementLabel === slot.label) {
            return slot;
        }

        changed = true;
        return { ...slot, label: replacementLabel };
    });

    return changed ? enhanced : timeslots;
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
        return null;
    }

    const extended = metadata.extended;
    if (!extended || typeof extended !== 'object') {
        return null;
    }

    const entry = extended[date];
    if (entry === undefined) {
        return null;
    }

    if (Array.isArray(entry)) {
        return Array.from(new Set(collectAvailableIdsFromValues(entry)));
    }

    if (entry && typeof entry === 'object') {
        if (Array.isArray(entry.availableActivityIds)) {
            return Array.from(new Set(collectIdsFromValues(entry.availableActivityIds)));
        }

        const activityValues = [];
        if (Array.isArray(entry.activities)) {
            activityValues.push(...entry.activities);
        } else if (entry.activities && typeof entry.activities === 'object') {
            activityValues.push(...Object.values(entry.activities));
        }

        if (activityValues.length > 0) {
            return Array.from(new Set(collectAvailableIdsFromValues(activityValues)));
        }

        const ids = [
            ...collectIdsFromValues(entry.activityIds),
            ...collectIdsFromValues(entry.aids),
            ...collectIdsFromValues(entry.ids),
        ];

        return Array.from(new Set(ids));
    }

    return [];
}

function deriveTimeslotsFromSources(state, payloadTimeslots, metadata) {
    if (Array.isArray(payloadTimeslots) && payloadTimeslots.length > 0) {
        const availableIds = getAvailableIdsFromMetadata(metadata, state.selectedDate);
        if (!Array.isArray(availableIds)) {
            return enhanceTimeslotsWithMetadata(state, payloadTimeslots, metadata, state.availabilityMetadata);
        }

        if (availableIds.length === 0) {
            return enhanceTimeslotsWithMetadata(state, [], metadata, state.availabilityMetadata);
        }

        const availableSet = new Set(availableIds);
        const filtered = payloadTimeslots.filter((slot) => availableSet.has(slot.id));
        if (filtered.length > 0) {
            return enhanceTimeslotsWithMetadata(state, filtered, metadata, state.availabilityMetadata);
        }

        return enhanceTimeslotsWithMetadata(state, [], metadata, state.availabilityMetadata);
    }

    const availableIds = getAvailableIdsFromMetadata(metadata, state.selectedDate);
    if (!Array.isArray(availableIds) || availableIds.length === 0) {
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

function stringifyTimeslotDetailValue(value) {
    if (value === null || value === undefined) {
        return undefined;
    }

    if (typeof value === 'string') {
        const trimmed = value.trim();
        return trimmed === '' ? undefined : trimmed;
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        const stringValue = String(value).trim();
        return stringValue === '' ? undefined : stringValue;
    }

    if (Array.isArray(value)) {
        const parts = value
            .map((item) => stringifyTimeslotDetailValue(item))
            .filter((item) => item !== undefined && item !== '');

        if (parts.length === 0) {
            return undefined;
        }

        return parts.join(', ');
    }

    if (typeof value !== 'object') {
        const stringValue = String(value).trim();
        return stringValue === '' ? undefined : stringValue;
    }

    const preferredKeys = [
        'provided',
        'display',
        'label',
        'text',
        'value',
        'times',
        'time',
        'departure',
        'departureTime',
        'checkIn',
        'checkin',
        'checkintime',
        'check_in',
    ];

    for (const key of preferredKeys) {
        if (!(key in value)) {
            continue;
        }

        const candidate = stringifyTimeslotDetailValue(value[key]);
        if (candidate !== undefined) {
            return candidate;
        }
    }

    for (const candidate of Object.values(value)) {
        const stringValue = stringifyTimeslotDetailValue(candidate);
        if (stringValue !== undefined) {
            return stringValue;
        }
    }

    return undefined;
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

        const normalisedValue = stringifyTimeslotDetailValue(value);
        if (normalisedValue === undefined) {
            return accumulator;
        }

        accumulator[stringKey] = normalisedValue;
        return accumulator;
    }, {});
}

function extractDetailsFromDescription(description) {
    if (!description || typeof description !== 'object') {
        return undefined;
    }

    const sources = Array.isArray(description) ? description : [description];
    const aggregated = {};

    sources.forEach((source) => {
        if (!source || typeof source !== 'object') {
            return;
        }

        const directDetails = normaliseTimeslotDetails(source);
        Object.entries(directDetails).forEach(([key, value]) => {
            if (aggregated[key] === undefined) {
                aggregated[key] = value;
            }
        });

        if (source.details && typeof source.details === 'object' && !Array.isArray(source.details)) {
            const nestedDetails = normaliseTimeslotDetails(source.details);
            Object.entries(nestedDetails).forEach(([key, value]) => {
                if (aggregated[key] === undefined) {
                    aggregated[key] = value;
                }
            });
        }
    });

    return Object.keys(aggregated).length > 0 ? aggregated : undefined;
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
        return `Only ${timeslot.available} ${pluralize('seat', timeslot.available)} left!`;
    }

    return 'Seats available';
}

function getAvailabilityMessageClasses(timeslot) {
    if (timeslot.available === null || timeslot.available === undefined) {
        return 'text-xs font-medium text-slate-500';
    }

    if (timeslot.available <= 0) {
        return 'text-xs font-semibold text-rose-600';
    }

    if (timeslot.available <= 4) {
        return 'text-xs font-semibold text-amber-600';
    }

    return 'text-xs font-medium text-emerald-600';
}

function renderTimeslots(state) {
    if (!availabilityPanel || !timeslotList) {
        return;
    }

    const { timeslots, selectedTimeslotId } = state;
    const isLoading = state.loading?.availability;
    const hasTimeslots = Array.isArray(timeslots) && timeslots.length > 0;
    const guestBreakdown = computeGuestBreakdown(state);
    const pricingTotals = computePricingTotals(state);
    const hasGuestPricing = guestBreakdown.length > 0;
    const hasGuestDetails = Array.isArray(state.guestTypeDetails) && state.guestTypeDetails.length > 0;
    const feesAmount = pricingTotals.fees;

    const savingsAmount = (() => {
        const metadata = state.availabilityMetadata || {};
        const keys = [
            'savings',
            'savingsAmount',
            'savingsTotal',
            'discountSavings',
            'goldCardSavings',
        ];

        for (const key of keys) {
            if (metadata[key] === undefined) {
                continue;
            }

            const numeric = Number(metadata[key]);
            if (Number.isFinite(numeric) && numeric > 0) {
                return numeric;
            }
        }

        return 0;
    })();

    toggleHidden(loadingState, !isLoading);
    toggleHidden(emptyState, isLoading || hasTimeslots);
    toggleHidden(timeslotList, isLoading || !hasTimeslots);

    if (!hasTimeslots) {
        clearChildren(timeslotList);
    }

    if (hasTimeslots) {
        clearChildren(timeslotList);

        timeslots.forEach((timeslot) => {
            const item = createElement('li', { className: 'w-full' });

            const label = createElement('label', {
                className: 'group relative block cursor-pointer rounded-xl border border-slate-200 bg-white p-4 shadow-card transition duration-150 hover:border-[#1c55db] focus-visible:outline-none focus-within:ring-2 focus-within:ring-[#1c55db] focus-within:ring-offset-2 focus-within:ring-offset-white',
                attributes: { 'data-timeslot-id': timeslot.id },
            });

            const headerRow = createElement('div', { className: 'flex items-start gap-3' });

            const radio = createElement('input', {
                attributes: {
                    type: 'radio',
                    name: 'timeslotId',
                    value: timeslot.id,
                    class: 'mt-0.5 h-4 w-4 accent-[#1c55db] focus-visible:outline-none',
                    'aria-label': `Select ${timeslot.label}`,
                },
            });

            if (timeslot.id === selectedTimeslotId) {
                radio.checked = true;
            }

            if (timeslot.available !== null && timeslot.available <= 0) {
                radio.disabled = true;
            }

            const titleWrapper = createElement('div', { className: 'flex flex-col gap-1' });
            titleWrapper.appendChild(createElement('span', {
                className: 'text-sm font-medium text-[#090b0a]',
                text: timeslot.label,
            }));

            const availabilityText = describeAvailability(timeslot);
            if (availabilityText) {
                titleWrapper.appendChild(createElement('span', {
                    className: getAvailabilityMessageClasses(timeslot),
                    text: availabilityText,
                }));
            }

            headerRow.appendChild(radio);
            headerRow.appendChild(titleWrapper);
            label.appendChild(headerRow);

            const priceInfo = createElement('div', {
                className: 'mt-4 inline-flex w-full flex-col gap-4 border-t border-[#090b0a]/20 pt-4 text-xs text-[#090b0a] sm:flex-row sm:items-start sm:justify-between sm:gap-8',
            });

            if (!hasGuestDetails) {
                priceInfo.appendChild(createElement('p', {
                    className: 'text-xs font-medium leading-5 tracking-tight text-[#090b0a]/60',
                    text: 'Guest pricing will appear once rates finish loading.',
                }));
            } else if (!hasGuestPricing) {
                priceInfo.appendChild(createElement('p', {
                    className: 'text-xs font-medium leading-5 tracking-tight text-[#090b0a]/60',
                    text: 'Add guests to see pricing for this departure.',
                }));

            } else {
                const breakdownWrapper = createElement('div', {
                    className: 'flex-1 space-y-1 text-xs font-medium leading-5 tracking-tight text-[#090b0a]',
                });

                guestBreakdown.forEach((line) => {
                    breakdownWrapper.appendChild(createElement('p', {
                        text: `${line.count} × ${line.label}: ${formatCurrencyForState(state, line.total)}`,
                    }));
                });

                if (pricingTotals.upgrades > 0) {
                    breakdownWrapper.appendChild(createElement('p', {
                        text: `Upgrades: ${formatCurrencyForState(state, pricingTotals.upgrades)}`,
                    }));
                }

                if (feesAmount > 0) {
                    breakdownWrapper.appendChild(createElement('p', {
                        text: `Taxes & fees: ${formatCurrencyForState(state, feesAmount)}`,
                    }));
                }

                priceInfo.appendChild(breakdownWrapper);

                const totalsWrapper = createElement('div', {
                    className: 'flex flex-col items-end justify-start gap-1 text-right text-[#090b0a]',
                });

                totalsWrapper.appendChild(createElement('span', {
                    className: 'text-base font-semibold leading-normal tracking-tight',
                    text: `Total: ${formatCurrencyForState(state, pricingTotals.total)}`,
                }));

                if (feesAmount > 0) {
                    totalsWrapper.appendChild(createElement('span', {
                        className: 'text-xs font-medium leading-snug tracking-tight text-[#090b0a]/50',
                        text: 'Including taxes and fees',
                    }));
                }

                if (savingsAmount > 0) {
                    totalsWrapper.appendChild(createElement('span', {
                        className: 'text-xs font-semibold leading-[21px] tracking-tight text-[#1c55db] lg:text-sm',
                        text: `You save ${formatCurrencyForState(state, savingsAmount)} with your Shaka Gold Card`,
                    }));

                }

                priceInfo.appendChild(totalsWrapper);
            }

            label.appendChild(priceInfo);

            if (radio.disabled) {
                label.classList.add('cursor-not-allowed', 'opacity-60', 'hover:border-slate-200');
                label.classList.remove('cursor-pointer', 'hover:border-[#1c55db]');
            }

            if (radio.checked) {
                label.classList.add('border-[#1c55db]', 'ring-2', 'ring-[#1c55db]', 'ring-offset-2', 'ring-offset-white');
            }

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
