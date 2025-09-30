import { getState, setState, subscribe } from '../core/store.js';
import { qs, qsa } from '../utility/dom.js';

let root;

function render(state) {
    if (!root) {
        return;
    }

    qsa('[data-transportation-option]', root).forEach((option) => {
        const id = option.dataset.transportationOption;
        const input = qs('input[type="radio"]', option);
        if (input) {
            input.checked = state.transportationRouteId === id;
        }
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
