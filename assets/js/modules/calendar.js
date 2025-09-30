import { getState, setState, subscribe } from '../core/store.js';
import { qs, clearChildren, createElement } from '../utility/dom.js';
import { formatDateLong } from '../utility/formating.js';

let root;
let monthLabel;
let grid;
let hint;
let prevButton;
let nextButton;

const DAY_COUNT = 42; // 6 weeks
const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const statusDescriptions = {
    available: { text: 'Available', className: 'text-emerald-600' },
    limited: { text: 'Limited', className: 'text-amber-600' },
    sold_out: { text: 'Sold out', className: 'text-red-600' },
    soldout: { text: 'Sold out', className: 'text-red-600' },
    full: { text: 'Sold out', className: 'text-red-600' },
    booked: { text: 'Sold out', className: 'text-red-600' },
    unavailable: { text: 'Unavailable', className: 'text-slate-400' },
    closed: { text: 'Unavailable', className: 'text-slate-400' },
    fallback: { text: 'Estimated', className: 'text-slate-500' },
};

function parseMonth(value) {
    if (!value) {
        return null;
    }
    const instance = new Date(`${value}-01T00:00:00`);
    if (Number.isNaN(instance.getTime())) {
        return null;
    }
    return instance;
}

function startOfGrid(monthDate) {
    const instance = new Date(monthDate);
    const day = instance.getDay();
    instance.setDate(instance.getDate() - day);
    instance.setHours(0, 0, 0, 0);
    return instance;
}

function pad(value) {
    return value.toString().padStart(2, '0');
}

function toMonthKey(date) {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;
}

function toDateKey(date) {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function isPast(date) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return date < today;
}

function buildCalendarGrid(state) {
    const monthDate = parseMonth(state.visibleMonth) || new Date();
    const startDate = startOfGrid(monthDate);
    const dayStatuses = new Map((state.calendarDays || []).map((day) => [day.date, day.status]));
    const selectedDate = state.selectedDate;
    const selectedMonth = selectedDate ? selectedDate.slice(0, 7) : null;

    const locale = state.currency?.locale || 'en-US';

    clearChildren(grid);

    for (let dayOffset = 0; dayOffset < DAY_COUNT; dayOffset += 1) {
        const current = new Date(startDate);
        current.setDate(startDate.getDate() + dayOffset);

        const currentMonthKey = toMonthKey(current);
        const currentDateKey = toDateKey(current);
        const status = (dayStatuses.get(currentDateKey) || '').toLowerCase();
        const statusInfo = statusDescriptions[status] || null;
        const outsideMonth = currentMonthKey !== toMonthKey(monthDate);
        const selected = selectedDate === currentDateKey;
        const pastDate = isPast(current);
        const disabled = pastDate || status === 'sold_out' || status === 'unavailable';

        const cell = createElement('div', { attributes: { role: 'gridcell' } });
        const button = createElement('button', {
            className: [
                'w-full rounded-lg border px-2 py-2 text-sm font-medium transition-colors focus:outline-none focus-visible:ring focus-visible:ring-blue-500/40',
                outsideMonth ? 'text-slate-400 border-transparent' : 'text-slate-900 border-transparent hover:border-blue-200',
                selected ? 'border-blue-500 bg-blue-50 text-blue-700 shadow-inner' : '',
                disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
            ].filter(Boolean).join(' '),
            attributes: {
                type: 'button',
                'data-date': currentDateKey,
                'data-month': currentMonthKey,
                'data-status': status || 'unknown',
            },
        });

        button.setAttribute('aria-label', `Select ${formatDateLong(currentDateKey, locale)}`);

        if (selected) {
            button.setAttribute('data-selected', '1');
            button.setAttribute('aria-pressed', 'true');
        } else {
            button.setAttribute('aria-pressed', 'false');
        }

        if (outsideMonth) {
            button.setAttribute('data-outside-month', '1');
        }

        if (disabled) {
            button.disabled = true;
        }

        const time = createElement('time', {
            className: selected ? 'block text-base font-semibold' : 'block text-sm',
            text: String(current.getDate()),
            attributes: { datetime: currentDateKey },
        });

        const indicator = createElement('span', {
            className: ['mt-1 block text-xs', statusInfo ? statusInfo.className : 'text-slate-400'].join(' '),
            text: statusInfo ? statusInfo.text : '',
        });

        button.appendChild(time);
        button.appendChild(indicator);
        cell.appendChild(button);
        grid.appendChild(cell);
    }

    monthLabel.textContent = monthDate.toLocaleDateString(state.currency?.locale || 'en-US', {
        month: 'long',
        year: 'numeric',
    });

    if (hint) {
        const metadata = state.availabilityMetadata || {};
        if (metadata.fallback) {
            hint.textContent = 'Live availability is temporarily offline. Dates shown are estimated.';
        } else {
            hint.textContent = 'Availability syncs automatically. Select a date to load timeslots.';
        }
    }

    if (selectedMonth && selectedMonth !== state.visibleMonth) {
        // Keep visible month aligned with selected date.
        setState({ visibleMonth: selectedMonth });
    }

    updateNavigation(state);
}

function updateNavigation(state) {
    if (!prevButton || !nextButton) {
        return;
    }

    const visibleDate = parseMonth(state.visibleMonth) || new Date();
    const today = new Date();
    today.setDate(1);
    today.setHours(0, 0, 0, 0);

    const previousMonth = new Date(visibleDate);
    previousMonth.setMonth(previousMonth.getMonth() - 1);

    prevButton.disabled = previousMonth < today;
}

function moveMonth(offset) {
    const state = getState();
    const visible = parseMonth(state.visibleMonth) || new Date();
    const candidate = new Date(visible);
    candidate.setMonth(candidate.getMonth() + offset);
    candidate.setDate(1);

    const today = new Date();
    today.setDate(1);
    today.setHours(0, 0, 0, 0);

    if (candidate < today) {
        return;
    }

    const nextMonthKey = toMonthKey(candidate);
    const currentDay = state.selectedDate ? Number(state.selectedDate.split('-')[2]) : 1;

    const temp = new Date(candidate);
    const daysInMonth = new Date(candidate.getFullYear(), candidate.getMonth() + 1, 0).getDate();
    temp.setDate(Math.min(currentDay, daysInMonth));

    const nextDateKey = toDateKey(temp);

    setState({
        visibleMonth: nextMonthKey,
        selectedDate: nextDateKey,
    });
}

function handleGridClick(event) {
    const button = event.target.closest('button[data-date]');
    if (!button || button.disabled) {
        return;
    }

    const date = button.getAttribute('data-date');
    if (!date) {
        return;
    }

    setState({ selectedDate: date, visibleMonth: date.slice(0, 7) });
}

function render(state) {
    buildCalendarGrid(state);
}

export function initCalendar() {
    root = qs('[data-component="calendar"]');
    if (!root) {
        return;
    }

    monthLabel = qs('[data-calendar-month-label]', root);
    grid = qs('[data-calendar] [role="grid"]', root) || qs('[role="grid"]', root);
    hint = qs('[data-calendar-hint]', root);
    prevButton = qs('[data-action="previous-month"]', root);
    nextButton = qs('[data-action="next-month"]', root);

    if (!grid) {
        grid = createElement('div', { attributes: { role: 'grid' }, className: 'mt-2 grid grid-cols-7 gap-2' });
        root.appendChild(grid);
    }

    if (prevButton) {
        prevButton.addEventListener('click', () => moveMonth(-1));
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => moveMonth(1));
    }

    if (grid) {
        grid.addEventListener('click', handleGridClick);
    }

    subscribe(render);

    // Render initial header row if not present.
    const headerRow = qs('[data-calendar] .grid.grid-cols-7.text-center');
    if (!headerRow) {
        const header = createElement('div', {
            className: 'grid grid-cols-7 text-center text-xs font-semibold uppercase tracking-wide text-slate-400',
        });
        WEEKDAY_LABELS.forEach((weekday) => {
            header.appendChild(createElement('span', { text: weekday }));
        });
        const calendarContainer = qs('[data-calendar]', root) || root;
        calendarContainer.insertBefore(header, calendarContainer.firstChild);
    }

    render(getState());
}

export default initCalendar;
