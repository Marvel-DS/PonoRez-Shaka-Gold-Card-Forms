# Development Roadmap

This roadmap outlines the sequential task list for modernizing the booking application. Tasks are ordered within each sprint so the app remains testable as functionality accumulates.

---

## Sprint 0 – Foundations _(Week 1)_
1. Initialize Git repository (if not already tracked) and create a project-specific `.gitignore` covering Node/Tailwind artefacts (`node_modules`, `assets/dist`), cache directories, and macOS files.
2. Remove obsolete runtime code from `_oldApp/` (retain for reference only) and verify `docs/project-structure.md` matches the repository.
3. Configure PHP autoloading via Composer (`composer dump-autoload`); ensure `PonoRez\SGCForms\` maps to `controller/`.
4. Install Node dependencies; configure Tailwind/Vite build scripts in `package.json`.
5. Implement `controller/Setup.php` to load `.env`, configs, autoloaders, and logging defaults.
6. Update all entry points (`index.php`, `public/index.php`, each file in `api/`) to require `Setup.php`.
7. Render a minimal page using `partials/layout/form-basic.php` and `partials/form/component-button.php` to verify bootstrap + asset pipeline.
8. Run smoke tests: ensure `npm run build` (or equivalent) and `composer dump-autoload` succeed.

## Sprint 1 – Core Data Services _(Weeks 2–3)_
1. Build `controller/Services/SoapClientBuilder.php` with authentication, WSDL selection, and logging hooks.
2. Implement `controller/Cache` layer (`FileCache`, `CacheInterface`, `CacheKeyGenerator`, `NullCache`).
3. Create DTOs needed for guest types and availability (`GuestType`, `GuestTypeCollection`, `AvailabilityDay`, `AvailabilityCalendar`, `Timeslot`).
4. Implement `GuestTypeService` using SOAP + cache, returning DTOs.
5. Implement `AvailabilityService` (dates + timeslots) using DTOs.
6. Add PHPUnit tests for both services (`tests/phpunit/Services/GuestTypesTest.php`, `AvailabilityTest.php`) using data from `tests/phpunit/Responses`.
7. Build `Support/RequestValidator.php`, `ResponseFormatter.php`, `ErrorManager.php`, and `LogManager.php` utilities.
8. Wire `api/get-guest-types.php` and `api/get-availability.php` to new services; verify JSON responses locally.
9. Update `healthcheck.php` to call both services.

## Sprint 2 – Transportation, Upgrades, Checkout _(Week 4)_
1. Extend DTO set (`TransportationRoute`, `TransportationSet`, `Upgrade`, `UpgradeCollection`, `CheckoutInitRequest`, `CheckoutInitResponse`).
2. Implement `TransportationService` with supplier/activity overrides.
3. Implement `UpgradeService` for upgrades metadata.
4. Implement `CheckoutInitService` for checkout handoff and add PHPUnit coverage (`ReservationTest.php`).
5. Wire `api/get-transportation.php`, `api/get-upgrades.php`, and `api/init-checkout.php` to their services.
6. Update Playwright fixtures (if needed) to include transport/upgrade scenarios.
7. Smoke-test all API endpoints with `curl`/Postman.

## Sprint 3 – Form Rendering & Data Hydration _(Week 5)_
1. Render supplier/activity branding via `partials/layout/branding.php`; confirm Tailwind variables update.
2. Output full markup for form partials (`component-guest-types`, `component-calendar`, `component-upgrades`, `component-transportation`, `component-timeslot`).
3. Inject bootstrap JSON (`window.__SGC_BOOTSTRAP__`) containing supplier/activity state and API routes.
4. Build base Tailwind styles (`assets/css/main.css`, component CSS) and run `npm run build` to ensure pipelines work.
5. Manual check: load page with sample supplier config; confirm sections render (static) without JS.

## Sprint 4 – Client Modules _(Weeks 6–7)_
1. Initialize global store (`assets/js/core/store.js`) and events bus.
2. Implement `modules/guest-types.js` (listen to selectors, call guest types API, update store).
3. Implement `modules/availability.js` + `modules/calendar.js`; fetch availability and populate timeslots.
4. Implement `modules/upgrades.js` and `modules/transportation.js`; ensure store sync and validation.
5. Implement `modules/pricing.js` and `modules/alerts.js`; update subtotal UI and errors.
6. Implement `modules/booking.js` with `overlay/checkout-overlay.js` integration.
7. Wire `main.js` to initialize all modules; test end-to-end booking flow manually.

## Sprint 5 – Supplier Customization & Content _(Week 8)_
1. Validate supplier configs with `scripts/sync-suppliers.php` and `config/suppliers.schema.json`.
2. Verify branding fallbacks (`branding.config`) when supplier logos/colors missing.
3. Test transportation overrides in multiple supplier configs; ensure default route logic works.
4. Confirm image fallback paths via `assets/images/*-placeholder.*`.
5. Document configuration patterns in `docs/integration-notes.md`.

## Sprint 6 – Testing, Hardening & Release Prep _(Week 9)_
1. Expand PHPUnit coverage for controller endpoints (`tests/phpunit/Controllers`).
2. Author Playwright specs for booking flow, calendar accessibility, transportation (existing files) and ensure they pass.
3. Exercise cache utilities (`scripts/purge-cache.php`); confirm TTL and cache directory behaviour.
4. Review performance/logging: tune `logging.config`, confirm `LogManager` outputs per channel.
5. Update developer docs (`docs/project-structure.md`, `docs/development-roadmap.md`, `docs/ui-specs.md`) with final notes.
6. Prepare release checklist (build commands, deployment steps, healthcheck verification).

---

Progressive testing strategy:
- After each sprint, run existing PHPUnit suites and manual smoke tests on updated endpoints/UI.
- Add Playwright steps starting in Sprint 4 once front-end flow is interactive.
- Use `healthcheck.php` and log outputs to verify integrations before promoting to staging.
