import { getState, setState } from '../core/store.js';
import { qs, toggleHidden } from '../utility/dom.js';
import { showError } from './alerts.js';
import { openOverlay } from '../overlay/checkout-overlay.js';

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
    if (typeof numbers !== 'string') {
        return;
    }

    const sanitized = numbers.replace(/[\r\n]+/g, ',');
    const entries = sanitized
        .split(/\s*[;,]\s*/)
        .map((value) => value.trim())
        .filter((value) => value !== '');

    entries.forEach((entry) => {
        params.append('goldcardnumber', entry);
    });
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

function buildPonorezCheckoutUrl(state) {
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

function submitCheckout(event) {
    event.preventDefault();

    const state = getState();
    const validationError = validateState(state);
    if (validationError) {
        showError(validationError);
        return;
    }

    setSubmitting(true);

    try {
        const checkoutUrl = buildPonorezCheckoutUrl(state);
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
