import { getBootstrap } from './config.js';

const bootstrap = getBootstrap();

const today = new Date();
const todayIso = today.toISOString().slice(0, 10);
const bootstrapDate = typeof bootstrap.environment === 'object' && bootstrap.environment !== null
    ? bootstrap.environment.currentDate
    : null;

const initialDate = typeof bootstrapDate === 'string' && bootstrapDate !== ''
    ? bootstrapDate
    : todayIso;

function normaliseGuestCounts(config) {
    const counts = {};
    if (!config || typeof config !== 'object') {
        return counts;
    }

    const ids = Array.isArray(config.ids) ? config.ids : [];
    const min = typeof config.min === 'object' && config.min !== null ? config.min : {};

    ids.forEach((identifier) => {
        const id = String(identifier);
        const minValue = Number.isFinite(min[id]) ? Number(min[id]) : 0;
        counts[id] = minValue;
    });

    return counts;
}

function normaliseUpgradeQuantities(upgrades) {
    if (!Array.isArray(upgrades)) {
        return {};
    }

    return upgrades.reduce((accumulator, upgrade) => {
        if (!upgrade || upgrade.enabled === false) {
            return accumulator;
        }

        const id = upgrade.id !== undefined ? String(upgrade.id) : '';
        if (id === '') {
            return accumulator;
        }

        const minQuantity = Number.isFinite(upgrade.minQuantity) ? Number(upgrade.minQuantity) : 0;
        accumulator[id] = minQuantity;
        return accumulator;
    }, {});
}

const guestCounts = normaliseGuestCounts(bootstrap.activity && bootstrap.activity.guestTypes);
const upgradeQuantities = normaliseUpgradeQuantities(bootstrap.activity && bootstrap.activity.upgrades);

const initialState = {
    bootstrap,
    currency: {
        code: bootstrap.activity && bootstrap.activity.currency && bootstrap.activity.currency.code
            ? String(bootstrap.activity.currency.code).toUpperCase()
            : 'USD',
        symbol: bootstrap.activity && bootstrap.activity.currency && bootstrap.activity.currency.symbol
            ? String(bootstrap.activity.currency.symbol)
            : '$',
        locale: bootstrap.activity && bootstrap.activity.currency && bootstrap.activity.currency.locale
            ? String(bootstrap.activity.currency.locale)
            : 'en-US',
    },
    selectedDate: initialDate,
    visibleMonth: initialDate.slice(0, 7),
    guestCounts,
    guestTypeDetails: [],
    calendarDays: [],
    timeslots: [],
    selectedTimeslotId: null,
    availabilityMetadata: {},
    transportationRouteId: bootstrap.activity
        && bootstrap.activity.transportation
        && bootstrap.activity.transportation.defaultRouteId
            ? String(bootstrap.activity.transportation.defaultRouteId)
            : null,
    upgradeQuantities,
    loading: {
        guestTypes: false,
        availability: false,
        checkout: false,
    },
    pricing: {
        guests: null,
        transportation: null,
        upgrades: null,
        fees: null,
        total: null,
    },
};

const subscribers = new Set();
let state = initialState;

function shallowEqual(a, b) {
    if (a === b) {
        return true;
    }

    if (!a || !b) {
        return false;
    }

    const keysA = Object.keys(a);
    const keysB = Object.keys(b);

    if (keysA.length !== keysB.length) {
        return false;
    }

    for (let index = 0; index < keysA.length; index += 1) {
        const key = keysA[index];
        if (a[key] !== b[key]) {
            return false;
        }
    }

    return true;
}

function mergeSlice(current, updates) {
    if (!updates || typeof updates !== 'object') {
        return current;
    }

    const next = { ...current, ...updates };
    return shallowEqual(current, next) ? current : next;
}

export function getState() {
    return state;
}

export function setState(updater) {
    const updates = typeof updater === 'function' ? updater(state) : updater;
    if (!updates || typeof updates !== 'object') {
        return;
    }

    const nextState = { ...state };
    let changed = false;

    Object.entries(updates).forEach(([key, value]) => {
        if (value === undefined) {
            return;
        }

        if (key === 'loading') {
            const merged = mergeSlice(nextState.loading, value);
            if (merged !== nextState.loading) {
                nextState.loading = merged;
                changed = true;
            }
            return;
        }

        if (key === 'pricing') {
            const merged = mergeSlice(nextState.pricing, value);
            if (merged !== nextState.pricing) {
                nextState.pricing = merged;
                changed = true;
            }
            return;
        }

        if (key === 'guestCounts') {
            const merged = mergeSlice(nextState.guestCounts, value);
            if (merged !== nextState.guestCounts) {
                nextState.guestCounts = merged;
                changed = true;
            }
            return;
        }

        if (key === 'upgradeQuantities') {
            const merged = mergeSlice(nextState.upgradeQuantities, value);
            if (merged !== nextState.upgradeQuantities) {
                nextState.upgradeQuantities = merged;
                changed = true;
            }
            return;
        }

        if (Array.isArray(value)) {
            const currentValue = Array.isArray(nextState[key]) ? nextState[key] : [];
            const sameLength = currentValue.length === value.length;
            if (sameLength && currentValue.every((item, index) => item === value[index])) {
                return;
            }
        } else if (typeof value === 'object' && value !== null) {
            const currentValue = nextState[key];
            if (currentValue && shallowEqual(currentValue, value)) {
                return;
            }
        } else if (nextState[key] === value) {
            return;
        }

        nextState[key] = value;
        changed = true;
    });

    if (!changed) {
        return;
    }

    state = nextState;

    subscribers.forEach((listener) => {
        try {
            listener(state);
        } catch (error) {
            console.error('Store subscriber error', error);
        }
    });
}

export function subscribe(listener, { immediate = true } = {}) {
    if (typeof listener !== 'function') {
        return () => {};
    }

    subscribers.add(listener);

    if (immediate) {
        try {
            listener(state);
        } catch (error) {
            console.error('Store subscriber error', error);
        }
    }

    return () => {
        subscribers.delete(listener);
    };
}

export function resetState() {
    state = { ...initialState }; // shallow copy is fine because nested slices rebuilt via setState
    subscribers.forEach((listener) => {
        try {
            listener(state);
        } catch (error) {
            console.error('Store subscriber error', error);
        }
    });
}

export default {
    getState,
    setState,
    subscribe,
    resetState,
};
