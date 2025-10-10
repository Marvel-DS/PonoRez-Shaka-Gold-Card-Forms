import { setState, subscribe } from '../core/store.js';
import { qs, qsa, clamp } from '../utility/dom.js';

const CHECK_ICON_SVG = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="h-full w-full"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>';

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

function isActiveSelection(value, bounds) {
    if (!bounds) {
        return false;
    }

    if (bounds.min > 0) {
        return value >= bounds.min;
    }

    return value > bounds.min;
}

function applyCardState(container, value) {
    const bounds = getBounds(container);
    const card = qs('[data-upgrade-card]', container);
    const indicator = qs('[data-upgrade-indicator]', container);
    const icon = qs('[data-upgrade-icon]', container);
    const active = isActiveSelection(value, bounds);

    if (!card || !indicator || !icon) {
        return;
    }

    container.dataset.active = active ? 'true' : 'false';

    if (active) {
        card.classList.add('border-[var(--sgc-brand-primary)]', 'bg-[var(--sgc-brand-primary)]/10', 'shadow-lg');
        card.classList.remove('border-slate-200');
        indicator.classList.add('border-transparent', 'bg-[var(--sgc-brand-primary)]');
        indicator.classList.remove('border-slate-300', 'bg-white');
        icon.innerHTML = CHECK_ICON_SVG;
        return;
    }

    card.classList.remove('border-[var(--sgc-brand-primary)]', 'bg-[var(--sgc-brand-primary)]/10', 'shadow-lg');
    card.classList.add('border-slate-200');
    indicator.classList.remove('border-transparent', 'bg-[var(--sgc-brand-primary)]');
    indicator.classList.add('border-slate-300', 'bg-white');
    icon.innerHTML = '';
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
    applyCardState(container, nextValue);
}

function handleInput(event, container) {
    const input = event.currentTarget;
    const { min, max } = getBounds(container);
    const desired = Number(input.value || 0);
    const nextValue = clamp(desired, min, max);
    input.value = String(nextValue);
    updateQuantity(container.dataset.upgradeId, nextValue);
    applyCardState(container, nextValue);
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
        applyCardState(container, bounded);
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
            applyCardState(container, Number(input.value || 0));
        } else {
            applyCardState(container, getBounds(container).min);
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
