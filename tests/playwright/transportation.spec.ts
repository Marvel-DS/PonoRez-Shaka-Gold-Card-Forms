import { expect, test } from '@playwright/test';
import { mockApiRoutes, transportationResponse } from './fixtures/api';

test.describe('Transportation fixtures', () => {
  test('exposes mandatory shuttle route and optional choices', async ({ page }) => {
    await mockApiRoutes(page);

    const { transportation } = transportationResponse;
    expect(transportation.mandatory).toBe(true);
    expect(transportation.defaultRouteId).toBe('route-shuttle');

    const routeIds = transportation.routes.map((route) => route.id);
    expect(routeIds).toEqual(['route-shuttle', 'route-self', 'route-airport']);

    const shuttle = transportation.routes.find((route) => route.id === 'route-shuttle');
    expect(shuttle).toBeDefined();
    expect(shuttle?.price).toBe(38);
    expect(shuttle?.capacity).toBe(18);

    const selfDrive = transportation.routes.find((route) => route.id === 'route-self');
    expect(selfDrive?.price).toBe(0);
  });
});
