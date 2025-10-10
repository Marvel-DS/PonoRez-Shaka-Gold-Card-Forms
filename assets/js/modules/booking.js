import { resolveApiUrl } from '../core/config.js';
import { postJson } from '../core/api-client.js';
import { getState, setState } from '../core/store.js';
import { qs, toggleHidden } from '../utility/dom.js';
import { formatCurrency } from '../utility/formating.js';
import { showError, showSuccess } from './alerts.js';
import { initCheckoutOverlay, openOverlay } from '../overlay/checkout-overlay.js';
import { computeGoldCardPrice, computeTotalGuestCount } from './pricing-utils.js';

let form;
let submitButton;
let buttonLabel;
let buttonSpinner;

function parseNumber(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const number = Number(value);
    return Number.isFinite(number) ? number : null;
}

function compactObject(candidate) {
    if (!candidate || typeof candidate !== 'object') {
        return {};
    }

    return Object.entries(candidate).reduce((accumulator, [key, value]) => {
        if (value === undefined || value === null || value === '') {
            return accumulator;
        }

        if (Array.isArray(value)) {
            const filtered = value
                .map((entry) => (entry && typeof entry === 'object' ? compactObject(entry) : entry))
                .filter((entry) => {
                    if (entry === undefined || entry === null) {
                        return false;
                    }
                    if (typeof entry === 'object') {
                        return Object.keys(entry).length > 0;
                    }
                    return entry !== '';
                });

            if (filtered.length > 0) {
                accumulator[key] = filtered;
            }
            return accumulator;
        }

        if (typeof value === 'object') {
            const nested = compactObject(value);
            if (Object.keys(nested).length > 0) {
                accumulator[key] = nested;
            }
            return accumulator;
        }

        accumulator[key] = value;
        return accumulator;
    }, {});
}

function getGuestCatalog(state) {
    const collection = state.bootstrap?.activity?.guestTypes?.collection;
    if (!Array.isArray(collection)) {
        return new Map();
    }

    return collection.reduce((map, entry) => {
        if (!entry || entry.id === undefined || entry.id === null) {
            return map;
        }
        const id = String(entry.id);
        if (id === '') {
            return map;
        }
        map.set(id, entry);
        return map;
    }, new Map());
}

function buildGuestSummary(state, guestCounts) {
    const catalog = getGuestCatalog(state);
    const breakdown = Object.entries(guestCounts).map(([id, count]) => {
        const info = catalog.get(id) || {};
        return compactObject({
            id,
            label: typeof info.label === 'string' ? info.label : undefined,
            description: typeof info.description === 'string' ? info.description : undefined,
            price: parseNumber(info.price),
            quantity: Number(count) || 0,
        });
    }).filter((entry) => Object.keys(entry).length > 0 && entry.quantity > 0);

    const totalGuests = breakdown.reduce((sum, entry) => sum + (entry.quantity || 0), 0);

    return compactObject({
        totalGuests,
        breakdown,
    });
}

function getUpgradeCatalog(state) {
    const upgrades = state.bootstrap?.activity?.upgrades;
    if (!Array.isArray(upgrades)) {
        return new Map();
    }

    return upgrades.reduce((map, entry) => {
        if (!entry || entry.id === undefined || entry.id === null) {
            return map;
        }
        const id = String(entry.id);
        if (id === '') {
            return map;
        }
        map.set(id, entry);
        return map;
    }, new Map());
}

function buildUpgradeSummary(state, selectedUpgrades) {
    const catalog = getUpgradeCatalog(state);
    const items = Object.entries(selectedUpgrades).map(([id, quantity]) => {
        const info = catalog.get(id) || {};
        const labelCandidates = [info.label, info.name, info.title].filter((value) => typeof value === 'string' && value.trim() !== '');
        const label = labelCandidates.length > 0 ? labelCandidates[0].trim() : undefined;
        const descriptionCandidates = [info.description, info.details, info.summary]
            .filter((value) => typeof value === 'string' && value.trim() !== '');
        const description = descriptionCandidates.length > 0 ? descriptionCandidates[0].trim() : undefined;
        return compactObject({
            id,
            label,
            description,
            price: parseNumber(info.price ?? info.amount ?? info.rate),
            quantity: Number(quantity) || 0,
        });
    }).filter((entry) => Object.keys(entry).length > 0 && entry.quantity > 0);

    if (items.length === 0) {
        return {};
    }

    return { items };
}

function buildTransportationSummary(state) {
    const routeId = state.transportationRouteId;
    if (!routeId) {
        return {};
    }

    const routes = state.bootstrap?.activity?.transportation?.routes;
    if (!Array.isArray(routes)) {
        return { routeId };
    }

    const match = routes.find((route) => String(route.id) === String(routeId));
    if (!match) {
        return { routeId };
    }

    const summary = compactObject({
        id: String(match.id ?? routeId),
        label: typeof match.label === 'string' ? match.label : undefined,
        description: typeof match.description === 'string' ? match.description : undefined,
        price: parseNumber(match.price),
        capacity: parseNumber(match.capacity),
    });

    return Object.keys(summary).length > 0 ? summary : { routeId };
}

function buildTimeslotSummary(state) {
    if (!state.selectedTimeslotId) {
        return {};
    }

    const timeslot = (state.timeslots || []).find((slot) => String(slot.id) === String(state.selectedTimeslotId));
    if (!timeslot) {
        return { id: state.selectedTimeslotId };
    }

    const summary = compactObject({
        id: timeslot.id,
        label: typeof timeslot.label === 'string' ? timeslot.label : undefined,
        startTime: timeslot.startTime || timeslot.start,
        endTime: timeslot.endTime || timeslot.end,
        capacity: parseNumber(timeslot.capacity ?? timeslot.available),
        status: timeslot.status || undefined,
    });

    return summary;
}

function buildPricingSummary(state) {
    const { pricing = {}, currency = {} } = state;
    const totals = compactObject({
        guests: parseNumber(pricing.guests),
        transportation: parseNumber(pricing.transportation),
        upgrades: parseNumber(pricing.upgrades),
        fees: parseNumber(pricing.fees),
        goldCard: parseNumber(pricing.goldCard),
        total: parseNumber(pricing.total),
    });

    const currencyInfo = compactObject({
        code: currency.code,
        symbol: currency.symbol,
        locale: currency.locale,
    });

    if (Object.keys(totals).length === 0 && Object.keys(currencyInfo).length === 0) {
        return {};
    }

    return compactObject({ totals, currency: currencyInfo });
}

function formatDateLabel(date, locale) {
    if (typeof date !== 'string' || date === '') {
        return null;
    }

    try {
        const formatter = new Intl.DateTimeFormat(locale || 'en-US', { dateStyle: 'long' });
        const safeDate = new Date(`${date}T12:00:00Z`);
        if (Number.isNaN(safeDate.getTime())) {
            return null;
        }
        return formatter.format(safeDate);
    } catch (error) {
        // Swallow formatting errors; fall back to the raw ISO date.
        void error;
    }

    return null;
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

    const metadata = {
        source: 'sgc-forms',
        generatedAt: new Date().toISOString(),
        supplier: compactObject({
            slug: state.bootstrap?.supplier?.slug,
            name: state.bootstrap?.supplier?.name,
        }),
        activity: compactObject({
            slug: state.bootstrap?.activity?.slug,
            displayName: state.bootstrap?.activity?.displayName,
            primaryActivityId: state.bootstrap?.activity?.primaryActivityIdString
                || (state.bootstrap?.activity?.primaryActivityId !== undefined
                    ? String(state.bootstrap.activity.primaryActivityId)
                    : undefined),
            activityIds: state.bootstrap?.activity?.activityIdStrings,
        }),
        selection: compactObject({
            date: state.selectedDate,
            dateLabel: formatDateLabel(state.selectedDate, state.currency?.locale),
            timeslot: buildTimeslotSummary(state),
            transportation: buildTransportationSummary(state),
        }),
        guests: buildGuestSummary(state, guestCounts),
        upgrades: buildUpgradeSummary(state, upgrades),
        pricing: buildPricingSummary(state),
        availability: state.availabilityMetadata,
        environment: compactObject({
            currentDate: state.bootstrap?.environment?.currentDate,
        }),
        formState: compactObject({
            guestCounts,
            upgrades,
            transportationRouteId: state.transportationRouteId,
            buyGoldCard: state.buyGoldCard ? true : undefined,
            shakaGoldCardNumber: state.shakaGoldCardNumber,
        }),
    };

    const rawGoldCard = state.shakaGoldCardNumber || '';
    const voucherId = typeof rawGoldCard === 'string' ? rawGoldCard.trim() : '';
    if (voucherId !== '') {
        metadata.voucherId = voucherId;
    }

    if (state.buyGoldCard) {
        metadata.buyGoldCard = true;
        metadata.goldCard = {
            guestCount: computeTotalGuestCount(state),
            price: computeGoldCardPrice(state),
        };
    }

    const compactMetadata = compactObject(metadata);

    const payload = {
        supplier: state.bootstrap?.supplier?.slug,
        activity: state.bootstrap?.activity?.slug,
        date: state.selectedDate,
        timeslotId: state.selectedTimeslotId,
        guestCounts,
        upgrades,
        metadata: compactMetadata,
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
