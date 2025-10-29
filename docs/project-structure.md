# Project Architecture & File Reference

This document reflects the current repository layout and describes the role and relationships of every directory and significant file in the project.

---

## 1. Top-Level Overview

```
/ (project root)
├─ api/
├─ assets/
├─ config/
├─ controller/
├─ docs/
├─ partials/
├─ public/
├─ resources/
├─ scripts/
├─ suppliers/
├─ tests/
├─ _oldApp/              (legacy snapshot for reference)
├─ index.php
├─ package.json / package-lock.json
├─ tailwind.config.js
└─ composer.json
```

Additional root files:
- `Setup.php` lives in `controller/` and is required by entry points.

---

## 2. Root Files

| File | Purpose |
|------|---------|
| `index.php` | Public entry point when serving the app from project root (mirrors `public/index.php`). |
| `composer.json` | Declares PHP dependencies and PSR-4 namespaces (`PonoRez\SGCForms\`). |
| `package.json` / `package-lock.json` | Node tooling (Tailwind/Vite build, lint, tests). |
| `tailwind.config.js` | Tailwind theme tokens, purge paths, and plugin configuration. |

---

## 3. `api/`

PHP endpoints that respond with JSON. Each file loads `controller/Setup.php`, validates input, invokes a service, and returns a normalized payload.

| File | Description |
|------|-------------|
| `get-guest-types.php` | Retrieves guest type metadata and pricing via `Services/GuestTypeService`. |
| `get-availability.php` | Returns calendar availability and timeslots using `Services/AvailabilityService`. |
| `get-transportation.php` | Supplies transportation routes/details using `Services/TransportationService`. |
| `get-upgrades.php` | Provides upgrade options and capacity data via `Services/UpgradeService`. |
| `init-checkout.php` | Validates booking payload, creates checkout handoff through `Services/CheckoutInitService`, and returns overlay data. |
| `healthcheck.php` | Lightweight status check (configuration readability + SOAP heartbeat). |
| `index.php` | Convenience router exposing API usage/help. |

---

## 4. `assets/`

### 4.1 `assets/css/`

| File / Folder | Description |
|---------------|-------------|
| `tailwind.css` | Tailwind source stylesheet (`@tailwind base/components/utilities`). |
| `main.css` | Hand-authored overrides that complement utility classes. |
| `components/` | Component-level styles extracted for clarity. Files include: `alerts.css`, `calendar.css`, `guest-types.css`, `overlay.css`, `pricing.css`, `timeslots.css`, `transportation.css`, `upgrades.css`. |

`assets/dist/` is reserved for build outputs (currently empty in the repo).

### 4.2 `assets/images/`

Placeholder imagery for suppliers/activities when dedicated assets are missing (`activity-cover-placeholder.jpg`, `supplier-logo-placeholder.png`).

### 4.3 `assets/js/`

| Folder / File | Description |
|---------------|-------------|
| `main.js` | Front-end entry point; initializes modules once DOM is ready. |
| `core/api-client.js` | Fetch wrapper with consistent headers, error handling. |
| `core/config.js` | Merges server-injected config into the front-end runtime. |
| `core/events.js` | Lightweight pub/sub bus for inter-module communication. |
| `core/store.js` | Reactive store mirroring the form context (guest counts, timeslots, pricing, etc.). |
| `modules/alerts.js` | UI notifications for success/error states. |
| `modules/availability.js` | Calendar + timeslot orchestration tied to availability API. |
| `modules/booking.js` | Collects booking state, calls `init-checkout`, opens overlay. |
| `modules/calendar.js` | Calendar UI logic (month navigation, keyboard support). |
| `modules/guest-types.js` | Renders guest selectors and syncs counts with store + API. |
| `modules/pricing.js` | Maintains pricing breakdown (base + upgrades). |
| `modules/transportation.js` | Handles route selection, mandatory transport validation. |
| `modules/upgrades.js` | Upgrade selector UI, capacity enforcement. |
| `overlay/checkout-overlay.js` | Manages the checkout iframe overlay lifecycle. |
| `overlay/overlay-messages.js` | Message schema + postMessage handlers for the overlay. |
| `utility/dom.js` | DOM selection/manipulation helpers. |
| `utility/formating.js` | (typo intentionally retained) Currency/date formatting helpers. |
| `utility/validation.js` | Form validation utilities. |
| `utility/strings.js` | Copy helpers and label fallbacks. |

### 4.4 `assets/index.php`

Prevents directory listing when assets are served directly.

---

## 5. `config/`

| File | Description |
|------|-------------|
| `env.config` | Selects active environment (`production`, `staging`, etc.) and stores SOAP credentials + API roots. |
| `build.config` | Front-end build options (public path, asset hashes). |
| `branding.config` | Global brand defaults and named presets (fallback colors, fonts, logo path). |
| `logging.config` | Log channels, file destinations, verbosity. |
| `suppliers.schema.json` | JSON schema validating `supplier.config` and `<activity>.config` files. |
| `index.php` | Disables directory browsing. |

---

## 6. `controller/`

### 6.1 Root Files

| File | Description |
|------|-------------|
| `Setup.php` | Bootstraps environment (loads `.env`, registers autoloading, injects defaults). Required by all entry points. |
| `UtilityService.php` | Common helpers for path resolution, config loading, logging, URL helpers. |
| `index.php` | Prevents directory listing. |

### 6.2 `controller/Cache/`

Implements caching layer used by services.

| File | Description |
|------|-------------|
| `CacheInterface.php` | Contract for cache operations (`get`, `set`, `delete`, `purge`). |
| `CacheKeyGenerator.php` | Utility for consistent cache key composition (supplier/activity/date). |
| `FileCache.php` | Filesystem-backed cache implementation targeting `cache/` directories. |
| `NullCache.php` | No-op cache for environments where caching is disabled. |
| `index.php` | Directory guard. |

### 6.3 `controller/DTO/`

Normalized data structures returned by services.

| File | Description |
|------|-------------|
| `AvailabilityCalendar.php` | Aggregates `AvailabilityDay` entries. |
| `AvailabilityDay.php` | Represents availability state for a specific date. |
| `CheckoutInitRequest.php` | Encapsulates payload sent to Ponorez for checkout handoff. |
| `CheckoutInitResponse.php` | Normalized response returned to the front end (redirect URL, overlay token). |
| `GuestType.php` / `GuestTypeCollection.php` | Guest type metadata and collection wrapper. |
| `Timeslot.php` | Individual timeslot representation. |
| `TransportationRoute.php` / `TransportationSet.php` | Transportation route details and collection. |
| `Upgrade.php` / `UpgradeCollection.php` | Upgrade metadata and collection wrapper. |
| `index.php` | Directory guard. |

### 6.4 `controller/Services/`

Business logic wrappers around SOAP calls.

| File | Description |
|------|-------------|
| `AvailabilityService.php` | Fetches activity availability, applies calendar constraints, returns `AvailabilityCalendar`. |
| `CheckoutInitService.php` | Builds SOAP request to initiate Ponorez checkout and normalizes result. |
| `GuestTypeService.php` | Merges SOAP guest type data with supplier overrides and caches results. |
| `PricingService.php` | Calculates totals combining guest types and upgrades. |
| `SoapClientBuilder.php` | Produces configured `SoapClient` instances (auth, WSDL selection, logging hooks). |
| `TransportationService.php` | Retrieves/overrides transportation details per activity. |
| `UpgradeService.php` | Pulls upgrade information and availability. |
| `index.php` | Directory guard. |

### 6.5 `controller/Support/`

| File | Description |
|------|-------------|
| `ErrorManager.php` | Centralized exception handling/translation for API responses. |
| `LogManager.php` | Provides per-channel PSR-3 logger instances based on `logging.config`. |
| `RequestValidator.php` | Validates and sanitizes incoming request payloads. |
| `ResponseFormatter.php` | Standardizes JSON responses (`status`, `data`, `errors`). |
| `index.php` | Directory guard. |

---

## 7. `docs/`

Documentation set for developers and stakeholders.

| File | Description |
|------|-------------|
| `project-structure.md` | This document. |
| `architecture-overview.md` | High-level diagrams and data flow. |
| `architecture-decision-records.md` | ADR log (referencing major technical decisions). |
| `integration-notes.md` | SOAP quirks, environment setup tips. |
| `api-specs.md` | API contract details for each endpoint. |
| `ui-specs.md` | UI/UX guidelines for booking flow. |

---

## 8. `partials/`

Server-rendered PHP fragments composing the booking page(s).

### 8.1 `partials/form/`

| File | Description |
|------|-------------|
| `activity-description.php` | Displays marketing copy or description for the selected activity. |
| `activity-directions.php` | Travel directions or meeting point info. |
| `activity-info.php` | Consolidated summary block (highlights, duration, inclusions). |
| `activity-restrictions.php` | Displays age/health/weight restrictions. |
| `component-button.php` | Primary submission button with loading state markup. |
| `component-calendar.php` | Calendar widget container (ARIA attributes, hidden input). |
| `component-guest-types.php` | Guest selector UI; outputs data attributes for JS hydration. |
| `component-goldcard.php` / `component-goldcard-upsell.php` | Loyalty/signup modules. |
| `component-timeslot.php` | Timeslot radio button list placeholder. |
| `component-transportation.php` | Transportation selection UI (optional). |
| `component-upgrades.php` | Upgrade selector list with hidden inputs. |

### 8.2 `partials/layout/`

| File | Description |
|------|-------------|
| `branding.php` | Injects CSS variables and link tags based on supplier branding. |
| `form-advanced.php` / `form-template.php` | Two layout variants wrapping form components. |
| `section-footer.php` / `section-header.php` / `section-hero.php` | Surrounding UI elements (hero banner, navigation, footer). |

### 8.3 `partials/shared/`

| File | Description |
|------|-------------|
| `component-error.php` | Standard error alert for server-side validation. |
| `component-loading.php` | Loading spinner used across modules. |
| `component-overlay.php` | Non-JS fallback markup for checkout overlay. |

---

## 9. `public/`

Deployment-ready web root (mirrors root `index.php` for convenience).

| File / Folder | Description |
|---------------|-------------|
| `index.php` | Front controller routed by web server. |
| `healthcheck.php` | Public health endpoint. |
| `web.config` | IIS rewrite rules (maps pretty URLs to `index.php`). |
| `assets/` | Compiled assets ready for distribution (folders for `css`, `js`, `images`, `fonts`). Currently populated with placeholders. |

---

## 10. `resources/`

Reference material used when rebuilding behaviour.

| File / Folder | Description |
|------|-------------|
| `bookingsupport-1.js` | Legacy booking form logic from Ponorez external toolkit. |
| `functions.js` | Legacy helper/utilities (validation, analytics). |
| `calendar_js.jsp` | Original calendar implementation for parity reference. |
| `SOAP-Specification.docx` | Official Ponorez SOAP documentation. |
| `wireframes/` | Design references for the modern UI. |

---

## 11. `scripts/`

| File | Description |
|------|-------------|
| `build-assets.sh` | Runs Node build and copies output to `public/assets`. |
| `deploy.sh` | Illustrative deployment steps (composer install, asset build). |
| `purge-cache.php` | CLI utility to clear cache directories safely. |
| `sync-suppliers.php` | Validates supplier folders against `suppliers.schema.json`. |
| `index.php` | Directory guard. |

---

## 12. `suppliers/`

Structure already detailed in Section 3.10. Additional files present:

| File | Description |
|------|-------------|
| `index.php` (root + within each supplier) | Prevents directory listing. |
| `blue-dolphin-charters/` | Example supplier offering multiple activities (`Deluxe-AM-Napali-Snorkel.config`, `Deluxe-SS-Napali-Snorkel.config`, `Private-AM-Napali-Snorkel.config`) and images stored under `images/`. |
| `supplier-slug/` | Template/example supplier folder with sample config and imagery. |
| `cache/guest-types/*.json` | Optional fallback data (per-activity or `default.json`) used when SOAP lookups are unavailable. |
| `cache/activity-info/*.json` | Optional fallback activity metadata/timeslot labels when Ponorez data cannot be fetched. |

Transportation customization is handled inline within each `<activity-slug>.config` via the `transportation` block, as described earlier.

---

## 13. `tests/`

### 13.1 `tests/phpunit/`

| File / Folder | Description |
|------|-------------|
| `Services/GuestTypesTest.php` | Unit tests for `GuestTypeService`. |
| `Services/AvailabilityTest.php` | Unit tests for `AvailabilityService`. |
| `Services/ReservationTest.php` | Unit tests for `CheckoutInitService`/reservation workflows. |
| `Controllers/GetGuestTypesControllerTest.php` | Endpoint-level tests for the guest types API. |
| `Responses/` | JSON/XML payloads representing SOAP responses and supplier configs used across tests. |
| `index.php` | Directory guard. |

### 13.2 `tests/playwright/`

| File | Description |
|------|-------------|
| `booking-flow.spec.ts` | End-to-end booking flow (date → guests → checkout overlay). |
| `calendar.spec.ts` | Calendar behaviour and accessibility checks. |
| `transportation.spec.ts` | Transportation selection flows and mandatory route enforcement. 
| `index.php` | Directory guard. |

---

## 14. Supporting Files

| File / Folder | Description |
|------|-------------|
| `public/assets/css`, `public/assets/js`, etc. | Build output targets populated during deployment. |
| `scripts/index.php`, `config/index.php`, etc. | Directory guards to prevent listing. |
| `.DS_Store` files | macOS artifacts (can be deleted without impact). |

---

This documentation is kept in sync with the repository; update it whenever files are added, removed, or renamed to maintain an accurate architectural reference.
