import { formatServiceDate } from "../../lib/date";
import { useQuery } from "@tanstack/react-query";
import {
  Building2,
  Clock3,
  Download,
  FileSpreadsheet,
  Search,
  ShieldX,
  UserRound,
} from "lucide-react";
import { useMemo, useState, type ReactNode } from "react";

import { Pagination } from "../../components/ui/Pagination";
import { csvCell } from "../../lib/csv";
import { useDebounced } from "../../lib/use-debounced";
import { Button } from "../../components/ui/Button";
import { Input } from "../../components/ui/Input";
import { useAuth } from "../auth/AuthProvider";
import {
  getServiceLedger,
  allLedgerRows,
  type LedgerRow,
  type ReportFilters,
  type ReportSummary,
} from "./reports-api";

type ReportPreset = "ledger" | "client" | "employee" | "pending" | "declined";
const today = new Date().toISOString().slice(0, 10);
const monthStart = `${today.slice(0, 8)}01`;
const presets: Array<{
  id: ReportPreset;
  label: string;
  icon: typeof FileSpreadsheet;
}> = [
  { id: "ledger", label: "Service ledger", icon: FileSpreadsheet },
  { id: "client", label: "Client-wise", icon: Building2 },
  { id: "employee", label: "Employee-wise", icon: UserRound },
  { id: "pending", label: "Pending", icon: Clock3 },
  { id: "declined", label: "Declined", icon: ShieldX },
];

export function ReportsPage() {
  const { actor, authenticatedRequest } = useAuth();
  const canDecline = Boolean(
    actor?.roles.includes("SYSTEM_ADMIN") ||
    actor?.permissions.includes("tickets.decline"),
  );
  const [page, setPage] = useState(1);
  const [exporting, setExporting] = useState(false);
  const [exportCount, setExportCount] = useState(0);
  const [exportError, setExportError] = useState("");
  const [preset, setPreset] = useState<ReportPreset>("ledger");
  const [filters, setFilters] = useState<ReportFilters>({
    from: monthStart,
    to: today,
    status: "",
    clientId: "",
    locationId: "",
    employeeId: "",
    search: "",
  });
  const debouncedFilters = useDebounced(filters);
  const report = useQuery({
    queryKey: ["service-report", debouncedFilters, page],
    queryFn: () =>
      getServiceLedger(authenticatedRequest, debouncedFilters, page),
  });
  const download = async () => {
    setExporting(true);
    setExportCount(0);
    setExportError("");
    try {
      const rows = await allLedgerRows(
        authenticatedRequest,
        filters,
        setExportCount,
      );
      exportCsv(rows, filters);
    } catch (error) {
      setExportError(
        error instanceof Error ? error.message : "Export failed. Try again.",
      );
    } finally {
      setExporting(false);
    }
  };
  const locations = useMemo(
    () =>
      (report.data?.filters.locations || []).filter(
        (location) =>
          !filters.clientId || String(location.client_id) === filters.clientId,
      ),
    [report.data, filters.clientId],
  );
  const choosePreset = (selected: ReportPreset) => {
    setPreset(selected);
    setPage(1);
    setFilters((value) => ({
      ...value,
      status:
        selected === "pending"
          ? "PENDING"
          : selected === "declined"
            ? "DECLINED"
            : "",
      clientId: selected === "employee" ? "" : value.clientId,
      employeeId: selected === "client" ? "" : value.employeeId,
      locationId: "",
    }));
  };
  const set = (field: keyof ReportFilters, value: string) => {
    setPage(1);
    setFilters((current) => ({
      ...current,
      [field]: value,
      ...(field === "clientId" ? { locationId: "" } : {}),
    }));
  };

  return (
    <div className="mx-auto max-w-[1500px]">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.16em] text-[var(--brand)]">
            Operational intelligence
          </p>
          <h1 className="mt-1 text-2xl font-bold sm:text-3xl">
            Service reports
          </h1>
          <p className="mt-2 text-sm text-[var(--muted)]">
            One ledger with date, client, employee, status, and location
            filters.
          </p>
        </div>
        <Button
          disabled={exporting || !report.data?.items.length}
          onClick={download}
        >
          <Download className="mr-2 h-4 w-4" />
          {exporting ? `Exporting ${exportCount}…` : "Export CSV"}
        </Button>
      </div>
      <div className="mt-7 grid gap-2 sm:grid-cols-5">
        {presets
          .filter((item) => item.id !== "declined" || canDecline)
          .map(({ id, label, icon: Icon }) => (
            <button
              key={id}
              type="button"
              onClick={() => choosePreset(id)}
              className={`flex h-12 items-center justify-center gap-2 rounded-xl border text-sm font-semibold ${preset === id ? "border-[var(--brand)] bg-[var(--brand)] text-white" : "border-[var(--border)] bg-[var(--surface)] text-[var(--muted)]"}`}
            >
              <Icon className="h-4 w-4" />
              {label}
            </button>
          ))}
      </div>
      <section className="mt-5 rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-4 shadow-sm sm:p-5">
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-7">
          <Filter label="From">
            <Input
              type="date"
              value={filters.from}
              onChange={(event) => set("from", event.target.value)}
            />
          </Filter>
          <Filter label="To">
            <Input
              type="date"
              value={filters.to}
              onChange={(event) => set("to", event.target.value)}
            />
          </Filter>
          <Filter label="Client">
            <select
              value={filters.clientId}
              onChange={(event) => set("clientId", event.target.value)}
              className={selectClass}
            >
              <option value="">All clients</option>
              {report.data?.filters.clients.map((client) => (
                <option key={client.client_id} value={client.client_id}>
                  {client.client_code} · {client.legal_name}
                </option>
              ))}
            </select>
          </Filter>
          <Filter label="Location">
            <select
              value={filters.locationId}
              onChange={(event) => set("locationId", event.target.value)}
              className={selectClass}
            >
              <option value="">All locations</option>
              {locations.map((location) => (
                <option key={location.location_id} value={location.location_id}>
                  {location.location_code} · {location.location_name}
                </option>
              ))}
            </select>
          </Filter>
          <Filter label="Employee">
            <select
              value={filters.employeeId}
              onChange={(event) => set("employeeId", event.target.value)}
              className={selectClass}
            >
              <option value="">All employees</option>
              {report.data?.filters.employees.map((employee) => (
                <option key={employee.employee_id} value={employee.employee_id}>
                  {employee.employee_code} · {employee.full_name}
                </option>
              ))}
            </select>
          </Filter>
          <Filter label="Status">
            <select
              value={filters.status}
              onChange={(event) => set("status", event.target.value)}
              className={selectClass}
            >
              <option value="">All statuses</option>
              <option value="PENDING">Pending</option>
              <option value="OPEN">Open</option>
              <option value="ACCEPTED">Accepted</option>
              <option value="COMPLETED">Completed</option>
              {canDecline && <option value="DECLINED">Declined</option>}
            </select>
          </Filter>
          <Filter label="Search">
            <label className="relative block">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]" />
              <Input
                value={filters.search}
                onChange={(event) => set("search", event.target.value)}
                placeholder="Ticket or issue"
                className="pl-9"
              />
            </label>
          </Filter>
        </div>
      </section>
      {report.data && (
        <Summary summary={report.data.summary} canDecline={canDecline} />
      )}
      {report.isLoading ? (
        <div className="mt-5 rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-16 text-center text-sm text-[var(--muted)]">
          Preparing report…
        </div>
      ) : report.isError ? (
        <div className="mt-5 rounded-2xl bg-red-50 p-8 text-center text-sm text-red-800">
          The report could not be loaded.
        </div>
      ) : (
        <LedgerTable
          rows={report.data?.items || []}
          total={report.data?.total || 0}
        />
      )}
      {report.data && (
        <Pagination
          page={page}
          limit={report.data.limit}
          total={report.data.total}
          pending={report.isFetching}
          onPage={setPage}
        />
      )}
      {exportError && (
        <p role="alert" className="mt-3 text-red-700">
          {exportError}
        </p>
      )}
    </div>
  );
}

function Summary({
  summary,
  canDecline,
}: {
  summary: ReportSummary;
  canDecline: boolean;
}) {
  const cards = [
    ["Total", summary.total],
    ["Open", summary.open_count],
    ["Accepted", summary.accepted_count],
    ["Completed", summary.completed_count],
    ...(canDecline ? [["Declined", summary.declined_count]] : []),
    ["Avg resolution", duration(summary.avg_resolution_minutes)],
  ];
  return (
    <section className="mt-5 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
      {cards.map(([label, value]) => (
        <article
          key={label}
          className="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-4 shadow-sm"
        >
          <p className="text-[10px] font-bold uppercase tracking-wide text-[var(--muted)]">
            {label}
          </p>
          <p className="mt-2 text-xl font-bold">{value ?? 0}</p>
        </article>
      ))}
    </section>
  );
}
function LedgerTable({ rows, total }: { rows: LedgerRow[]; total: number }) {
  return (
    <section className="mt-5 overflow-hidden rounded-2xl border border-[var(--border)] bg-[var(--surface)] shadow-sm">
      <div className="border-b border-[var(--border)] px-4 py-3 text-xs text-[var(--muted)]">
        Showing {rows.length} of {total} records
      </div>
      {rows.length === 0 ? (
        <div className="p-16 text-center text-sm text-[var(--muted)]">
          No service records match these filters.
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="min-w-[1300px] w-full text-left text-xs">
            <thead className="bg-[var(--surface-soft)] text-[10px] uppercase text-[var(--muted)]">
              <tr>
                {[
                  "Date / SR No.",
                  "Client / Location",
                  "Issue",
                  "Priority",
                  "Status",
                  "Service employees",
                  "Attempts",
                  "Outcome / Closed",
                ].map((heading) => (
                  <th key={heading} className="px-4 py-3 font-bold">
                    {heading}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--border)]">
              {rows.map((row) => (
                <tr
                  key={row.ticket_id}
                  className="align-top hover:bg-[var(--surface-soft)]/50"
                >
                  <td className="px-4 py-4">
                    <p>{formatDate(row.created_at)}</p>
                    <p className="mt-1 font-bold text-[var(--brand)]">
                      {row.service_request_number}
                    </p>
                  </td>
                  <td className="px-4 py-4">
                    <p className="font-semibold">
                      {row.client_code} · {row.client_name}
                    </p>
                    <p className="mt-1 text-[var(--muted)]">
                      {row.location_code} · {row.location_name}, {row.city}
                    </p>
                    <p className="mt-1 text-[var(--muted)]">
                      Contact: {row.reported_by_name}
                    </p>
                  </td>
                  <td className="max-w-sm px-4 py-4">
                    <p className="font-semibold">{row.subject}</p>
                    <p className="mt-1 line-clamp-3 leading-5 text-[var(--muted)]">
                      {row.issue_description}
                    </p>
                  </td>
                  <td className="px-4 py-4 font-bold">{row.priority}</td>
                  <td className="px-4 py-4">
                    <span className="rounded-full bg-[var(--surface-soft)] px-2 py-1 font-bold">
                      {row.ticket_status}
                    </span>
                  </td>
                  <td className="px-4 py-4">
                    {row.service_employees || row.current_employee_name || "—"}
                  </td>
                  <td className="px-4 py-4 text-center font-bold">
                    {row.attempt_count}
                  </td>
                  <td className="max-w-sm px-4 py-4">
                    <p>
                      {row.ticket_status === "COMPLETED"
                        ? row.final_resolution || "Completed"
                        : row.ticket_status === "DECLINED"
                          ? row.decline_reason || "Declined"
                          : row.current_employee_name
                            ? `Assigned to ${row.current_employee_name}`
                            : "Pending acceptance"}
                    </p>
                    {(row.completed_at || row.declined_at) && (
                      <p className="mt-1 text-[var(--muted)]">
                        {formatDate(row.completed_at || row.declined_at || "")}
                      </p>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}
function Filter({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="block">
      <span className="mb-1 block text-[10px] font-bold uppercase text-[var(--muted)]">
        {label}
      </span>
      {children}
    </label>
  );
}
const selectClass =
  "h-11 w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 text-sm outline-none focus:border-[var(--brand)]";
const formatDate = formatServiceDate;
function duration(minutes: number | null) {
  if (minutes === null) return "—";
  if (minutes < 60) return `${Math.round(minutes)} min`;
  const hours = minutes / 60;
  return hours < 24
    ? `${hours.toFixed(1)} hr`
    : `${(hours / 24).toFixed(1)} days`;
}
function exportCsv(rows: LedgerRow[], filters: ReportFilters) {
  const headers = [
    "Created",
    "Service request",
    "Client code",
    "Client",
    "Location",
    "Subject",
    "Priority",
    "Status",
    "Reported by",
    "Service employees",
    "Attempts",
    "Completed",
    "Resolution",
    "Decline reason",
  ];
  const values = rows.map((row) => [
    row.created_at,
    row.service_request_number,
    row.client_code,
    row.client_name,
    row.location_name,
    row.subject,
    row.priority,
    row.ticket_status,
    row.reported_by_name,
    row.service_employees || "",
    row.attempt_count,
    row.completed_at || "",
    row.final_resolution || "",
    row.decline_reason || "",
  ]);
  const csv = [headers, ...values]
    .map((line) => line.map(csvCell).join(","))
    .join("\r\n");
  const url = URL.createObjectURL(
    new Blob([csv], { type: "text/csv;charset=utf-8" }),
  );
  const link = document.createElement("a");
  link.href = url;
  link.download = `onesalez-service-report-${filters.from}-to-${filters.to}.csv`;
  link.click();
  URL.revokeObjectURL(url);
}
