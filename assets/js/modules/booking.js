import { resolveApiUrl } from '../core/config.js';
import { postJson } from '../core/api-client.js';
import { getState, setState } from '../core/store.js';
import { qs, toggleHidden } from '../utility/dom.js';
import { formatCurrency } from '../utility/formating.js';
import { showError, showSuccess } from './alerts.js';
import { initCheckoutOverlay, openOverlay } from '../overlay/checkout-overlay.js';

let form;
let submitButton;
let buttonLabel;
let buttonSpinner;

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

function pruneZeroEntries(map) {
    if (!map || typeof map !== 'object') {
        return {};
    }

    return Object.entries(map).reduce((accumulator, [key, value]) => {
        if (Number(value) > 0) {
            accumulator[key] = Number(value);
        }
        return accumulator;
    }, {});
}

function validateState(state) {
    if (!state.selectedDate) {
        return 'Select a travel date to continue.';
    }

    if (!state.selectedTimeslotId) {
        return 'Choose a departure time before continuing.';
    }

    const guestCounts = state.guestCounts || {};
    const hasGuests = Object.values(guestCounts).some((count) => Number(count) > 0);
    if (!hasGuests) {
        return 'Add at least one guest to your reservation.';
    }

    return null;
}

function buildPayload(state) {
    const guestCounts = pruneZeroEntries(state.guestCounts);
    const upgrades = pruneZeroEntries(state.upgradeQuantities);

    const payload = {
        supplier: state.bootstrap?.supplier?.slug,
        activity: state.bootstrap?.activity?.slug,
        date: state.selectedDate,
        timeslotId: state.selectedTimeslotId,
        guestCounts,
        upgrades,
        metadata: {
            source: 'sgc-forms',
            generatedAt: new Date().toISOString(),
        },
    };

    if (state.transportationRouteId) {
        payload.transportationRouteId = state.transportationRouteId;
    }

    return payload;
}

function deriveOverlayPayload(checkout, state) {
    if (!checkout) {
        return {};
    }

    const currencyOptions = {
        currency: state.currency?.code || 'USD',
        locale: state.currency?.locale || 'en-US',
    };

    const totalPrice = checkout.totalPrice !== undefined && checkout.totalPrice !== null
        ? formatCurrency(Number(checkout.totalPrice), currencyOptions)
        : null;

    return {
        url: checkout.overlayUrl
            || checkout.checkoutUrl
            || checkout.url
            || checkout.redirectUrl
            || (checkout.reservation && checkout.reservation.checkoutUrl)
            || null,
        reservationId: checkout.reservationId
            || (checkout.reservation && (checkout.reservation.id || checkout.reservation.reservationId))
            || null,
        totalPrice,
        message: totalPrice ? `Reservation total ${totalPrice}` : null,
    };
}

function updatePricingFromCheckout(checkout) {
    if (!checkout || typeof checkout !== 'object') {
        return;
    }

    const totals = {};

    if (checkout.totalPrice !== undefined) {
        totals.total = Number(checkout.totalPrice);
    }

    if (checkout.calculation && typeof checkout.calculation === 'object') {
        if (checkout.calculation.out_price !== undefined) {
            totals.total = Number(checkout.calculation.out_price);
        }
        if (checkout.calculation.out_requiredSupplierPayment !== undefined) {
            totals.supplierPayment = Number(checkout.calculation.out_requiredSupplierPayment);
        }
    }

    if (Object.keys(totals).length > 0) {
        setState((current) => ({
            pricing: { ...current.pricing, ...totals },
        }));
    }
}

async function submitCheckout(event) {
    event.preventDefault();

    const state = getState();
    const validationError = validateState(state);
    if (validationError) {
        showError(validationError);
        return;
    }

    const endpoint = resolveApiUrl('initCheckout');
    if (!endpoint) {
        showError('Checkout endpoint is not configured.');
        return;
    }

    const payload = buildPayload(state);

    try {
        setSubmitting(true);
        const response = await postJson(endpoint, payload);
        const checkout = response.checkout || response.data?.checkout || null;

        if (!checkout) {
            showSuccess('Reservation created.');
            return;
        }

        updatePricingFromCheckout(checkout);

        const overlayPayload = deriveOverlayPayload(checkout, state);
        if (overlayPayload.url || overlayPayload.reservationId || overlayPayload.message) {
            openOverlay(overlayPayload);
        }
        showSuccess('Reservation initiated. Complete the checkout to finalize.');
    } catch (error) {
        console.error('Checkout failed', error);
        showError(error.message || 'Unable to initiate checkout.');
    } finally {
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

    toggleHidden(buttonSpinner, true);

    form.addEventListener('submit', submitCheckout);

    initCheckoutOverlay();
}

export default initBooking;
