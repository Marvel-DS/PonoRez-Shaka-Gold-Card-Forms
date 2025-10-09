import { setState, subscribe } from '../core/store.js';
import { qs, qsa } from '../utility/dom.js';

const CHECK_ICON_SVG = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="h-full w-full"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>';

let root;

function updateOptionState(option, isChecked) {
    const input = qs('input[type="radio"]', option);
    if (input) {
        input.checked = isChecked;
    }

    const card = qs('[data-transportation-card]', option);
    const radioVisual = qs('[data-transportation-radio]', option);
    const radioIcon = qs('[data-transportation-icon]', option);

    if (!card || !radioVisual || !radioIcon) {
        return;
    }

    if (isChecked) {
        card.classList.add('border-[#1C55DB]', 'bg-[#1C55DB]/10', 'shadow-lg');
        radioVisual.classList.remove('border-slate-300', 'bg-white', 'border-slate-200', 'bg-slate-100');
        radioVisual.classList.add('border-transparent', 'bg-[var(--sgc-brand-primary)]');
        radioIcon.innerHTML = CHECK_ICON_SVG;
        return;
    }

    card.classList.remove('border-[#1C55DB]', 'bg-[#1C55DB]/10', 'shadow-lg');
    radioVisual.classList.remove('border-transparent', 'bg-[var(--sgc-brand-primary)]');
    radioVisual.classList.add('border-slate-300', 'bg-white');
    radioIcon.innerHTML = '';
}

function render(state) {
    if (!root) {
        return;
    }

    qsa('[data-transportation-option]', root).forEach((option) => {
        const id = option.dataset.transportationOption;
        updateOptionState(option, state.transportationRouteId === id);
    });
}

function handleChange(event) {
    const target = event.target;
    if (!target || target.name !== 'transportationRouteId') {
        return;
    }

    setState({ transportationRouteId: target.value });
}

export function initTransportation() {
    root = qs('[data-component="transportation"]');
    if (!root) {
        return;
    }

    root.addEventListener('change', handleChange);

    const checked = qs('input[name="transportationRouteId"]:checked', root);
    if (checked) {
        setState({ transportationRouteId: checked.value });
    }

    subscribe(render);
}

export default initTransportation;
