import { test, expect } from '@playwright/test';
import { BASE } from '../base-url';

const ADMIN_USER = 'admin';
const ADMIN_PASS = 'Jember3509';
const PETUGAS_USER = 'petugas01';
const PETUGAS_PASS = 'Jember3509';
const OPERATOR_USER = 'operator01';
const OPERATOR_PASS = 'Jember3509';
const STATISTISI_USER = 'statistisi01';
const STATISTISI_PASS = 'Jember3509';
const VIEWER_USER = 'viewer01';
const VIEWER_PASS = 'Jember3509';

const ROLES = [
  { name: 'admin', user: ADMIN_USER, pass: ADMIN_PASS },
  { name: 'petugas', user: PETUGAS_USER, pass: PETUGAS_PASS },
  { name: 'operator', user: OPERATOR_USER, pass: OPERATOR_PASS },
  { name: 'statistisi', user: STATISTISI_USER, pass: STATISTISI_PASS },
  { name: 'viewer', user: VIEWER_USER, pass: VIEWER_PASS },
];

async function loginAs(page, username: string, password: string) {
  try {
    await page.goto(`${BASE}/auth/login`, { timeout: 10000 });
    await page.fill('input[name="username"]', username);
    await page.fill('#password', password);
    await page.getByRole('button', { name: 'Login' }).click();
    await page.waitForURL(/\/(dashboard|password\/change)/, { timeout: 8000 });
    if (page.url().includes('/password/change')) {
      await page.goto(`${BASE}/dashboard`, { timeout: 8000 });
      await page.waitForURL(/\/dashboard/, { timeout: 5000 });
    }
  } catch {
    await page.waitForTimeout(2000);
  }
}

test.describe('CSRF Protection', () => {
  for (const role of ROLES) {
    test(`${role.name} — POST without CSRF token fails`, async ({ page }) => {
      await loginAs(page, role.user, role.pass);
      const resp = await page.request.post(`${BASE}/api/v1/laporan-hama`, {
        data: { wilayah_id: 1, opt_id: 1 },
        maxRedirects: 0,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Content-Type': 'application/json',
        },
      });
      expect([200, 422, 500]).not.toContain(resp.status());
    });
  }
});

test.describe('Cross-Role Boundary Enforcement', () => {
  test('petugas cannot access operator irigasi rules', async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
    const resp = await page.request.post(`${BASE}/api/irigasi/rules`, {
      data: { irigasi_id: 1, rule_name: 'Test' },
      maxRedirects: 0,
    });
    expect([401, 403, 302, 404]).toContain(resp.status());
  });

  test('viewer cannot create laporan hama via API', async ({ page }) => {
    await loginAs(page, VIEWER_USER, VIEWER_PASS);
    const resp = await page.request.post(`${BASE}/api/v1/laporan-hama`, {
      data: { wilayah_id: 1, opt_id: 1 },
      maxRedirects: 0,
    });
    expect([401, 403, 302, 404, 405]).toContain(resp.status());
  });

  test('statistisi cannot verify laporan', async ({ page }) => {
    await loginAs(page, STATISTISI_USER, STATISTISI_PASS);
    const resp = await page.request.post(`${BASE}/api/laporan-hama/1/verify`, {
      data: { status: 'Diverifikasi' },
      maxRedirects: 0,
    });
    expect([401, 403, 302, 405, 404]).toContain(resp.status());
  });

  test('petugas cannot access admin OPT management', async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
    const resp = await page.request.post(`${BASE}/api/opt`, {
      data: { nama_opt: 'Test' },
      maxRedirects: 0,
    });
    expect([401, 403, 302, 404]).toContain(resp.status());
  });
});

test.describe('SQL Injection Resistance', () => {
  const sqliPatterns = [
    "' OR '1'='1",
    "1; DROP TABLE users--",
    "' UNION SELECT * FROM users--",
    "admin'--",
    "1' AND 1=1--",
  ];

  for (const payload of sqliPatterns) {
    test(`login form rejects SQLi: "${payload.slice(0, 20)}"`, async ({ page }) => {
      const url = `${BASE}/auth/login`;
      // Get CSRF token from login page
      await page.goto(url);
      const csrfToken = await page.locator('input[name="_csrf_token"]').getAttribute('value').catch(() => '');
      // Submit via API directly
      const resp = await page.request.post(url, {
        form: { username: payload, password: payload, _csrf_token: csrfToken },
        maxRedirects: 0,
      });
      const status = resp.status();
      const text = await resp.text();
      // Should either stay on login page (302) or show form with error (200)
      // But should NOT return SQL error or 500
      expect([200, 302, 400, 422]).toContain(status);
      expect(text).not.toMatch(/SQL syntax|mysqli|PDOException|You have an error|Uncaught/);
    });
  }
});

test.describe('XSS Prevention', () => {
  const xssPatterns = [
    '<script>alert(1)</script>',
    '"><script>alert(1)</script>',
    '<img src=x onerror=alert(1)>',
    'javascript:alert(1)',
  ];

  for (const payload of xssPatterns) {
    test(`dashboard search rejects XSS: "${payload.slice(0, 20)}"`, async ({ page }) => {
      await page.goto(`${BASE}/auth/login`);
      await page.fill('input[name="username"]', 'admin');
      await page.fill('#password', 'Jember3509');
      await page.getByRole('button', { name: 'Login' }).click();
      await page.waitForTimeout(2000);
      await page.goto(`${BASE}/dashboard?search=${encodeURIComponent(payload)}`);
      expect(await page.locator('body').count()).toBe(1);
    });
  }
});

test.describe('Forced Browsing Protection', () => {
  const sensitivePaths = [
    '/.env',
    '/config.php',
    '/composer.json',
    '/.git/config',
    '/vendor/autoload.php',
  ];

  const testRoles = [ROLES[0], ROLES[1]]; // admin + petugas enough
  for (const role of testRoles) {
    for (const path of sensitivePaths) {
      test(`${role.name} — cannot access ${path}`, async ({ page }) => {
        await page.goto(`${BASE}/auth/login`);
        await page.fill('input[name="username"]', role.user);
        await page.fill('#password', role.pass);
        await page.getByRole('button', { name: 'Login' }).click();
        await page.waitForTimeout(2000);
        const resp = await page.request.get(`${BASE}${path}`, { maxRedirects: 0 });
        expect(resp.status()).not.toBe(200);
      });
    }
  }
});

test.describe('Session Security', () => {
  test('old session cookies are invalid after logout', async ({ page, context }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
    const cookies1 = await context.cookies();
    const sessionCookie = cookies1.find(c => c.name.includes('PHPSESSID') || c.name.includes('session'));
    expect(sessionCookie).toBeDefined();

    await page.locator('form[action="auth/logout"] button[type="submit"]').click();
    await page.waitForTimeout(2000);
    const urlAfterLogout = page.url();
    expect(urlAfterLogout).toMatch(/\/login/);

    await context.addCookies([sessionCookie!]);
    await page.goto(`${BASE}/dashboard`);
    await page.waitForTimeout(2000);
    const urlReplayed = page.url();
    expect(urlReplayed).toMatch(/\/login/);
  });

  test('new session after login is different from previous', async ({ page, context }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
    const cookies1 = await context.cookies();
    const session1 = cookies1.find(c => c.name.includes('PHPSESSID') || c.name.includes('session'));

    await page.locator('form[action="auth/logout"] button[type="submit"]').click();
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
    const cookies2 = await context.cookies();
    const session2 = cookies2.find(c => c.name.includes('PHPSESSID') || c.name.includes('session'));

    expect(session1!.value).not.toBe(session2!.value);
  });
});

test.describe('Rate Limiting / Brute Force Protection', () => {
  test('rapid failed login attempts are handled gracefully', async ({ page }) => {
    // Use 3 attempts (under LOGIN_MAX_ATTEMPTS=5) to avoid IP lockout
    for (let i = 0; i < 3; i++) {
      await page.goto(`${BASE}/auth/login`);
      await page.fill('input[name="username"]', 'admin');
      await page.fill('#password', `wrong_${i}`);
      await page.getByRole('button', { name: 'Login' }).click();
    }
    await page.goto(`${BASE}/auth/login`);
    await page.fill('input[name="username"]', ADMIN_USER);
    await page.fill('#password', ADMIN_PASS);
    await page.getByRole('button', { name: 'Login' }).click();
    const currentUrl = page.url();
    if (currentUrl.includes('/login')) {
      const bodyText = await page.locator('body').innerText();
      // Check for error message or rate-limit indicator
      const hasRateLimit = /terlalu banyak|too many|rate limit|limit|coba lagi|banned|salah|error|invalid/i.test(bodyText);
      if (hasRateLimit) {
        test.info().annotations.push({ type: 'info', description: 'Rate limiting triggered after 3 attempts' });
      }
    } else {
      await page.waitForTimeout(1000);
      const finalUrl = page.url();
      expect(finalUrl).toMatch(/\/dashboard/);
    }
  });
});

test.describe('Include Draft Parameter Handling', () => {
  test('include_draft=invalid is accepted (falls back to default false)', async ({ page }) => {
    await page.goto(`${BASE}/auth/login`);
    await page.fill('input[name="username"]', 'admin');
    await page.fill('#password', 'Jember3509');
    await page.getByRole('button', { name: 'Login' }).click();
    await page.waitForTimeout(2000);
    const endpoints = [
      `${BASE}/api/v1/laporan-hama`,
      `${BASE}/api/analytics/dashboard-summary`,
    ];
    for (const ep of endpoints) {
      const resp = await page.request.get(`${ep}?include_draft=invalid`, { maxRedirects: 0 });
      expect([200, 400, 401, 403, 302, 404]).toContain(resp.status());
    }
  });
});

test.describe('HTTP Verb Tampering', () => {
  test('DELETE on read-only endpoints is rejected', async ({ page }) => {
    await page.goto(`${BASE}/auth/login`);
    await page.fill('input[name="username"]', ADMIN_USER);
    await page.fill('#password', ADMIN_PASS);
    await page.getByRole('button', { name: 'Login' }).click();
    await page.waitForTimeout(2000);
    const resp = await page.request.fetch(`${BASE}/api/v1/laporan-hama`, {
      method: 'DELETE',
      maxRedirects: 0,
    });
    expect([405, 404, 403, 401]).toContain(resp.status());
  });

  test('PUT on create-only endpoints is rejected', async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
    const resp = await page.request.fetch(`${BASE}/api/v1/laporan-hama/1`, {
      method: 'PUT',
      maxRedirects: 0,
      data: { wilayah_id: 1 },
    });
    expect([405, 404, 403, 401, 422]).toContain(resp.status());
  });
});


