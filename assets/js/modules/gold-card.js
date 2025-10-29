import { getState, setState, subscribe } from '../core/store.js';
import { qs } from '../utility/dom.js';
import {
    computeGoldCardPrice,
    formatCurrencyForState,
} from './pricing-utils.js';
import { normaliseGoldCardEntries, buildGoldCardSignature } from './gold-card-utils.js';

let root;
let numberInput;
let upsellCheckbox;
let priceLabel;

function updateNumberValue(value) {
    const nextValue = typeof value === 'string' ? value : '';
    const state = getState();
    const current = state.shakaGoldCardNumber || '';
    if (current === nextValue) {
        return;
    }

    const entries = normaliseGoldCardEntries(nextValue);
    const signature = buildGoldCardSignature(entries);

    setState({
        shakaGoldCardNumber: nextValue,
        goldCardDiscount: signature === (state.goldCardDiscount?.signature ?? null)
            ? state.goldCardDiscount
            : null,
    });
}

function handleNumberInput(event) {
    const target = event.target;
    if (!target || target.tagName !== 'INPUT') {
        return;
    }

    updateNumberValue(target.value);
}

function handleNumberBlur(event) {
    const target = event.target;
    if (!target || target.tagName !== 'INPUT') {
        return;
    }

    const trimmed = target.value.trim();
    target.value = trimmed;
    updateNumberValue(trimmed);
}

function handleUpsellChange(event) {
    const target = event.target;
    if (!target || target.tagName !== 'INPUT') {
        return;
    }

    const checked = Boolean(target.checked);
    if (getState().buyGoldCard === checked) {
        return;
    }

    setState({ buyGoldCard: checked });
}

function render(state) {
    if (numberInput) {
        const value = state.shakaGoldCardNumber || '';
        if (numberInput.value !== value) {
            numberInput.value = value;
        }
    }

    if (upsellCheckbox) {
        const checked = Boolean(state.buyGoldCard);
        if (upsellCheckbox.checked !== checked) {
            upsellCheckbox.checked = checked;
        }
    }

    if (priceLabel) {
        const price = computeGoldCardPrice(state);
        const formatted = formatCurrencyForState(state, price);
        priceLabel.textContent = formatted ? `Add for ${formatted}` : '';
    }
}

export function initGoldCard() {
    root = qs('[data-component="shaka-gold-card"]');
    if (!root) {
        return;
    }

    numberInput = qs('[data-goldcard-number]', root);
    upsellCheckbox = qs('[data-goldcard-upsell]', root);
    priceLabel = qs('[data-goldcard-price]', root);

    if (numberInput) {
        numberInput.addEventListener('input', handleNumberInput);
        numberInput.addEventListener('blur', handleNumberBlur);
    }

    if (upsellCheckbox) {
        upsellCheckbox.addEventListener('change', handleUpsellChange);
    }

    subscribe((state) => render(state));
    render(getState());
}

export default initGoldCard;
