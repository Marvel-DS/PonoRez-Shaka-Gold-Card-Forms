/**
 * booking.js
 * Handles booking form interactions (guest type selection, validation, availability refresh, upgrades, reservation submission).
 *
 * Notes:
 * - Uses relative endpoints via fetchData (api.js) so the app can live under any folder.
 * - Expects the form element to optionally carry data-activity-ids (JSON array) and data-supplier key.
 * - Falls back to window.SGC_ACTIVITY_IDS if data-activity-ids is not present (to be injected by PHP if desired).
 */

import { qs, qsa, debounce } from './utils.js';
import { fetchData } from './api.js';


/**
 * Extract activity IDs from the form dataset or global fallback.
 */
function getActivityIds(form) {
  const ds = form?.dataset?.activityIds;
  if (ds) {
    try { return JSON.parse(ds); } catch { /* ignore */ }
  }
  if (Array.isArray(window.SGC_ACTIVITY_IDS)) return window.SGC_ACTIVITY_IDS;
  return [];
}

/**
 * Collect guest counts from selects with class `guestCountSelect`.
 * Supports IDs like `guest-214` OR data attribute `data-guest-type-id`.
 */
function getGuestCounts(form) {
  const selects = qsa('.guestCountSelect', form);
  const result = {};
  selects.forEach(sel => {
    const rawId = sel.dataset.guestTypeId || sel.id?.replace('guest-', '') || '';
    const id = parseInt(rawId, 10);
    const qty = parseInt(sel.value || '0', 10) || 0;
    if (!Number.isNaN(id)) result[id] = qty;
  });
  return result;
}

function getSelectedDate(form) {
  const el = qs('.dateTextInput', form);
  return el ? (el.value || '').trim() : '';
}

function getSelectedTimeslot(form) {
  const selected = qs('input[name="timeslot"]:checked', form);
  return selected ? selected.value : '';
}

function setSubmitEnabled(form, enabled) {
  const btn = qs('button[type="submit"], .btn-primary[type="submit"]', form);
  if (btn) btn.disabled = !enabled;
}

async function refreshTimeslots(form) {
  const date = getSelectedDate(form);
  const activityIds = getActivityIds(form);
  const guestCounts = getGuestCounts(form);

  if (!date || activityIds.length === 0) {
    // Not enough info to query
    setSubmitEnabled(form, false);
    const container = qs('.timeslotContainer', form);
    if (container) container.innerHTML = '';
    return;
  }

  const payload = { date, activityIds, guestCounts };
  // POST to relative endpoint; backend will be scaffolded later
  const res = await fetchData('availability.php', {
    method: 'POST',
    body: payload,
  });

  // Update guest type prices if available
  if (res && Array.isArray(res.guestTypes)) {
    console.log("Guest types from API:", res.guestTypes);
    res.guestTypes.forEach(gt => {
      const priceEl = document.querySelector(`[data-guest-price="${gt.id}"]`);
      if (priceEl) {
        console.log("Updating price for guest type", gt.id, "->", gt.price);
        const price = Number(gt.price);
        priceEl.textContent = isNaN(price) ? '-' : `$${price.toFixed(2)}`;
      }
      else {
        console.warn("No price element found for guest type", gt.id);
      }
      // Optionally disable selects if no availability
      const selectEl = document.querySelector(`#guest-${gt.id}`);
      if (selectEl) {
        console.log("Guest type", gt.id, "availability:", gt.availability);
        if (gt.availability === 0) {
          selectEl.disabled = true;
        } else {
          selectEl.disabled = false;
        }
      }
    });
  }

  // Expecting a shape like { timeslots: [{id, label}], status: 'ok' }
  const container = qs('.timeslotContainer', form);
  if (container) {
    container.innerHTML = '';
    if (res && Array.isArray(res.timeslots) && res.timeslots.length > 0) {
      res.timeslots.forEach(ts => {
        const id = ts.id;
        const label = ts.label || ts.time || String(ts.id);
        const radioId = `timeslot-${id}`;

        const radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = 'timeslot';
        radio.value = id;
        radio.id = radioId;

        const labelEl = document.createElement('label');
        labelEl.htmlFor = radioId;
        labelEl.textContent = label;

        const wrapper = document.createElement('div');
        wrapper.classList.add('timeslot-option');
        wrapper.appendChild(radio);
        wrapper.appendChild(labelEl);

        container.appendChild(wrapper);
      });
      setSubmitEnabled(form, false);
    } else {
      // No timeslots; disable submit
      setSubmitEnabled(form, false);
    }
  }
}

function collectBookingState(form) {
  return {
    supplierId: form.dataset.supplierId,
    activityIds: getActivityIds(form),
    date: getSelectedDate(form),
    timeslot: getSelectedTimeslot(form),
    guests: getGuestCounts(form),
    upgrades: Array.from(qsa('[data-upgrade]:checked', form)).map(u => u.value),
    goldCard: qs('[name="goldcardNumber"]', form)?.value?.trim() || null,
  };
}

async function submitReservation(form) {
  const state = collectBookingState(form);
  if (!state.date) { alert("Please select a date"); return; }
  if (!state.timeslot) { alert("Please select a time slot"); return; }
  if (Object.values(state.guests).every(qty => qty === 0)) {
    alert("Please select at least one guest"); return;
  }
  try {
    const res = await fetchData('reservation.php', {
      method: 'POST',
      body: state,
    });
    if (res?.status === 'ok') {
      alert("Reservation confirmed!");
    } else {
      alert("Reservation failed: " + (res?.error || 'Unknown error'));
    }
  } catch (err) {
    console.error("Reservation error", err);
    alert("An error occurred while making the reservation.");
  }
}

export function initBookingForm(formSelector) {
  const form = qs(formSelector);
  if (!form) return;

  console.log('Booking form initialized:', form);

  // Initially disable submit until a timeslot is chosen
  setSubmitEnabled(form, false);

  // Refresh timeslots when guest counts change
  form.addEventListener('change', (e) => {
    if (e.target.classList.contains('guestCountSelect')) {
      refreshTimeslots(form);
    }
  });

  // Refresh timeslots when date changes (calendar.js sets value)
  const dateInput = qs('.dateTextInput', form);
  if (dateInput) {
    dateInput.addEventListener('change', () => refreshTimeslots(form));
  }

  // Enable submit when a timeslot is selected
  form.addEventListener('change', (e) => {
    if (e.target.name === 'timeslot') {
      setSubmitEnabled(form, !!e.target.value);
    }
  });

  // Intercept submit for validation and reservation submission
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    await submitReservation(form);
  });
}