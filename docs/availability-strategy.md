# Availability & Checkout Strategy

This document outlines how the modern SGC Forms application will replicate the legacy Ponorez “external” booking behavior while fitting our architecture (PHP REST API + modular JS front end) and following Ponorez SOAP/REST contracts.

---

## 1. Legacy Workflow Summary

Everything we observed in the HTML samples under `resources/existing static forms examples - legacy/` and the shipped helper scripts (`resources/bookingsupport-1.js`, `resources/calendar_js.jsp`, `resources/functions.js`) fits the following pattern:

| Stage | What Happens | Code Reference |
|-------|---------------|----------------|
| Script bootstrap | Page loads jQuery, `calendar_js.jsp`, `external/functions.js`, `external/bookingsupport-1.js`. Each form constructs a `bookingFormContext_*` object describing guest types, activity IDs, selectors, etc. | Static HTML samples (`BDC-DeluxeAMNapaliSnorkel.html`) |
| Calendar request | `PRBookingSupport.showGuestsDependentAvailabilityCalendar()` delegates to `reservationcalendar_createDatepickerPanel()` (see `resources/calendar_js.jsp`). That script calls `companyservlet?action=COMMON_AVAILABILITYCHECKJSON` (JSONP). | `resources/calendar_js.jsp`, lines ~2590–2730 |
| Month response | Ponorez returns JSONP (callback + object) containing `yearmonth_<Y>_<M>` and `yearmonth_<Y>_<M>_ex`. The `_ex` block lists activity IDs (`aids`) that are available for each date. | Example response captured from production/staging logs |
| Timeslot filtering | `_knownAvailabilityInfo()` / `_applySelectableActivityKnownAvailabilityStatus()` (in `bookingsupport-1.js`, lines ~1180–1270) filter the “Time” `<select>` options so only entries whose activity ID appears in the `aids` array remain visible. No extra SOAP call is made for departures. | `resources/bookingsupport-1.js` |
| Checkout redirect | `reservation()` / `reservation_init()` in `resources/functions.js` build Ponorez’ external checkout URL. Helpers (`addGuests`, `addUpgrades`, `setgoldcard`, `settransportationroute`, etc.) append the rest of the query string. `availability_iframe()` eventually navigates the browser to `externalservlet?action=EXTERNALPURCHASEPAGE…`. | `resources/functions.js`, lines ~20–760 |

Ponorez’ SOAP layer (`resources/initial app concept - legacy/controller/ApiService.php`) is only used by back-office or CLI scripts. Notably, there is **no** `getActivityTimeslots` method in the WSDL; static forms obtain departure availability entirely from the JSONP `COMMON_AVAILABILITYCHECKJSON` response.

---

## 2. Modernized Flow (Our Application)

We keep the same logical steps, but expose them through our architecture.

### 2.1 Calendar Availability (Month View)

**Front end expectation**

1. On initial load, fetch availability for the current month, but show the message `Select date to see available departure/start times` in the timeslot panel.
2. When the user changes guest counts, re-fetch the month (reflecting the new seat requirements) and redraw the calendar. Leave the timeslot panel untouched until a date is selected.

**Backend call** (`AvailabilityService::fetchCalendar()` → `api/get-availability.php`)

```http
GET /api/get-availability.php?supplier=blue-dolphin-charters&activity=deluxe-am-napali-snorkel&date=2025-11-01&month=2025-11&guestCounts=%7B%22214%22%3A2%7D
Accept: application/json
```

Our PHP service then calls Ponorez:

```http
GET https://ponorez.online/reservation/companyservlet
    ?action=COMMON_AVAILABILITYCHECKJSON
    &callback=jQuery19105491988469935201_1759244346916
    &wantExtendedResults=true
    &activityid=5263|5280|639|5281
    &agencyid=0
    &blocksonly=false
    &year_months=2025_11
    &checkAutoAttached=true
    &webbooking=true
    &hawaiifunbooking=false
    &agencybooking=false
    &minavailability={"guests":{"214":2},"upgrades":{}}
    &_=1759244346917
```

Response (JSONP):

```
jQuery19105491988469935201_1759244346916({
  "yearmonth_2025_11": {
    "d1": "1",
    "d2": "1",
    …
  },
  "yearmonth_2025_11_ex": {
    "d1": { "aids": [5280, 639] },
    "d6": { "aids": [5280, 639] },
    "d7": { "aids": [5280] },
    "d10": { "aids": [5280, 639] },
    …
  }
});
```

Our service strips the JSONP wrapper, decodes both objects, and returns:

```json
{
  "calendar": [
    { "date": "2025-11-01", "status": "available" },
    { "date": "2025-11-02", "status": "available" },
    { "date": "2025-11-06", "status": "available" },
    …
  ],
  "metadata": {
    "extended": {
      "2025-11-01": [5280, 639],
      "2025-11-06": [5280, 639],
      "2025-11-07": [5280],
      …
    },
    "requestedSeats": 2,
    "selectedDateStatus": "available",
    "timeslotStatus": "unavailable",
    "firstAvailableDate": "2025-11-01",
    "certificateVerification": "verified"
  },
  "timeslots": []
}
```

### 2.2 Timeslot List on Date Selection

When the user picks a date:

* `assets/js/modules/availability.js` receives the `extended` map and the selected date.
* We filter the configured `activityIds` against `metadata.extended[date]`. Only those that remain are shown.

Example (config snippet):

```json
"activityIds": [639, 5263, 5280, 5281],
"departureLabels": {
  "639": "8:00 AM Check In",
  "5263": "7:00 AM Check In",
  "5280": "7:00 AM Check In",
  "5281": "8:00 AM Check In"
}
```

If `metadata.extended["2025-11-06"] = [5280, 639]`, we render only those two departures. No SOAP call is necessary. If a deployment absolutely requires a second check, we can optionally call `checkActivityAvailability` for the specific activity/date combo, but the JSONP already tells us which IDs are open.

### 2.3 Checkout Handoff

Once the user selects a departure and fills guest counts/upgrades, we mimic `resources/functions.js`:

1. **Initialization** – `reservation_init()` adds `&activityid=<selected activity>`, `&date=MM/DD/YYYY`, plus the current `referer` URL.
2. **Guests** – `addGuests(id, count)` appends `&guestcount_<id>=<count>` for each non-zero count.
3. **Upgrades** – `addUpgrades(upgradeId, qty)` appends `&upgradecount_<upgradeId>=<qty>`.
4. **Optional flags** – `sethotel`, `setroom`, `settransportationroute`, `setgoldcard`, `setbuygoldcard`, Google Tag Manager session IDs, etc.
5. **Redirect URL** (final):

   ```
   https://ponorez.online/reservation/externalservlet?
       action=EXTERNALPURCHASEPAGE
       &mode=reservation
       &activityid=5280
       &date=11%2F06%2F2025
       &guestcount_214=2
       &upgradecount_upgrade-photos=1
       &policy=1
       &referer=...
       &gtagtagid=...      // optional
       &transportationrouteid=route-shuttle
       …
   ```

Ponorez’ hosted checkout then collects contact, payment, etc., and finalizes the reservation.

### 2.4 SOAP & REST Touch Points

| Purpose | Endpoint / Method | Notes |
|---------|-------------------|-------|
| Calendar availability | `companyservlet?action=COMMON_AVAILABILITYCHECKJSON` (JSONP) | Primary source of date + activity availability. No SOAP alternative for departures. |
| Guest type details | `getActivityGuestTypes` (SOAP) | Returns labels/pricing per guest type. Used to display price info, update totals, or compute min availability payloads. |
| Seat probing (optional) | `checkActivityAvailability` (SOAP) | Useful for verifying seat counts if the JSONP data is inconclusive. |
| Reservation creation | `externalservlet?action=EXTERNALPURCHASEPAGE…` (HTTP redirect) | Hosted checkout handles the final booking. |

`getActivityTimeslots` does not exist in Ponorez’ WSDL; we rely on JSONP `_ex` data + config mapping to show departures.

### 2.5 Front-End Responsibilities

* `assets/js/modules/calendar.js` – still handles month navigation, sets `state.visibleMonth`.
* `assets/js/modules/availability.js`
  - Sends guest counts, month, and activity IDs to `/api/get-availability.php`.
  - Keeps the timeslot panel on the “Select date…” message until a date is chosen.
  - When a date is selected, uses `metadata.extended` to filter departures.
* `assets/js/modules/booking.js`
  - Reads the selected activity ID, guest counts, upgrades, and optional transport data.
  - Builds Ponorez’ checkout URL (mirroring `functions.js` helpers) and redirects.
  - Maintains the “Select date to see available departure/start times” prompt until both guest mix and date are set.

### 2.6 QA Checklist

1. **Calendar** – verify December 6 (known sold-out) shows as disabled; other days match the `_ex` output.
2. **Guest mix** – change guest counts and confirm the extended metadata updates accordingly.
3. **Timeslot list** – select a date with limited `aids` and ensure only those departures appear.
4. **Checkout URL** – capture the redirect URL and confirm it matches the legacy pattern.
5. **Ponorez handoff** – ensure the hosted page displays the same summary as the static form.

---

## 3. Appendix – Sample Interactions

### 3.1 Month Availability Request & Response

```http
GET https://ponorez.online/reservation/companyservlet?
  action=COMMON_AVAILABILITYCHECKJSON
  &callback=jQuery19106598186252513221_1759244347888
  &wantExtendedResults=true
  &activityid=5263%7C5280%7C639%7C5281
  &agencyid=0
  &blocksonly=false
  &year_months=2025_11
  &checkAutoAttached=true
  &webbooking=true
  &hawaiifunbooking=false
  &agencybooking=false
  &minavailability=%7B%22guests%22%3A%7B%22214%22%3A2%7D%2C%22upgrades%22%3A%7B%7D%7D
  &_=1759244347891
```

Response:

```
jQuery19106598186252513221_1759244347888({
  "yearmonth_2025_11": {
    "d1": "1",
    …
  },
  "yearmonth_2025_11_ex": {
    "d30": { "aids": [5280, 639] },
    "d7": { "aids": [5280] },
    …
  }
});
```

### 3.2 Checkout Redirect

```
https://ponorez.online/reservation/externalservlet?
  action=EXTERNALPURCHASEPAGE
  &mode=reservation
  &activityid=5280
  &date=11%2F06%2F2025
  &guestcount_214=2
  &upgradecount_upgrade-photos=1
  &transportationpreselected=route-shuttle
  &policy=1
  &referer=https%3A%2F%2Fexample.com%2Fbooking
  &gtagtagid=G-XXXXXXX
  &gtagdebugmode=1
  … (additional flags as needed)
```

### 3.3 Code References

| Behavior | File / Lines |
|----------|--------------|
| Calendar JSONP call | `resources/calendar_js.jsp`, lines 2590–2721 |
| Extended availability cache | `resources/bookingsupport-1.js`, lines 1180–1520 |
| Checkout builder | `resources/functions.js`, lines 20–760 |
| Legacy availability REST fallback (`availability.php`) | `resources/initial app concept - legacy/api/availability.php` |
| SOAP wrappers for utilities | `resources/initial app concept - legacy/controller/ApiService.php` |

---

This approach mirrors the production legacy flow (JSONP availability + redirect checkout) while using our modular PHP/JS stack, ensuring we honor Ponorez’ contract and deliver the same behavior users expect. EOF
