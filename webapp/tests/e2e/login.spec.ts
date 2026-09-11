import { expect, test } from '@playwright/test';

test('login foundation is responsive and validates input', async ({ page }) => {
  await page.goto('/login');
  await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
  await page.getByRole('button', { name: /sign in to client portal/i }).click();
  await expect(page.getByText('Enter a valid work email address.')).toBeVisible();
  await expect(page.getByText('Enter your password.')).toBeVisible();
});

test('employee workspace changes the sign-in context', async ({ page }) => {
  await page.goto('/login');
  await page.getByRole('button', { name: 'Employee' }).click();
  await expect(page.getByRole('button', { name: /sign in to service console/i })).toBeVisible();
});

test('protected routes redirect guests to login', async ({ page }) => {
  await page.goto('/admin');
  await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
  await expect(page).toHaveURL(/\/login$/);
});

test('administrator can sign in, enter a protected route, and logout', async ({ page }) => {
  await page.route('**/api/v1/**', async (route) => {
    const url = route.request().url();
    if (url.endsWith('/health')) {
      await route.fulfill({ json: { success: true, data: { status: 'ok' }, error: null, timestamp: new Date().toISOString() } });
      return;
    }
    if (url.endsWith('/auth/login')) {
      await route.fulfill({
        json: {
          success: true,
          data: {
            access_token: 'test-access-token',
            token_type: 'Bearer',
            expires_in: 900,
            actor: {
              id: 1,
              type: 'EMPLOYEE',
              email: 'admin@onesalez.com',
              displayName: 'Siddharth Gupta',
              clientId: null,
              roles: ['SYSTEM_ADMIN'],
              permissions: [],
            },
          },
          error: null,
          timestamp: new Date().toISOString(),
        },
      });
      return;
    }
    if (url.endsWith('/auth/logout')) {
      await route.fulfill({ json: { success: true, data: { message: 'Logged out successfully.' }, error: null, timestamp: new Date().toISOString() } });
      return;
    }
    await route.fulfill({
      status: 401,
      json: { success: false, data: null, error: { code: 'UNAUTHENTICATED', message: 'No session.' }, timestamp: new Date().toISOString() },
    });
  });

  await page.goto('/login');
  await page.getByRole('button', { name: 'Employee' }).click();
  await page.getByPlaceholder('name@company.com').fill('admin@onesalez.com');
  await page.getByPlaceholder('Enter your password').fill('a-secure-password');
  await page.getByRole('button', { name: 'Sign in to service console' }).click();

  await expect(page.getByRole('heading', { name: 'Administrator workspace' })).toBeVisible();
  await expect(page.getByRole('definition').filter({ hasText: 'admin@onesalez.com' })).toBeVisible();

  const logoutButton = page.getByRole('button', { name: 'Logout' });
  if ((page.viewportSize()?.width ?? 1280) < 1024) {
    await page.getByRole('button', { name: 'Open sidebar' }).click();
  }
  await logoutButton.click();
  await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
});
