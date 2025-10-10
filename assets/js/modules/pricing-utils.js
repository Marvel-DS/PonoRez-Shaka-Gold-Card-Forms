import { formatCurrency } from '../utility/formating.js';

const GOLD_CARD_BASE_PRICE = 30;
const GOLD_CARD_BASE_GUEST_LIMIT = 4;
const GOLD_CARD_EXTRA_GUEST_PRICE = 7.5;

function toNumeric(value, fallback = 0) {
    const numeric = Number(value);
    return Number.isFinite(numeric) ? numeric : fallback;
}

function computeTotalGuestCount(state) {
    const guestCounts = state.guestCounts || {};

    return Object.values(guestCounts).reduce((sum, value) => sum + toNumeric(value, 0), 0);
}

export function calculateGoldCardPrice(guestCount) {
    const normalizedCount = Number.isFinite(guestCount) ? Math.max(0, Math.floor(guestCount)) : 0;

    if (normalizedCount <= 0) {
        return GOLD_CARD_BASE_PRICE;
    }

    if (normalizedCount <= GOLD_CARD_BASE_GUEST_LIMIT) {
        return GOLD_CARD_BASE_PRICE;
    }

    const additionalGuests = normalizedCount - GOLD_CARD_BASE_GUEST_LIMIT;
    return GOLD_CARD_BASE_PRICE + additionalGuests * GOLD_CARD_EXTRA_GUEST_PRICE;
}

export function computeGoldCardPrice(state) {
    return calculateGoldCardPrice(computeTotalGuestCount(state));
}

export function computeGoldCardTotal(state) {
    if (!state || !state.buyGoldCard) {
        return 0;
    }

    return computeGoldCardPrice(state);
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

    return details.reduce((accumulator, detail) => {
        if (!detail || detail.id === undefined) {
            return accumulator;
        }

        const id = String(detail.id);
        const count = toNumeric(guestCounts[id], 0);
        if (count <= 0) {
            return accumulator;
        }

        const label = detail.label !== undefined && detail.label !== null && String(detail.label).trim() !== ''
            ? String(detail.label)
            : id;

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

export function computeDiscountSavings(state, total) {
    const rawDiscount = state.bootstrap?.activity?.discount ?? state.bootstrap?.discount ?? 0;
    let discountRate = Number(rawDiscount);

    if (!Number.isFinite(discountRate) || discountRate <= 0) {
        return 0;
    }

    if (discountRate > 1) {
        discountRate = discountRate / 100;
    }

    const numericTotal = Number(total);
    const safeTotal = Number.isFinite(numericTotal) ? numericTotal : 0;

    return Math.max(0, safeTotal * discountRate);
}

export function computePricingTotals(state) {
    const totals = {
        guests: computeGuestTotal(state),
        transportation: computeTransportationTotal(state),
        upgrades: computeUpgradesTotal(state),
        fees: computeFees(state),
        goldCard: computeGoldCardTotal(state),
    };

    totals.total = totals.guests + totals.transportation + totals.upgrades + totals.fees + totals.goldCard;
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
    computeDiscountSavings,
    computePricingTotals,
    computeGoldCardPrice,
    computeGoldCardTotal,
    calculateGoldCardPrice,
};

export {
    GOLD_CARD_BASE_PRICE,
    GOLD_CARD_BASE_GUEST_LIMIT,
    GOLD_CARD_EXTRA_GUEST_PRICE,
    computeTotalGuestCount,
};
