import { setState, subscribe } from '../core/store.js';
import { qs, qsa, clamp } from '../utility/dom.js';

let root;

function getBounds(container) {
    const min = Number(container.dataset.min || 0);
    const maxAttr = container.dataset.max;
    const max = maxAttr !== undefined ? Number(maxAttr) : Infinity;
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

function applySelectionState(container, value) {
    const { min } = getBounds(container);
    container.dataset.active = value > min ? 'true' : 'false';
}

function handleSelect(event, container) {
    const select = event.currentTarget;
    const id = container.dataset.upgradeId;
    if (!select || !id) {
        return;
    }

    const { min, max } = getBounds(container);
    const desired = Number(select.value || min);
    const nextValue = clamp(desired, min, max);

    if (Number(select.value || min) !== nextValue) {
        select.value = String(nextValue);
    }

    updateQuantity(id, nextValue);
    applySelectionState(container, nextValue);
}

function syncFromState(state) {
    if (!root) {
        return;
    }

    qsa('[data-upgrade-id]', root).forEach((container) => {
        const id = container.dataset.upgradeId;
        const select = qs('[data-upgrade-select]', container) || qs('select', container);
        if (!id || !select) {
            return;
        }

        const { min, max } = getBounds(container);
        const stateValue = Number(state.upgradeQuantities?.[id] ?? min);
        const bounded = clamp(stateValue, min, max);

        if (Number(select.value || min) !== bounded) {
            select.value = String(bounded);
        }

        applySelectionState(container, bounded);
    });
}

export function initUpgrades() {
    root = qs('[data-component="upgrades"]');
    if (!root) {
        return;
    }

    const containers = qsa('[data-upgrade-id]', root);

    containers.forEach((container) => {
        const select = qs('[data-upgrade-select]', container) || qs('select', container);
        if (!select) {
            applySelectionState(container, getBounds(container).min);
            return;
        }

        select.addEventListener('change', (event) => handleSelect(event, container));
        select.addEventListener('blur', (event) => handleSelect(event, container));
        applySelectionState(container, Number(select.value || getBounds(container).min));
    });

    const initialQuantities = containers.reduce((accumulator, container) => {
        const id = container.dataset.upgradeId;
        const select = qs('[data-upgrade-select]', container) || qs('select', container);
        if (!id || !select) {
            return accumulator;
        }

        const { min } = getBounds(container);
        accumulator[id] = Number(select.value || min);
        return accumulator;
    }, {});

    if (Object.keys(initialQuantities).length > 0) {
        setState({ upgradeQuantities: initialQuantities });
    }

    subscribe((state) => syncFromState(state));
}

export default initUpgrades;
