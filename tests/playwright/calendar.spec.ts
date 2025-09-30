import { expect, test } from '@playwright/test';
import { availabilityResponse, mockApiRoutes } from './fixtures/api';

test.describe('Availability fixtures', () => {
  test('include multi-day calendar with sold-out status', async ({ page }) => {
    await mockApiRoutes(page);

    const days = availabilityResponse.calendar;
    expect(days.length).toBeGreaterThanOrEqual(3);

    const soldOutDay = days.find((day) => day.status === 'soldout');
    expect(soldOutDay?.date).toBe('2024-01-02');

    expect(availabilityResponse.timeslots.map((slot) => slot.id)).toEqual([
      'timeslot-101',
      'timeslot-202',
    ]);
  });
});
