import { getState, setState, subscribe } from '../core/store.js';
import { qs, qsa, clamp } from '../utility/dom.js';

let root;

function getBounds(container) {
    const min = Number(container.dataset.min || 0);
    const max = container.dataset.max !== undefined ? Number(container.dataset.max) : Infinity;
    return { min, max: Number.isFinite(max) ? max : Infinity };
}

function updateQuantity(id, value) {
    setState((current) => ({
        upgradeQuantities: {
            ...current.upgradeQuantities,
            [id]: value,
        },
    }));
}

function handleCounter(event, container) {
    const button = event.currentTarget;
    const action = button.dataset.action;
    const input = qs('input[type="number"]', container);
    if (!input) {
        return;
    }

    const { min, max } = getBounds(container);
    const currentValue = Number(input.value || 0);
    const delta = action === 'decrement' ? -1 : 1;
    const nextValue = clamp(currentValue + delta, min, max);
    input.value = String(nextValue);
    updateQuantity(container.dataset.upgradeId, nextValue);
}

function handleInput(event, container) {
    const input = event.currentTarget;
    const { min, max } = getBounds(container);
    const desired = Number(input.value || 0);
    const nextValue = clamp(desired, min, max);
    input.value = String(nextValue);
    updateQuantity(container.dataset.upgradeId, nextValue);
}

function syncFromState(state) {
    qsa('[data-upgrade-id]', root).forEach((container) => {
        const id = container.dataset.upgradeId;
        const input = qs('input[type="number"]', container);
        if (!id || !input) {
            return;
        }
        const { min, max } = getBounds(container);
        const stateValue = Number(state.upgradeQuantities?.[id] ?? min);
        const bounded = clamp(stateValue, min, max);
        if (Number(input.value || 0) !== bounded) {
            input.value = String(bounded);
        }
    });
}

export function initUpgrades() {
    root = qs('[data-component="upgrades"]');
    if (!root) {
        return;
    }

    qsa('[data-upgrade-id]', root).forEach((container) => {
        const decrement = qs('[data-action="decrement"]', container);
        const increment = qs('[data-action="increment"]', container);
        const input = qs('input[type="number"]', container);

        if (decrement) {
            decrement.addEventListener('click', (event) => handleCounter(event, container));
        }

        if (increment) {
            increment.addEventListener('click', (event) => handleCounter(event, container));
        }

        if (input) {
            input.addEventListener('change', (event) => handleInput(event, container));
            input.addEventListener('blur', (event) => handleInput(event, container));
        }
    });

    const initialQuantities = qsa('[data-upgrade-id]', root).reduce((accumulator, container) => {
        const id = container.dataset.upgradeId;
        const input = qs('input[type="number"]', container);
        if (!id || !input) {
            return accumulator;
        }
        accumulator[id] = Number(input.value || 0);
        return accumulator;
    }, {});

    if (Object.keys(initialQuantities).length > 0) {
        setState({ upgradeQuantities: initialQuantities });
    }

    subscribe((state) => syncFromState(state));
}

export default initUpgrades;
