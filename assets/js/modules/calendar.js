import { getState, setState, subscribe } from '../core/store.js';
import { qs, clearChildren, createElement } from '../utility/dom.js';
import { formatDateLong } from '../utility/formating.js';

let root;
let monthLabel;
let grid;
let hint;
let prevButton;
let nextButton;
let loadingOverlay;

const DAY_COUNT = 42; // 6 weeks
const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const statusLabels = {
    available: 'Available',
    limited: 'Limited availability',
    sold_out: 'Sold out',
    soldout: 'Sold out',
    full: 'Sold out',
    booked: 'Sold out',
    unavailable: 'Unavailable',
    closed: 'Unavailable',
    fallback: 'Estimated availability',
};

const BRAND_FALLBACK = '#1C55DB';
const RED_UNAVAILABLE = '#F87171';
const GRAY_MUTED = '#CBD5F5';

function getBrandColor() {
    if (typeof window === 'undefined') {
        return BRAND_FALLBACK;
    }

    const computed = window.getComputedStyle(document.documentElement);
    const value = computed.getPropertyValue('--sgc-brand-primary');
    return value && value.trim() !== '' ? value.trim() : BRAND_FALLBACK;
}

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

    if (loadingOverlay) {
        const isLoading = state.loading?.availability;
        if (isLoading) {
            loadingOverlay.classList.remove('hidden');
            loadingOverlay.classList.add('flex');
        } else {
            loadingOverlay.classList.add('hidden');
            loadingOverlay.classList.remove('flex');
        }
    }

    clearChildren(grid);

    const brandColor = getBrandColor();

    for (let dayOffset = 0; dayOffset < DAY_COUNT; dayOffset += 1) {
        const current = new Date(startDate);
        current.setDate(startDate.getDate() + dayOffset);

        const currentMonthKey = toMonthKey(current);
        const currentDateKey = toDateKey(current);
        const status = (dayStatuses.get(currentDateKey) || '').toLowerCase();
        const statusLabel = statusLabels[status] || 'Status unknown';
        const outsideMonth = currentMonthKey !== toMonthKey(monthDate);
        const selected = selectedDate === currentDateKey;
        const pastDate = isPast(current);
        const unavailableDay = ['sold_out', 'soldout', 'full', 'booked', 'unavailable', 'closed'].includes(status);
        let disabled = true;
        let textColor = GRAY_MUTED;

        if (!outsideMonth && !pastDate) {
            if (unavailableDay) {
                textColor = RED_UNAVAILABLE;
            } else {
                disabled = false;
                textColor = brandColor;
            }
        }

        const cell = createElement('div', {
            className: 'flex justify-center',
            attributes: { role: 'gridcell' },
        });

        const classList = [
            'flex h-12 w-12 items-center justify-center rounded-full text-sm font-medium transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-400/70',
        ];

        if (selected) {
            classList.push('shadow-lg');
        } else if (disabled) {
            classList.push('cursor-not-allowed');
        } else {
            classList.push('cursor-pointer', 'hover:bg-blue-50');
        }

        if (outsideMonth || pastDate) {
            classList.push('text-slate-300');
        }

        const button = createElement('button', {
            className: classList.join(' '),
            attributes: {
                type: 'button',
                'data-date': currentDateKey,
                'data-month': currentMonthKey,
                'data-status': status || 'unknown',
            },
        });

        button.setAttribute('aria-label', `Select ${formatDateLong(currentDateKey, locale)} (${statusLabel})`);

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

        button.style.backgroundColor = selected ? brandColor : 'transparent';
        button.style.color = selected ? '#ffffff' : textColor;

        if (selected) {
            button.style.opacity = '1';
        } else if (outsideMonth || pastDate) {
            button.style.opacity = '0.45';
        } else if (unavailableDay) {
            button.style.opacity = '0.65';
        } else {
            button.style.opacity = '1';
        }

        const time = createElement('time', {
            className: selected ? 'text-base font-medium' : 'text-base',
            text: String(current.getDate()),
            attributes: { datetime: currentDateKey },
        });

        button.appendChild(time);
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
 //   setLoading(true);

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
    loadingOverlay = qs('[data-calendar-loading]', root);

    if (!grid) {
        grid = createElement('div', {
            attributes: { role: 'grid' },
            className: 'mt-4 grid grid-cols-7 gap-y-4 gap-x-3',
        });
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
            className: 'grid grid-cols-7 gap-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-400',
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
