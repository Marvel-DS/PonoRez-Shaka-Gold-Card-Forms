import { getState, setState } from '../core/store.js';
import { resolveApiUrl } from '../core/config.js';
import { postJson } from '../core/api-client.js';
import { qs, toggleHidden } from '../utility/dom.js';
import { showError } from './alerts.js';
import { openOverlay } from '../overlay/checkout-overlay.js';
import { normaliseGoldCardEntries, buildGoldCardSignature } from './gold-card-utils.js';

let form;
let submitButton;
let buttonLabel;
let buttonSpinner;
let cancellationCheckbox;

function updateCancellationState() {
    if (!cancellationCheckbox) {
        return;
    }

    setState({
        acknowledgedCancellationPolicy: Boolean(cancellationCheckbox.checked),
    });
}

function setSubmitting(isSubmitting) {
    setState((current) => ({
        loading: { ...current.loading, checkout: isSubmitting },
    }));

    if (!submitButton) {
        return;
    }

    submitButton.disabled = isSubmitting;

    if (buttonLabel) {
        toggleHidden(buttonLabel, isSubmitting);
    }

    if (buttonSpinner) {
        toggleHidden(buttonSpinner, !isSubmitting);
    }
}

function prunePositiveIntegers(map) {
    if (!map || typeof map !== 'object') {
        return {};
    }

    return Object.entries(map).reduce((accumulator, [key, value]) => {
        const parsed = Number.parseInt(value, 10);
        if (Number.isFinite(parsed) && parsed > 0) {
            accumulator[key] = parsed;
        }
        return accumulator;
    }, {});
}

function getGoldCardNumbers(state) {
    return normaliseGoldCardEntries(state.shakaGoldCardNumber || '');
}

function getGoldCardSignature(numbers) {
    return buildGoldCardSignature(numbers);
}

function formatPonorezDate(isoDate) {
    if (typeof isoDate !== 'string') {
        return null;
    }

    const trimmed = isoDate.trim();
    if (trimmed === '') {
        return null;
    }

    const parts = trimmed.split('-');
    if (parts.length !== 3) {
        return null;
    }

    const [year, month, day] = parts;
    if (!year || !month || !day) {
        return null;
    }

    const normalizedMonth = month.padStart(2, '0');
    const normalizedDay = day.padStart(2, '0');

    if (normalizedMonth.length !== 2 || normalizedDay.length !== 2 || year.length !== 4) {
        return null;
    }

    return `${normalizedMonth}/${normalizedDay}/${year}`;
}

function ensureTrailingSlash(value) {
    if (!value.endsWith('/')) {
        return `${value}/`;
    }
    return value;
}

function resolvePonorezBaseUrl(state) {
    const candidates = [
        state.bootstrap?.activity?.ponorezBaseUrl,
        state.bootstrap?.supplier?.ponorezBaseUrl,
        state.bootstrap?.environment?.ponorezBaseUrl,
    ];

    for (const candidate of candidates) {
        if (typeof candidate !== 'string') {
            continue;
        }
        const trimmed = candidate.trim();
        if (trimmed !== '') {
            return ensureTrailingSlash(trimmed);
        }
    }

    return 'https://ponorez.online/reservation/';
}

function getSelectedActivityId(state) {
    if (state.selectedTimeslotId) {
        return String(state.selectedTimeslotId);
    }

    const primary = state.bootstrap?.activity?.primaryActivityIdString
        || (state.bootstrap?.activity?.primaryActivityId !== undefined
            ? String(state.bootstrap.activity.primaryActivityId)
            : null);
    if (primary) {
        return primary;
    }

    const activityIds = state.bootstrap?.activity?.activityIdStrings;
    if (Array.isArray(activityIds) && activityIds.length > 0) {
        return String(activityIds[0]);
    }

    return null;
}

function appendGoldCardNumbers(params, numbers) {
    const entries = normaliseGoldCardEntries(typeof numbers === 'string' ? numbers : '');

    entries.forEach((entry) => {
        params.append('goldcardnumber', entry);
    });
}

async function fetchGoldCardDiscount(state, numbers, signature) {
    const url = resolveApiUrl('goldCardDiscount');
    if (!url) {
        throw new Error('Gold card discount lookup is unavailable.');
    }

    const payload = {
        supplier: state.bootstrap?.supplier?.slug || null,
        activity: state.bootstrap?.activity?.slug || null,
        numbers,
        date: state.selectedDate || null,
        timeslotId: state.selectedTimeslotId ? String(state.selectedTimeslotId) : null,
        guestCounts: prunePositiveIntegers(state.guestCounts),
        transportationRouteId: state.transportationRouteId ? String(state.transportationRouteId) : null,
        upgrades: prunePositiveIntegers(state.upgradeQuantities),
        buyGoldCard: Boolean(state.buyGoldCard),
        policyAccepted: Boolean(state.shouldApplyCancellationPolicy && state.acknowledgedCancellationPolicy),
        payLater: true,
        referer: typeof window !== 'undefined' && window.location ? window.location.href : null,
    };

    if (payload.guestCounts && Object.keys(payload.guestCounts).length === 0) {
        delete payload.guestCounts;
    }

    if (payload.upgrades && Object.keys(payload.upgrades).length === 0) {
        delete payload.upgrades;
    }

    if (payload.transportationRouteId === null) {
        delete payload.transportationRouteId;
    }

    if (payload.timeslotId === null) {
        delete payload.timeslotId;
    }

    if (!payload.date) {
        delete payload.date;
    }

    try {
        const response = await postJson(url, payload);
        const discount = response.discount || {};
        const codeRaw = typeof discount.code === 'string' ? discount.code.trim() : '';
        const normalizedCode = codeRaw !== '' ? codeRaw : null;

        setState({
            goldCardDiscount: {
                code: normalizedCode,
                numbers,
                signature,
                fetchedAt: Date.now(),
                source: typeof discount.source === 'string' ? discount.source : null,
            },
        });

        return normalizedCode;
    } catch (error) {
        setState({
            goldCardDiscount: {
                code: null,
                numbers,
                signature,
                fetchedAt: Date.now(),
                error: error.message || 'Unable to retrieve discount code.',
            },
        });
        throw error;
    }
}

async function ensureGoldCardDiscount(state) {
    const numbers = getGoldCardNumbers(state);

    if (numbers.length === 0) {
        if (state.goldCardDiscount !== null) {
            setState({ goldCardDiscount: null });
        }
        return null;
    }

    const signature = getGoldCardSignature(numbers);
    const cached = state.goldCardDiscount;

    if (cached && cached.signature === signature) {
        return cached.code || null;
    }

    return fetchGoldCardDiscount(state, numbers, signature);
}

function isTransportationMandatory(state) {
    const transportation = state.bootstrap?.activity?.transportation;
    if (!transportation || typeof transportation !== 'object') {
        return false;
    }

    const routes = Array.isArray(transportation.routes) ? transportation.routes : [];
    if (routes.length === 0) {
        return false;
    }

    return Boolean(transportation.mandatory);
}

function buildPonorezCheckoutUrl(state, options = {}) {
    const activityId = getSelectedActivityId(state);
    if (!activityId) {
        throw new Error('Select a departure to continue.');
    }

    const formattedDate = formatPonorezDate(state.selectedDate);
    if (!formattedDate) {
        throw new Error('Select a valid travel date before continuing.');
    }

    const baseUrl = resolvePonorezBaseUrl(state);
    const url = new URL('externalservlet', baseUrl);
    const params = url.searchParams;
    const discountCandidate = typeof options.discountCode === 'string'
        ? options.discountCode
        : state.goldCardDiscount?.code ?? null;
    const discountCode = typeof discountCandidate === 'string' ? discountCandidate.trim() : '';

    params.set('action', 'EXTERNALPURCHASEPAGE');
    params.set('mode', 'reservation');
    params.set('activityid', activityId);
    params.set('date', formattedDate);
    params.set('externalpurchasemode', '2');

    if (typeof window !== 'undefined' && window.location && window.location.href) {
        params.set('referer', window.location.href);
    }

    const guestCounts = prunePositiveIntegers(state.guestCounts);
    Object.keys(guestCounts).sort().forEach((id) => {
        params.set(`guests_t${id}`, String(guestCounts[id]));
    });

    if (!state.bootstrap?.activity?.disableUpgrades) {
        const upgrades = prunePositiveIntegers(state.upgradeQuantities);
        params.set('upgradesfixed', '1');
        Object.keys(upgrades).sort().forEach((id) => {
            params.set(`upgrades_u${id}`, String(upgrades[id]));
        });
    }

    if (state.transportationRouteId) {
        const routeId = String(state.transportationRouteId);
        params.set('transportationrouteid', routeId);
        params.set('transportationpreselected', routeId);
    }

    appendGoldCardNumbers(params, state.shakaGoldCardNumber);

    if (discountCode !== '') {
        params.set('discountcode', discountCode);
    }

    if (state.buyGoldCard) {
        params.set('buygoldcards', '1');
    }

    if (state.shouldApplyCancellationPolicy && state.acknowledgedCancellationPolicy) {
        params.set('policy', '1');
    }
    params.set('paylater', 'true');

    return url.toString();
}

function validateState(state) {
    if (!state.selectedDate) {
        return 'Select a travel date to continue.';
    }

    if (!state.selectedTimeslotId) {
        return 'Choose a departure time before continuing.';
    }

    const guestCounts = state.guestCounts || {};
    const hasGuests = Object.values(guestCounts).some((count) => Number.parseInt(count, 10) > 0);
    if (!hasGuests) {
        return 'Add at least one guest to your reservation.';
    }

    if (isTransportationMandatory(state) && !state.transportationRouteId) {
        return 'Select a transportation option before continuing.';
    }

    if (state.requiresCancellationAcknowledgement && !state.acknowledgedCancellationPolicy) {
        return 'Please review and acknowledge the cancellation policy before continuing.';
    }

    return null;
}

async function submitCheckout(event) {
    event.preventDefault();

    const state = getState();
    const validationError = validateState(state);
    if (validationError) {
        showError(validationError);
        return;
    }

    setSubmitting(true);

    try {
        let discountCode = null;

        try {
            discountCode = await ensureGoldCardDiscount(state);
        } catch (discountError) {
            showError(discountError.message || 'Unable to validate Shaka Gold Card discount.');
            setSubmitting(false);
            return;
        }

        const refreshedState = getState();
        const checkoutUrl = buildPonorezCheckoutUrl(refreshedState, { discountCode });
        const opened = openOverlay({ url: checkoutUrl });
        if (opened) {
            setSubmitting(false);
        } else {
            window.location.href = checkoutUrl;
        }
    } catch (error) {
        showError(error.message || 'Unable to start checkout.');
        setSubmitting(false);
    }
}

export function initBooking() {
    form = document.getElementById('sgc-booking-form');
    if (!form) {
        return;
    }

    submitButton = qs('[data-action="initiate-booking"]', form);
    buttonLabel = qs('[data-button-label]', submitButton);
    buttonSpinner = qs('[data-button-spinner]', submitButton);
    cancellationCheckbox = qs('[data-cancellation-acknowledgement]', form);

    toggleHidden(buttonSpinner, true);

    if (cancellationCheckbox) {
        setState({
            requiresCancellationAcknowledgement: true,
            acknowledgedCancellationPolicy: Boolean(cancellationCheckbox.checked),
            shouldApplyCancellationPolicy: true,
        });
        cancellationCheckbox.addEventListener('change', updateCancellationState);
    } else {
        setState({
            requiresCancellationAcknowledgement: false,
            acknowledgedCancellationPolicy: false,
            shouldApplyCancellationPolicy: false,
        });
    }

    form.addEventListener('submit', submitCheckout);
}

export default initBooking;
