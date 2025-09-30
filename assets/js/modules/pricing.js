import { getState, setState, subscribe } from '../core/store.js';
import { qs } from '../utility/dom.js';
import { formatCurrency } from '../utility/formating.js';

let root;
let guestsEl;
let transportationEl;
let upgradesEl;
let feesEl;
let totalEl;
let noteEl;

function getCurrencyOptions(state) {
    return {
        currency: state.currency?.code || 'USD',
        locale: state.currency?.locale || 'en-US',
    };
}

function computeGuestTotal(state) {
    const guestCounts = state.guestCounts || {};
    const details = state.guestTypeDetails || [];

    if (details.length === 0) {
        return 0;
    }

    return details.reduce((sum, detail) => {
        const count = Number(guestCounts[detail.id] || 0);
        const price = Number(detail.price || 0);
        if (!Number.isFinite(count) || !Number.isFinite(price)) {
            return sum;
        }
        return sum + count * price;
    }, 0);
}

function computeTransportationTotal(state) {
    const config = state.bootstrap?.activity?.transportation;
    if (!config || !Array.isArray(config.routes)) {
        return 0;
    }

    const selected = config.routes.find((route) => String(route.id) === state.transportationRouteId);
    if (!selected) {
        return 0;
    }

    return Number(selected.price || 0);
}

function computeUpgradesTotal(state) {
    const quantities = state.upgradeQuantities || {};
    const upgrades = state.bootstrap?.activity?.upgrades || [];

    return upgrades.reduce((sum, upgrade) => {
        if (!upgrade || upgrade.enabled === false) {
            return sum;
        }
        const id = upgrade.id !== undefined ? String(upgrade.id) : '';
        if (id === '') {
            return sum;
        }
        const quantity = Number(quantities[id] || 0);
        const price = Number(upgrade.price || 0);
        if (!Number.isFinite(quantity) || !Number.isFinite(price)) {
            return sum;
        }
        return sum + quantity * price;
    }, 0);
}

function computeFees(state) {
    const metadata = state.availabilityMetadata || {};
    if (metadata.fees && Number.isFinite(Number(metadata.fees))) {
        return Number(metadata.fees);
    }
    return 0;
}

function updatePricingState(state, totals) {
    const existing = state.pricing || {};
    const keys = ['guests', 'transportation', 'upgrades', 'fees', 'total'];
    const changed = keys.some((key) => {
        const previous = Number(existing[key] ?? 0);
        const next = Number(totals[key] ?? 0);
        return Math.abs(previous - next) > 0.005;
    });

    if (changed) {
        setState({ pricing: totals });
    }
}

function renderPricing(state) {
    if (!root) {
        return;
    }

    const currencyOptions = getCurrencyOptions(state);

    const totals = {
        guests: computeGuestTotal(state),
        transportation: computeTransportationTotal(state),
        upgrades: computeUpgradesTotal(state),
        fees: computeFees(state),
    };
    totals.total = totals.guests + totals.transportation + totals.upgrades + totals.fees;

    updatePricingState(state, totals);

    if (guestsEl) {
        guestsEl.textContent = formatCurrency(totals.guests, currencyOptions);
    }

    if (transportationEl) {
        transportationEl.textContent = formatCurrency(totals.transportation, currencyOptions);
    }

    if (upgradesEl) {
        upgradesEl.textContent = formatCurrency(totals.upgrades, currencyOptions);
    }

    if (feesEl) {
        feesEl.textContent = formatCurrency(totals.fees, currencyOptions);
    }

    if (totalEl) {
        totalEl.textContent = formatCurrency(totals.total, currencyOptions);
    }

    if (noteEl) {
        const metadata = state.availabilityMetadata || {};
        const currencyCode = state.currency?.code || 'USD';
        if (metadata.fallback) {
            noteEl.textContent = 'Totals are estimates until live availability resumes.';
        } else {
            noteEl.textContent = `Rates are displayed in ${currencyCode} and include all required fees.`;
        }
    }
}

export function initPricing() {
    root = qs('[data-component="pricing"]');
    if (!root) {
        return;
    }

    guestsEl = qs('[data-pricing-guests]', root);
    transportationEl = qs('[data-pricing-transportation]', root);
    upgradesEl = qs('[data-pricing-upgrades]', root);
    feesEl = qs('[data-pricing-fees]', root);
    totalEl = qs('[data-pricing-total]', root);
    noteEl = qs('[data-pricing-note]', root);

    subscribe((state) => renderPricing(state));
    renderPricing(getState());
}

export default initPricing;
