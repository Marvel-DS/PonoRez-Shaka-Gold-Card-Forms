/**
 * calendar.js
 * Handles calendar UI (date input + interactive grid).
 *
 * Notes:
 * - Does not call SOAP directly.
 * - On date change, dispatches an event so booking.js can refresh availability.
 * - Uses relative paths and SGC_BASE for consistency if needed.
 */

//import { qs } from './utils.js';

export function initCalendar(calendarEl) {
  if (!calendarEl) return;

  const labelEl = calendarEl.querySelector('[data-cal-label]');
  const prevBtn = calendarEl.querySelector('[data-cal-prev]');
  const nextBtn = calendarEl.querySelector('[data-cal-next]');
  const gridEl  = calendarEl.querySelector('[data-cal-grid]');
  const selectedInput = calendarEl.querySelector('[data-selected-input]');
  const supplierId = calendarEl.dataset.supplierId;
  const activityId = calendarEl.dataset.activityId;

  const availabilityCache = {};
  const CACHE_TTL = 60000; // 60 seconds

  // Allow external events to clear cache, e.g., when guest counts change
  window.addEventListener('SGC:guestsChanged', () => {
    for (const key in availabilityCache) {
      delete availabilityCache[key];
    }
    render();
  });

  let availability = {};

  const fmtDateISO = (d) => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  };

  const fmtDateUS = (d) => {
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const y = d.getFullYear();
    return `${m}/${day}/${y}`;
  };

  const parseDateString = (dateStr) => {
    if (!dateStr) return null;
    if (/^\d{2}\/\d{2}\/\d{4}$/.test(dateStr)) {
      const [m, d, y] = dateStr.split('/');
      return new Date(`${y}-${m}-${d}T00:00:00`);
    }
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
      return new Date(`${dateStr}T00:00:00`);
    }
    return null;
  };

  const minDate = calendarEl.dataset.min ? parseDateString(calendarEl.dataset.min) : null;
  const maxDate = calendarEl.dataset.max ? parseDateString(calendarEl.dataset.max) : null;

  let today = new Date();
  today.setHours(0, 0, 0, 0);
  let tomorrow = new Date(today);
  tomorrow.setDate(today.getDate() + 1);
  tomorrow.setHours(0,0,0,0);

  let selectedDate = null;
  if (selectedInput?.value) {
    selectedDate = parseDateString(selectedInput.value);
    if (selectedDate) selectedDate.setHours(0,0,0,0);
  } else {
    // Prefer today if available, else fallback to tomorrow
    selectedDate = new Date(today);
    if (selectedInput) selectedInput.value = fmtDateUS(today);
  }

  let viewYear = today.getFullYear();
  let viewMonth = today.getMonth();

  async function fetchAvailability(year, month) {
    const key = `${supplierId}-${activityId}-${year}-${month}`;
    const now = Date.now();

    if (availabilityCache[key] && (now - availabilityCache[key].ts) < CACHE_TTL) {
      return availabilityCache[key].data;
    }

    try {
      const base = window.SGC_BASE || '/SCG';
      const dateStr = fmtDateUS(new Date(year, month, 1));
      const res = await fetch(`${base}/api/availability.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          supplierId: supplierId,
          activityIds: [activityId],
          date: dateStr
        })
      });
      const text = await res.text();
      let data = {};
      try {
        data = JSON.parse(text);
      } catch (err) {
        console.error("availability.php did not return valid JSON:", text);
        return {};
      }
      const days = data.days || {};

      availabilityCache[key] = { data: days, ts: now };
      return days;
    } catch (e) {
      console.error('Failed to fetch availability', e);
      return {};
    }
  }

  async function render() {
    availability = await fetchAvailability(viewYear, viewMonth);

    if (!(selectedDate instanceof Date) || isNaN(selectedDate.getTime())) {
      selectedDate = new Date(today);
      if (selectedInput) selectedInput.value = fmtDateUS(today);
    }

    if (availability[fmtDateISO(selectedDate)] === 'sold_out') {
      selectedDate = new Date(tomorrow);
      if (selectedInput) selectedInput.value = fmtDateUS(tomorrow);
    }

    const monthName = new Date(viewYear, viewMonth, 1).toLocaleString(undefined, { month: 'long', year: 'numeric' });
    if (labelEl) labelEl.textContent = monthName;

    const firstDay = new Date(viewYear, viewMonth, 1);
    const lastDay  = new Date(viewYear, viewMonth + 1, 0);
    const startWeekday = firstDay.getDay();
    const daysInMonth  = lastDay.getDate();

    const parts = [];
    for (let i = 0; i < startWeekday; i++) parts.push('<div></div>');

    for (let day = 1; day <= daysInMonth; day++) {
      const d = new Date(viewYear, viewMonth, day);
      const iso = fmtDateISO(d);
      const us  = fmtDateUS(d);
      const state = (availability[iso] ?? 'sold_out').toLowerCase();
      const inRange = (!minDate || d >= minDate) && (!maxDate || d <= maxDate);
      const isSelected = selectedDate && fmtDateISO(selectedDate) === iso;
      const isToday = fmtDateISO(today) === iso;

      const base = 'w-12 h-12 rounded-full flex items-center justify-center text-sm font-normal';
      let cls = base;
      let attrs = '';

      if (!inRange || state === 'sold_out' || state === 'soldout') {
        cls += ' text-slate-300 cursor-not-allowed';
        attrs = ' aria-disabled="true" disabled title="No departures"';
      } else if (isSelected) {
        cls += ' bg-[color:var(--brand-color)] text-white';
      } else if (isToday) {
        cls += ' ring-2 ring-[color:var(--brand-color)] text-body';
      } else {
        cls += ' text-body hover:bg-slate-50';
      }

      parts.push(`<button type="button" role="gridcell" aria-selected="${isSelected ? 'true' : 'false'}" class="${cls}" data-date="${us}"${attrs}>${day}</button>`);
    }

    gridEl.innerHTML = parts.join('');

    gridEl.querySelectorAll('button[data-date]:not([disabled])').forEach(btn => {
      btn.addEventListener('click', () => {
        const parsed = parseDateString(btn.getAttribute('data-date'));
        if (!parsed) {
          console.warn("Invalid date string on click:", btn.getAttribute('data-date'));
          return;
        }
        selectedDate = parsed;
        if (selectedInput) selectedInput.value = fmtDateUS(parsed);

        const event = new CustomEvent('SGC:dateSelected', {
          detail: { date: btn.getAttribute('data-date') },
          bubbles: true
        });
        calendarEl.dispatchEvent(event);

        // Also trigger a change event on the hidden input so booking.js can hook in
        if (selectedInput) {
          selectedInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        const targetDate = btn.getAttribute('data-date');
        render().then(() => {
          const newBtn = gridEl.querySelector(`button[data-date="${targetDate}"]`);
          newBtn?.focus();
        });
      });
      btn.addEventListener('keydown', (e) => {
        if (!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Enter',' '].includes(e.key)) return;
        e.preventDefault();
        const current = parseDateString(btn.getAttribute('data-date'));
        if (!(current instanceof Date) || isNaN(current.getTime())) {
          console.warn("Invalid or null date on keydown:", btn.getAttribute('data-date'));
          return;
        }
        let target = new Date(current.getTime());
        if (e.key === 'ArrowLeft') target.setDate(current.getDate() - 1);
        if (e.key === 'ArrowRight') target.setDate(current.getDate() + 1);
        if (e.key === 'ArrowUp') target.setDate(current.getDate() - 7);
        if (e.key === 'ArrowDown') target.setDate(current.getDate() + 7);
        if (e.key === 'Enter' || e.key === ' ') btn.click();
        const tIso = fmtDateISO(target);
        const tUs  = fmtDateUS(target);
        const next = Array.from(gridEl.querySelectorAll('button[data-date]'))
          .find(el => el.dataset.date === tUs && !el.disabled);
        if (next) next.focus();
      });
    });
  }

  if (prevBtn) prevBtn.addEventListener('click', async () => {
    viewMonth--;
    if (viewMonth < 0) { viewMonth = 11; viewYear--; }
    await render();
  });

  if (nextBtn) nextBtn.addEventListener('click', async () => {
    viewMonth++;
    if (viewMonth > 11) { viewMonth = 0; viewYear++; }
    await render();
  });

  render();
}