import type { Page, Route } from '@playwright/test';

export type ApiFixture = {
  url: RegExp | string;
  method?: 'GET' | 'POST';
  body: unknown;
  status?: number;
};

export const guestTypesResponse = {
  status: 'ok',
  guestTypes: [
    {
      id: '345',
      label: 'Adult from SOAP',
      description: 'Ages 16+',
      price: 150.55,
      min: 1,
      max: 10,
    },
    {
      id: '456',
      label: 'Child from SOAP',
      description: 'Ages 2-16',
      price: 50,
      min: 0,
      max: 10,
    },
    {
      id: '789',
      label: 'Infant',
      description: null,
      price: 0,
      min: 0,
      max: 10,
    },
  ],
};

export const availabilityResponse = {
  status: 'ok',
  calendar: [
    { date: '2024-01-01', status: 'available' },
    { date: '2024-01-02', status: 'soldout' },
    { date: '2024-01-03', status: 'available' },
  ],
  timeslots: [
    { id: 'timeslot-101', label: '8:00 AM', available: 12 },
    { id: 'timeslot-202', label: '1:00 PM', available: 8 },
  ],
};

export const transportationResponse = {
  status: 'ok',
  transportation: {
    mandatory: true,
    defaultRouteId: 'route-shuttle',
    routes: [
      {
        id: 'route-shuttle',
        label: 'Waikiki Shuttle Updated',
        description: 'Round trip transportation from Waikiki hotels.',
        price: 38,
        capacity: 18,
      },
      {
        id: 'route-self',
        label: 'Self Drive',
        description: 'Meet us at the harbor.',
        price: 0,
        capacity: 50,
      },
      {
        id: 'route-airport',
        label: 'Airport Transfer',
        description: 'Pickup at HNL airport.',
        price: 50,
        capacity: 8,
      },
    ],
  },
};

export const upgradesResponse = {
  status: 'ok',
  upgrades: [
    {
      id: 'upgrade-photos',
      label: 'Photo Package Updated',
      description: 'Digital photo bundle',
      price: 59.25,
      maxQuantity: 1,
    },
    {
      id: 'upgrade-snacks',
      label: 'Snack Pack',
      description: 'Light refreshments',
      price: 15,
      maxQuantity: 5,
    },
  ],
};

export const checkoutInitResponse = {
  status: 'ok',
  checkout: {
    totalPrice: 199.5,
    supplierPaymentAmount: 75.25,
    reservationId: 'RES-123',
    calculation: {
      out_price: 199.5,
      out_requiredSupplierPayment: 75.25,
    },
    reservation: {
      id: 'RES-123',
    },
  },
};

export const apiFixtures: ApiFixture[] = [
  {
    url: /\/api\/get-guest-types/,
    method: 'GET',
    body: guestTypesResponse,
  },
  {
    url: /\/api\/get-availability/,
    method: 'GET',
    body: availabilityResponse,
  },
  {
    url: /\/api\/get-transportation/,
    method: 'GET',
    body: transportationResponse,
  },
  {
    url: /\/api\/get-upgrades/,
    method: 'GET',
    body: upgradesResponse,
  },
  {
    url: /\/api\/init-checkout/,
    method: 'POST',
    body: checkoutInitResponse,
  },
];

export async function mockApiRoutes(page: Page, overrides: Partial<Record<string, ApiFixture>> = {}): Promise<void> {
  const entries = apiFixtures.map((fixture) => {
    const key = fixture.url.toString();
    const override = overrides[key];
    return override ? { ...fixture, ...override } : fixture;
  });

  await Promise.all(
    entries.map(async (fixture) => {
      await page.route(fixture.url, async (route: Route) => {
        const request = route.request();
        const isMethodMatch = !fixture.method || request.method() === fixture.method;
        if (!isMethodMatch) {
          await route.fallback();
          return;
        }

        await route.fulfill({
          status: fixture.status ?? 200,
          contentType: 'application/json',
          body: JSON.stringify(fixture.body),
        });
      });
    })
  );
}

export default {
  guestTypesResponse,
  availabilityResponse,
  transportationResponse,
  upgradesResponse,
  checkoutInitResponse,
};
