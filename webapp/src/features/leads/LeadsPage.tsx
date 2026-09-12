import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState, type FormEvent } from "react";
import { Link } from "react-router-dom";
import { Button } from "../../components/ui/Button";
import { Input } from "../../components/ui/Input";
import { Pagination } from "../../components/ui/Pagination";
import { useDebounced } from "../../lib/use-debounced";
import { useAuth } from "../auth/AuthProvider";
import { ClientOnboardingWizard } from "../clients/ClientOnboardingWizard";
import type { ClientOnboardingInput } from "../clients/clients-api";

const statuses = ["NEW", "CONTACTED", "QUALIFIED", "WON", "LOST"] as const;
type LeadStatus = (typeof statuses)[number];
interface Lead {
  lead_id: number;
  business_name: string;
  contact_name: string;
  email: string;
  phone: string;
  source: string;
  notes: string;
  status: LeadStatus;
  converted_client_id: number | null;
}
interface LeadPage {
  items: Lead[];
  total: number;
  page: number;
  limit: number;
}
const empty = {
  business_name: "",
  contact_name: "",
  email: "",
  phone: "",
  source: "",
  notes: "",
  status: "NEW" as LeadStatus,
};
const fieldClass =
  "h-11 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 text-sm";

export function LeadsPage() {
  const { authenticatedRequest: request } = useAuth();
  const cache = useQueryClient();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [editing, setEditing] = useState<Lead | "new" | null>(null);
  const [form, setForm] = useState(empty);
  const [convert, setConvert] = useState<Lead | null>(null);
  const [selected, setSelected] = useState<number[]>([]);
  const [bulkStatus, setBulkStatus] = useState<LeadStatus>("CONTACTED");
  const query = useDebounced(search);
  const leads = useQuery({
    queryKey: ["leads", page, query, status],
    queryFn: () =>
      request<LeadPage>(
        `/leads?${new URLSearchParams({ paginated: "1", page: String(page), limit: "25", search: query, status })}`,
      ),
  });
  const refresh = () => cache.invalidateQueries({ queryKey: ["leads"] });
  const save = useMutation({
    mutationFn: () =>
      request(editing === "new" ? "/leads" : `/leads/${editing?.lead_id}`, {
        method: editing === "new" ? "POST" : "PUT",
        body: JSON.stringify(form),
      }),
    onSuccess: async () => {
      setEditing(null);
      await refresh();
    },
  });
  const remove = useMutation({
    mutationFn: (id: number) => request(`/leads/${id}`, { method: "DELETE" }),
    onSuccess: refresh,
  });
  const bulk = useMutation({
    mutationFn: () =>
      request("/leads/bulk-status", {
        method: "POST",
        body: JSON.stringify({ ids: selected, status: bulkStatus }),
      }),
    onSuccess: async () => {
      setSelected([]);
      await refresh();
    },
  });
  const conversion = useMutation({
    mutationFn: (input: ClientOnboardingInput) =>
      request(`/leads/${convert?.lead_id}/convert`, {
        method: "POST",
        body: JSON.stringify(input),
      }),
    onSuccess: async () => {
      setConvert(null);
      await refresh();
      await cache.invalidateQueries({ queryKey: ["clients"] });
    },
  });
  const edit = (lead: Lead | "new") => {
    save.reset();
    setEditing(lead);
    setForm(
      lead === "new"
        ? empty
        : {
            business_name: lead.business_name,
            contact_name: lead.contact_name,
            email: lead.email,
            phone: lead.phone || "",
            source: lead.source || "",
            notes: lead.notes || "",
            status: lead.status,
          },
    );
  };
  const submit = (event: FormEvent) => {
    event.preventDefault();
    save.mutate();
  };
  return (
    <main className="mx-auto max-w-7xl">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">Leads</h1>
          <p className="mt-2 text-sm text-[var(--muted)]">
            Track prospects and convert qualified leads into clients.
          </p>
        </div>
        <Button onClick={() => edit("new")}>Add lead</Button>
      </div>
      <div className="my-5 flex flex-wrap gap-3">
        <Input
          aria-label="Search leads"
          placeholder="Business, contact or email"
          value={search}
          onChange={(e) => {
            setSearch(e.target.value);
            setPage(1);
            setSelected([]);
          }}
          className="max-w-sm"
        />
        <select
          aria-label="Filter lead status"
          className={fieldClass}
          value={status}
          onChange={(e) => {
            setStatus(e.target.value);
            setPage(1);
            setSelected([]);
          }}
        >
          <option value="">All statuses</option>
          {statuses.map((s) => (
            <option key={s}>{s}</option>
          ))}
        </select>
      </div>
      {selected.length > 0 && (
        <div className="mb-4 flex flex-wrap items-center gap-3">
          <span>{selected.length} selected</span>
          <select
            aria-label="Bulk lead status"
            className={fieldClass}
            value={bulkStatus}
            onChange={(e) => setBulkStatus(e.target.value as LeadStatus)}
          >
            {statuses.map((s) => (
              <option key={s}>{s}</option>
            ))}
          </select>
          <Button disabled={bulk.isPending} onClick={() => bulk.mutate()}>
            Update selected
          </Button>
        </div>
      )}
      {[leads.error, remove.error, bulk.error]
        .filter(Boolean)
        .map((error, index) => (
          <p role="alert" key={index} className="my-3 text-red-700">
            {error?.message}
          </p>
        ))}
      {editing && (
        <form
          onSubmit={submit}
          className="mb-6 rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5"
        >
          <h2 className="mb-4 font-bold">
            {editing === "new" ? "New lead" : "Edit lead"}
          </h2>
          <div className="grid gap-3 sm:grid-cols-2">
            {(
              [
                "business_name",
                "contact_name",
                "email",
                "phone",
                "source",
              ] as const
            ).map((field) => (
              <label key={field} className="text-sm">
                {field.replaceAll("_", " ")}
                <Input
                  required={["business_name", "contact_name", "email"].includes(
                    field,
                  )}
                  maxLength={
                    field === "phone"
                      ? 20
                      : field === "source"
                        ? 100
                        : field === "email"
                          ? 254
                          : 200
                  }
                  type={field === "email" ? "email" : "text"}
                  value={form[field]}
                  onChange={(e) =>
                    setForm((value) => ({ ...value, [field]: e.target.value }))
                  }
                />
              </label>
            ))}
            <label className="text-sm">
              Status
              <select
                className={`${fieldClass} block w-full`}
                value={form.status}
                onChange={(e) =>
                  setForm((value) => ({
                    ...value,
                    status: e.target.value as LeadStatus,
                  }))
                }
              >
                {statuses.map((s) => (
                  <option key={s}>{s}</option>
                ))}
              </select>
            </label>
            <label className="text-sm sm:col-span-2">
              Notes
              <textarea
                className="mt-1 min-h-24 w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] p-3"
                value={form.notes}
                onChange={(e) =>
                  setForm((value) => ({ ...value, notes: e.target.value }))
                }
              />
            </label>
          </div>
          {save.error && (
            <p role="alert" className="my-3 text-red-700">
              {save.error.message}
            </p>
          )}
          <div className="mt-4 flex gap-2">
            <Button disabled={save.isPending} type="submit">
              Save lead
            </Button>
            <Button type="button" onClick={() => setEditing(null)}>
              Cancel
            </Button>
          </div>
        </form>
      )}
      {leads.isLoading && <p role="status">Loading leads…</p>}
      {leads.data?.items.length === 0 && (
        <p className="p-8 text-center">No leads match these filters.</p>
      )}
      <div className="grid gap-3">
        {leads.data?.items.map((lead) => (
          <article
            key={lead.lead_id}
            className="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5"
          >
            <div className="flex gap-3">
              <input
                aria-label={`Select ${lead.business_name}`}
                type="checkbox"
                checked={selected.includes(lead.lead_id)}
                onChange={(e) =>
                  setSelected((ids) =>
                    e.target.checked
                      ? [...ids, lead.lead_id]
                      : ids.filter((id) => id !== lead.lead_id),
                  )
                }
              />
              <div className="min-w-0">
                <h2 className="font-bold">{lead.business_name}</h2>
                <p className="mt-1 break-words text-sm text-[var(--muted)]">
                  {lead.contact_name} · {lead.email} · {lead.phone}
                </p>
                <p className="mt-2 text-xs font-bold">{lead.status}</p>
                {lead.notes && (
                  <p className="mt-2 whitespace-pre-wrap text-sm">
                    {lead.notes}
                  </p>
                )}
              </div>
            </div>
            <div className="mt-4 flex flex-wrap gap-3">
              <Button onClick={() => edit(lead)}>Edit</Button>
              {lead.converted_client_id ? (
                <Link
                  className="p-3 text-sm text-[var(--brand)]"
                  to="/admin/clients"
                >
                  Converted to client #{lead.converted_client_id}
                </Link>
              ) : (
                <Button
                  onClick={() => {
                    conversion.reset();
                    setConvert(lead);
                  }}
                >
                  Convert to client
                </Button>
              )}
              <Button
                disabled={remove.isPending}
                onClick={() => {
                  if (window.confirm(`Remove lead ${lead.business_name}?`))
                    remove.mutate(lead.lead_id);
                }}
              >
                Remove
              </Button>
            </div>
          </article>
        ))}
      </div>
      {leads.data && (
        <Pagination
          page={page}
          limit={leads.data.limit}
          total={leads.data.total}
          pending={leads.isFetching}
          onPage={(value) => {
            setPage(value);
            setSelected([]);
          }}
        />
      )}
      {convert && (
        <ClientOnboardingWizard
          pending={conversion.isPending}
          serverError={conversion.error?.message || null}
          onClose={() => setConvert(null)}
          initialValues={{
            legalName: convert.business_name,
            displayName: convert.business_name,
            adminName: convert.contact_name,
            email: convert.email,
            mobileNumber: convert.phone || "",
          }}
          onSubmit={async (input) => {
            await conversion.mutateAsync(input);
          }}
        />
      )}
    </main>
  );
}
