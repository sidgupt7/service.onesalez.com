import { expect, test } from "@playwright/test";

test("system administrator can onboard a client", async ({ page }) => {
  const clients: Record<string, unknown>[] = [];
  let submitted: Record<string, any> | null = null;
  const session = {
    access_token: "test-access-token",
    token_type: "Bearer",
    expires_in: 900,
    actor: {
      id: 1,
      type: "EMPLOYEE",
      email: "admin@onesalez.com",
      displayName: "Siddharth Gupta",
      clientId: null,
      roles: ["SYSTEM_ADMIN"],
      permissions: ["clients.manage"],
    },
  };

  await page.route("**/api/v1/**", async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    let data: unknown = null;
    if (url.pathname.endsWith("/auth/refresh")) data = session;
    else if (url.pathname.endsWith("/health")) data = { status: "ok" };
    else if (
      url.pathname.endsWith("/customers/onboard") &&
      request.method() === "POST"
    ) {
      submitted = request.postDataJSON() as Record<string, any>;
      const clientInput = submitted.client as Record<string, string>;
      data = {
        client_id: 17,
        client_code: clientInput.client_code,
        legal_name: clientInput.legal_name,
        display_name: clientInput.display_name,
        primary_email: submitted.administrator.email,
        primary_phone: null,
        is_active: true,
        location_count: 1,
        contact_count: 1,
      };
      clients.push(data as Record<string, unknown>);
    } else if (url.pathname.endsWith("/customers"))
      data = url.searchParams.get("paginated")
        ? { items: clients, page: 1, limit: 25, total: clients.length }
        : clients;
    else {
      await route.fulfill({
        status: 404,
        json: {
          success: false,
          data: null,
          error: { code: "NOT_FOUND", message: "Not found." },
          timestamp: new Date().toISOString(),
        },
      });
      return;
    }
    await route.fulfill({
      json: {
        success: true,
        data,
        error: null,
        timestamp: new Date().toISOString(),
      },
    });
  });

  await page.goto("/admin/clients");
  await expect(
    page.getByRole("heading", { name: "Clients", exact: true }),
  ).toBeVisible();
  await page.getByRole("button", { name: "Add new client" }).first().click();

  await page.getByLabel(/Client code/).fill("TEST001");
  await page
    .getByLabel(/Legal business name/)
    .fill("Test Trading Private Limited");
  await page.getByLabel(/Display name/).fill("Test Trading");
  await page.getByRole("button", { name: /Continue/ }).click();

  await page.getByLabel(/Location code/).fill("HO01");
  await page.getByLabel(/Location name/).fill("Head Office");
  await page.getByLabel(/Address line 1/).fill("12 Business Park");
  await page.getByLabel(/City/).fill("Kolkata");
  await page.getByLabel(/State/).fill("West Bengal");
  await page.getByLabel(/PIN code/).fill("700001");
  await page.getByRole("button", { name: /Continue/ }).click();

  await page.getByLabel(/Full name/).fill("Client Administrator");
  await page.getByLabel(/Email/).fill("client.admin@example.com");
  await page.getByLabel(/Mobile number/).fill("9876543210");
  await page.getByLabel(/Temporary password/).fill("Temporary@123");
  await page.getByRole("button", { name: "Create client" }).click();

  await expect(page.getByText("Test Trading", { exact: true })).toBeVisible();
  expect(submitted).not.toBeNull();
  expect(submitted!.location.location_type).toBe("HEAD_OFFICE");
  expect(submitted!.administrator.email).toBe("client.admin@example.com");
});
