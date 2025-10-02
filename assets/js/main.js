import { initAlerts } from './modules/alerts.js';
import { initGuestTypes } from './modules/guest-types.js';
import { initCalendar } from './modules/calendar.js';
import { initAvailability } from './modules/availability.js';
import { initTransportation } from './modules/transportation.js';
import { initUpgrades } from './modules/upgrades.js';
import { initPricing } from './modules/pricing.js';
import { initBooking } from './modules/booking.js';

function removeAvailabilityMetadataDebug() {
    document.querySelectorAll('[data-availability-metadata]').forEach((element) => {
        element.remove();
    });
}

function bootstrap() {
    initAlerts();
    initGuestTypes();
    initCalendar();
    initAvailability();
    initTransportation();
    initUpgrades();
    initPricing();
    initBooking();
    removeAvailabilityMetadataDebug();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
} else {
    bootstrap();
}
