import { test, expect } from '@playwright/test';

const BASE = 'http://localhost:8080';

test('debug API login', async ({ request }) => {
  const resp = await request.post(`${BASE}/api/v1/auth/login`, {
    data: { username: 'admin', password: 'Jember3509' },
  });
  console.log('Status:', resp.status());
  console.log('Headers:', JSON.stringify(resp.headers(), null, 2));
  const text = await resp.text();
  console.log('Body:', text);
  expect(resp.status()).toBe(200);
});
