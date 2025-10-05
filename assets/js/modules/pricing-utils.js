import { formatCurrency } from '../utility/formating.js';

function normaliseGuestTypeConfig(config) {
    if (!config || typeof config !== 'object') {
        return { labels: {}, descriptions: {}, min: {}, max: {} };
    }

    const labels = typeof config.labels === 'object' && config.labels !== null ? config.labels : {};
    const min = typeof config.min === 'object' && config.min !== null ? config.min : {};
    const max = typeof config.max === 'object' && config.max !== null ? config.max : {};

    return { labels, min, max };
}

function toNumeric(value, fallback = 0) {
    const numeric = Number(value);
    return Number.isFinite(numeric) ? numeric : fallback;
}

export function getCurrencyOptions(state) {
    return {
        currency: state.currency?.code || 'USD',
        locale: state.currency?.locale || 'en-US',
    };
}

export function formatCurrencyForState(state, amount) {
    return formatCurrency(amount, getCurrencyOptions(state));
}

export function computeGuestBreakdown(state) {
    const guestCounts = state.guestCounts || {};
    const details = Array.isArray(state.guestTypeDetails) ? state.guestTypeDetails : [];
    if (details.length === 0) {
        return [];
    }

    const config = normaliseGuestTypeConfig(state.bootstrap?.activity?.guestTypes);

    return details.reduce((accumulator, detail) => {
        if (!detail || detail.id === undefined) {
            return accumulator;
        }

        const id = String(detail.id);
        const count = toNumeric(guestCounts[id], 0);
        if (count <= 0) {
            return accumulator;
        }

        const label = config.labels[id]
            ?? (detail.label !== undefined && detail.label !== null ? String(detail.label) : null)
            ?? id;

        const unitPrice = toNumeric(detail.price, 0);
        const lineTotal = count * unitPrice;

        accumulator.push({
            id,
            label,
            count,
            unitPrice,
            total: lineTotal,
        });

        return accumulator;
    }, []);
}

export function computeGuestTotal(state) {
    return computeGuestBreakdown(state).reduce((sum, line) => sum + line.total, 0);
}

export function computeTransportationTotal(state) {
    const config = state.bootstrap?.activity?.transportation;
    if (!config || !Array.isArray(config.routes)) {
        return 0;
    }

    const selected = config.routes.find((route) => String(route.id) === state.transportationRouteId);
    if (!selected) {
        return 0;
    }

    return toNumeric(selected.price, 0);
}

export function computeUpgradesTotal(state) {
    const quantities = state.upgradeQuantities || {};
    const upgrades = Array.isArray(state.bootstrap?.activity?.upgrades)
        ? state.bootstrap.activity.upgrades
        : [];

    return upgrades.reduce((sum, upgrade) => {
        if (!upgrade || upgrade.enabled === false) {
            return sum;
        }

        const id = upgrade.id !== undefined ? String(upgrade.id) : '';
        if (id === '') {
            return sum;
        }

        const quantity = toNumeric(quantities[id], 0);
        const price = toNumeric(upgrade.price, 0);
        return sum + quantity * price;
    }, 0);
}

export function computeFees(state) {
    const metadata = state.availabilityMetadata || {};
    if (metadata.fees !== undefined) {
        return toNumeric(metadata.fees, 0);
    }
    if (metadata.feesTotal !== undefined) {
        return toNumeric(metadata.feesTotal, 0);
    }
    return 0;
}

export function computePricingTotals(state) {
    const totals = {
        guests: computeGuestTotal(state),
        transportation: computeTransportationTotal(state),
        upgrades: computeUpgradesTotal(state),
        fees: computeFees(state),
    };

    totals.total = totals.guests + totals.transportation + totals.upgrades + totals.fees;
    return totals;
}

export default {
    getCurrencyOptions,
    formatCurrencyForState,
    computeGuestBreakdown,
    computeGuestTotal,
    computeTransportationTotal,
    computeUpgradesTotal,
    computeFees,
    computePricingTotals,
};
