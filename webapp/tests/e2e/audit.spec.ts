import { expect, test, type Page } from "@playwright/test";

const actor = {
  id: 1,
  type: "EMPLOYEE",
  email: "audit@example.invalid",
  displayName: "Audit Admin",
  clientId: null,
  roles: ["SYSTEM_ADMIN"],
  permissions: [
    "tickets.manage",
    "tickets.decline",
    "analytics.view",
    "leads.manage",
  ],
};
const session = {
  access_token: "audit-test-token",
  token_type: "Bearer",
  expires_in: 900,
  actor,
};
const summary = {
  total: 71,
  open_count: 65,
  accepted_count: 4,
  completed_count: 2,
  declined_count: 0,
  avg_resolution_minutes: 90,
};
const envelope = (data: unknown) => ({
  success: true,
  data,
  error: null,
  timestamp: new Date().toISOString(),
});

async function mock(page: Page) {
  const leads: Record<string, unknown>[] = [];
  await page.route("**/api/v1/**", async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    const path = url.pathname.replace("/api/v1", "");
    let data: unknown;
    if (path === "/auth/refresh") data = session;
    else if (path === "/health") data = { status: "ok" };
    else if (path === "/analytics")
      data = {
        summary,
        ageing: {
          pending: 69,
          unassigned: 65,
          under_day: 60,
          one_to_three_days: 7,
          over_three_days: 2,
        },
        daily: [{ date: "2026-09-12", raised: 71, completed: 2 }],
        team_performance: [
          {
            employee_id: 1,
            full_name: "Audit Admin",
            attempts: 6,
            completed: 2,
            released: 0,
            avg_attempt_minutes: 30,
          },
        ],
      };
    else if (path === "/tickets") {
      const pageNumber = Number(url.searchParams.get("page"));
      const status = url.searchParams.get("status") || "OPEN";
      data = {
        items: Array.from(
          { length: pageNumber === 3 ? 15 : 25 },
          (_, index) => ({
            ticket_id: (pageNumber - 1) * 25 + index + 1,
            service_request_number: `SR-${(pageNumber - 1) * 25 + index + 1}`,
            subject: `Request ${(pageNumber - 1) * 25 + index + 1}`,
            ticket_status: status,
            priority: "NORMAL",
            client_name: "Audit Client",
            location_name: "Head office",
            created_at: "2026-09-12T10:00:00+05:30",
            current_employee_name: null,
          }),
        ),
        total: 65,
        page: pageNumber,
        limit: 25,
        counts: { OPEN: 65, ACCEPTED: 4, COMPLETED: 2, DECLINED: 0 },
      };
    } else if (path === "/leads" && request.method() === "POST") {
      data = {
        ...request.postDataJSON(),
        lead_id: 1,
        converted_client_id: null,
      };
      leads.push(data as Record<string, unknown>);
    } else if (path === "/leads" && request.method() === "GET")
      data = { items: leads, total: leads.length, page: 1, limit: 25 };
    else if (path === "/teams") data = [];
    else if (path === "/users") data = [];
    else if (path === "/auth/logout") data = { message: "Logged out." };
    else {
      await route.fulfill({
        status: 404,
        json: {
          success: false,
          data: null,
          error: {
            code: "NOT_FOUND",
            message: "Ticket is no longer available.",
          },
        },
      });
      return;
    }
    await route.fulfill({ json: envelope(data) });
  });
}

test("dashboard renders live counts and remains within the viewport", async ({
  page,
}) => {
  await mock(page);
  await page.goto("/admin");
  await expect(
    page.getByRole("heading", { name: "Employee workspace" }),
  ).toBeVisible();
  await expect(
    page.getByRole("region", { name: "Ticket summary" }),
  ).toContainText("71");
  await expect(
    page.getByRole("heading", { name: "Employee performance" }),
  ).toBeVisible();
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= window.innerWidth,
    ),
  ).toBe(true);
  await page.screenshot({
    path: test.info().outputPath("dashboard.png"),
    fullPage: true,
  });
});

test("ticket pagination reads server totals and shows detail failures", async ({
  page,
}) => {
  await mock(page);
  await page.goto("/console");
  await expect(
    page.getByRole("button", { name: "Open 65", exact: true }),
  ).toBeVisible();
  await expect(
    page.getByRole("navigation", { name: "Pagination" }),
  ).toContainText("1–25 of 65");
  await page.getByRole("button", { name: "Next", exact: true }).click();
  await expect(
    page.getByRole("navigation", { name: "Pagination" }),
  ).toContainText("26–50 of 65");
  await page.getByRole("button").filter({ hasText: "Request 26" }).click();
  await expect(page.getByRole("alert")).toContainText(
    "Ticket is no longer available.",
  );
});

test("lead creation and client conversion form are available", async ({
  page,
}) => {
  await mock(page);
  await page.goto("/admin/leads");
  await page.getByRole("button", { name: "Add lead" }).click();
  await page.getByLabel("business name").fill("Audit Prospect");
  await page.getByLabel("contact name").fill("Audit Contact");
  await page
    .getByLabel("email", { exact: true })
    .fill("prospect@example.invalid");
  await page.getByRole("button", { name: "Save lead" }).click();
  await expect(
    page.getByRole("heading", { name: "Audit Prospect" }),
  ).toBeVisible();
  await page.getByRole("button", { name: "Convert to client" }).click();
  await expect(page.getByLabel(/Legal business name/)).toHaveValue(
    "Audit Prospect",
  );
});

test("theme preference survives reloading the settings page", async ({
  page,
}) => {
  await mock(page);
  await page.goto("/admin/settings");
  const checkbox = page.getByLabel("Use dark theme on this browser");
  await checkbox.check();
  await expect(
    page.getByRole("button", { name: "Use light theme" }),
  ).toBeVisible();
  await page.reload();
  await expect(checkbox).toBeChecked();
  await expect(page.locator("html")).toHaveClass(/dark/);
  await expect(page.getByRole("button", { name: "Reset app" })).toBeVisible();
});

test("client can read and reply to the service conversation", async ({
  page,
}) => {
  const messages = [
    {
      message_id: 1,
      author_type: "EMPLOYEE",
      message: "We are investigating.",
      is_internal: false,
      created_at: "2026-09-12 10:00:00",
    },
  ];
  const ticket = {
    ticket_id: 1,
    service_request_number: "SR-1",
    subject: "Printer help",
    issue_description: "Printer offline",
    ticket_status: "OPEN",
    priority: "NORMAL",
    location_name: "Office",
    created_at: "2026-09-12 10:00:00",
    attempts: [],
    description_history: [],
    messages,
  };
  await page.route("**/api/v1/**", async (route) => {
    const path = new URL(route.request().url()).pathname.replace("/api/v1", "");
    let data: unknown;
    if (path === "/auth/refresh")
      data = {
        ...session,
        actor: {
          ...actor,
          id: 7,
          type: "CLIENT_CONTACT",
          clientId: 2,
          roles: ["CLIENT_ADMIN"],
          permissions: [],
        },
      };
    else if (path === "/customers/2")
      data = {
        client_id: 2,
        legal_name: "Audit Client",
        contacts: [
          { contact_id: 7, has_all_locations: true, location_ids: [] },
        ],
        locations: [
          { location_id: 3, location_name: "Office", is_active: true },
        ],
      };
    else if (path === "/tickets")
      data = {
        items: [ticket],
        total: 1,
        page: 1,
        limit: 25,
        counts: { OPEN: 1, ACCEPTED: 0, COMPLETED: 0, DECLINED: 0 },
      };
    else if (path === "/tickets/1") data = ticket;
    else if (path === "/tickets/1/messages") {
      expect(route.request().postDataJSON().is_internal).toBe(false);
      messages.push({
        message_id: 2,
        author_type: "CLIENT_CONTACT",
        message: route.request().postDataJSON().message,
        is_internal: false,
        created_at: "2026-09-12 10:05:00",
      });
      data = { message_id: 2 };
    } else data = { status: "ok" };
    await route.fulfill({ json: envelope(data) });
  });
  await page.goto("/portal");
  await page.getByRole("button", { name: /Service history/i }).click();
  await page.getByRole("button").filter({ hasText: "Printer help" }).click();
  await expect(page.getByText("We are investigating.")).toBeVisible();
  await page
    .getByLabel("Message to service team")
    .fill("The printer is powered on.");
  await page.getByRole("button", { name: "Send message" }).click();
  await expect(page.getByText("The printer is powered on.")).toBeVisible();
  await expect(page.getByLabel("Message to service team")).toHaveValue("");
});
