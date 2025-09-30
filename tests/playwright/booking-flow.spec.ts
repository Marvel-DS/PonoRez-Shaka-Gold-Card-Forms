import { expect, test } from '@playwright/test';
import {
  availabilityResponse,
  checkoutInitResponse,
  guestTypesResponse,
  mockApiRoutes,
  transportationResponse,
  upgradesResponse,
} from './fixtures/api';

test.describe('Booking flow fixtures', () => {
  test('cover end-to-end data contract', async ({ page }) => {
    await mockApiRoutes(page);

    expect(guestTypesResponse.guestTypes).toHaveLength(3);
    const adult = guestTypesResponse.guestTypes.find((type) => type.id === '345');
    expect(adult?.price).toBeGreaterThan(0);

    expect(availabilityResponse.timeslots.length).toBeGreaterThan(0);
    expect(availabilityResponse.timeslots[0]).toHaveProperty('id');

    expect(transportationResponse.transportation.routes.some((route) => route.id === 'route-shuttle')).toBeTruthy();
    expect(upgradesResponse.upgrades.some((upgrade) => upgrade.id === 'upgrade-photos')).toBeTruthy();

    expect(checkoutInitResponse.checkout.totalPrice).toBeGreaterThan(0);
    expect(checkoutInitResponse.checkout.supplierPaymentAmount).toBeLessThanOrEqual(
      checkoutInitResponse.checkout.totalPrice,
    );
    expect(checkoutInitResponse.checkout.reservationId).toBe('RES-123');
  });
});
