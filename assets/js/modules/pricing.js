import { getState, setState, subscribe } from '../core/store.js';
import { qs } from '../utility/dom.js';
import { formatCurrency } from '../utility/formating.js';
import {
    getCurrencyOptions,
    computePricingTotals,
} from './pricing-utils.js';

let root;
let guestsEl;
let transportationEl;
let upgradesEl;
let feesEl;
let goldCardEl;
let totalEl;
let noteEl;

function updatePricingState(state, totals) {
    const existing = state.pricing || {};
    const keys = ['guests', 'transportation', 'upgrades', 'fees', 'goldCard', 'total'];
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

    const totals = computePricingTotals(state);

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

    if (goldCardEl) {
        goldCardEl.textContent = formatCurrency(totals.goldCard, currencyOptions);
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
    goldCardEl = qs('[data-pricing-goldcard]', root);

    subscribe((state) => renderPricing(state));
    renderPricing(getState());
}

export default initPricing;
