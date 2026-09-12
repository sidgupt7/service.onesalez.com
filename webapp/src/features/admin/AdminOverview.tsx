import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { Link } from "react-router-dom";
import { Input } from "../../components/ui/Input";
import { Button } from "../../components/ui/Button";
import { useAuth } from "../auth/AuthProvider";
import type { ReportSummary } from "../reports/reports-api";

interface Dashboard {
  summary: ReportSummary;
  ageing: {
    pending: number;
    unassigned: number;
    under_day: number;
    one_to_three_days: number;
    over_three_days: number;
  };
  team_performance: Array<{
    employee_id: number;
    full_name: string;
    attempts: number;
    completed: number;
    released: number;
    avg_attempt_minutes: number | null;
  }>;
  daily: Array<{ date: string; raised: number; completed: number }>;
}
const today = () =>
  new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Kolkata" });
const card =
  "rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 shadow-sm";

export function AdminOverview() {
  const { actor, authenticatedRequest } = useAuth();
  const [to, setTo] = useState(today);
  const [from, setFrom] = useState(() => `${today().slice(0, 8)}01`);
  const canReport = Boolean(
    actor?.roles.includes("SYSTEM_ADMIN") ||
    actor?.permissions.includes("analytics.view"),
  );
  const canDecline = Boolean(
    actor?.roles.includes("SYSTEM_ADMIN") ||
    actor?.permissions.includes("tickets.decline"),
  );
  const report = useQuery({
    queryKey: ["dashboard", from, to],
    queryFn: () =>
      authenticatedRequest<Dashboard>(
        `/analytics?${new URLSearchParams({ from, to })}`,
      ),
    enabled: canReport && Boolean(from && to),
    refetchInterval: 60_000,
  });
  const data = report.data;
  return (
    <main className="mx-auto max-w-7xl">
      <h1 className="text-2xl font-bold sm:text-3xl">Employee workspace</h1>
      <p className="mt-2 text-sm text-[var(--muted)]">
        Welcome, {actor?.displayName || actor?.email}. Manage clients and keep
        service requests moving.
      </p>
      <div className="my-5 flex flex-wrap gap-4 text-sm font-semibold text-[var(--brand)]">
        <Link to="/console">Open service console →</Link>
        <Link to="/admin/clients">Manage clients →</Link>
        {canReport && <Link to="/admin/reports">View service ledger →</Link>}
      </div>
      {!canReport ? (
        <article className={card}>
          <h2 className="font-semibold">Your workspace</h2>
          <p className="mt-2">
            Use the service console to accept requests, record each attempt, and
            complete service work.
          </p>
          <p className="mt-3 text-sm text-[var(--muted)]">
            {actor?.roles.join(", ")}
          </p>
        </article>
      ) : (
        <>
          <div className="flex flex-wrap items-end gap-3">
            <label className="text-sm">
              From
              <Input
                type="date"
                value={from}
                onChange={(event) => setFrom(event.target.value)}
              />
            </label>
            <label className="text-sm">
              To
              <Input
                type="date"
                value={to}
                onChange={(event) => setTo(event.target.value)}
              />
            </label>
            <Button
              disabled={report.isFetching}
              onClick={() => report.refetch()}
            >
              Refresh metrics
            </Button>
          </div>
          <p className="mt-2 text-xs text-[var(--muted)]">
            India Standard Time. Summary counts tickets raised in the selected
            range.
          </p>
          {report.isLoading && (
            <p role="status" className="p-8">
              Loading operational metrics…
            </p>
          )}
          {report.isError && (
            <p role="alert" className="my-5 text-red-700">
              {report.error.message}
            </p>
          )}
          {data && (
            <>
              <section
                aria-label="Ticket summary"
                className="my-6 grid grid-cols-2 gap-3 lg:grid-cols-3"
              >
                {[
                  ["Total raised", data.summary.total],
                  ["Open", data.summary.open_count],
                  ["Accepted", data.summary.accepted_count],
                  ["Completed", data.summary.completed_count],
                  ...(canDecline
                    ? [["Declined", data.summary.declined_count]]
                    : []),
                  [
                    "Average resolution",
                    data.summary.avg_resolution_minutes == null
                      ? "—"
                      : `${Math.round(Number(data.summary.avg_resolution_minutes))} min`,
                  ],
                ].map(([label, value]) => (
                  <article key={label} className={card}>
                    <p className="text-xs text-[var(--muted)]">{label}</p>
                    <p className="mt-2 text-2xl font-bold">{value ?? 0}</p>
                  </article>
                ))}
              </section>
              <section className={card}>
                <h2 className="font-bold">Current backlog · all dates</h2>
                <p className="my-3 text-sm">
                  {data.ageing.pending} pending · {data.ageing.unassigned}{" "}
                  awaiting acceptance
                </p>
                <div className="grid gap-3 sm:grid-cols-3">
                  {[
                    ["Under 24 hours", data.ageing.under_day],
                    ["1–3 days", data.ageing.one_to_three_days],
                    ["Over 3 days", data.ageing.over_three_days],
                  ].map(([label, value]) => (
                    <div
                      key={label}
                      className="rounded-xl bg-[var(--surface-soft)] p-4"
                    >
                      <p className="text-sm">{label}</p>
                      <p className="text-xl font-bold">{value}</p>
                    </div>
                  ))}
                </div>
              </section>
              <section className={`${card} mt-5`}>
                <h2 className="font-bold">Daily ticket volume</h2>
                <p className="mt-1 text-xs text-[var(--muted)]">
                  Completed shows the current outcome of requests raised that
                  day.
                </p>
                {data.daily.length === 0 ? (
                  <p className="py-5">No requests in this range.</p>
                ) : (
                  <div className="mt-4 max-h-80 space-y-2 overflow-y-auto">
                    {data.daily.map((day) => (
                      <div
                        key={day.date}
                        className="grid grid-cols-[90px_1fr_90px] items-center gap-3 text-xs"
                      >
                        <span>{day.date}</span>
                        <div className="h-5 rounded bg-[var(--surface-soft)]">
                          <div
                            className="h-5 rounded bg-[var(--brand)]"
                            style={{
                              width: `${(Number(day.raised) / Math.max(1, ...data.daily.map((row) => Number(row.raised)))) * 100}%`,
                            }}
                          />
                        </div>
                        <span>
                          {day.raised} / {day.completed} done
                        </span>
                      </div>
                    ))}
                  </div>
                )}
              </section>
              <section className={`${card} mt-5 overflow-x-auto`}>
                <h2 className="mb-4 font-bold">Employee performance</h2>
                <table className="w-full text-left text-sm">
                  <caption className="sr-only">
                    Attempts accepted during the selected date range
                  </caption>
                  <thead>
                    <tr>
                      {[
                        "Employee",
                        "Attempts",
                        "Completed",
                        "Released",
                        "Average duration",
                      ].map((label) => (
                        <th scope="col" key={label} className="p-2">
                          {label}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {data.team_performance.map((employee) => (
                      <tr
                        key={employee.employee_id}
                        className="border-t border-[var(--border)]"
                      >
                        <th scope="row" className="p-2 font-medium">
                          {employee.full_name}
                        </th>
                        <td className="p-2">{employee.attempts}</td>
                        <td className="p-2">{employee.completed ?? 0}</td>
                        <td className="p-2">{employee.released ?? 0}</td>
                        <td className="p-2">
                          {employee.avg_attempt_minutes == null
                            ? "—"
                            : `${Math.round(Number(employee.avg_attempt_minutes))} min`}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </section>
            </>
          )}
        </>
      )}
    </main>
  );
}
