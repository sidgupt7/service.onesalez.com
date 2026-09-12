import { expect, it, vi } from "vitest";
import {
  allLedgerRows,
  type LedgerRow,
  type ReportFilters,
  type ServiceLedgerReport,
} from "./reports-api";
import type { AuthenticatedRequest } from "../clients/clients-api";

it("exports more than 500 rows and advances by ticket ID", async () => {
  const requested: string[] = [];
  const request: AuthenticatedRequest = async <T>(path: string) => {
    requested.push(path);
    const before = new URL(path, "https://example.invalid").searchParams.get(
      "before_id",
    );
    const start = before ? Number(before) - 1 : 1001;
    const rows = Array.from(
      { length: Math.min(500, start) },
      (_, index) => ({ ticket_id: start - index }) as LedgerRow,
    );
    return { items: rows, limit: 500 } as ServiceLedgerReport as T;
  };
  const filters: ReportFilters = {
    from: "2026-09-01",
    to: "2026-09-12",
    status: "",
    clientId: "",
    employeeId: "",
    locationId: "",
    search: "",
  };
  const progress = vi.fn();
  const rows = await allLedgerRows(request, filters, progress);
  expect(rows).toHaveLength(1001);
  expect(new Set(rows.map((row) => row.ticket_id)).size).toBe(1001);
  expect(requested).toHaveLength(3);
  expect(requested[1]).toContain("before_id=502");
  expect(progress).toHaveBeenLastCalledWith(1001);
});
