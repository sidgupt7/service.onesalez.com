import { useDialog } from "../../lib/use-dialog";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Building2, MapPin, Plus, UserRound, X } from "lucide-react";
import { useEffect, useState, type FormEvent } from "react";

import { Button } from "../../components/ui/Button";
import { Input } from "../../components/ui/Input";
import { ApiError } from "../../lib/api";
import { useAuth } from "../auth/AuthProvider";
import {
  getClient,
  saveContact,
  saveLocation,
  setClientActive,
  setContactActive,
  setLocationActive,
  updateClient,
  type ClientContact,
  type ClientDetails,
  type ClientDetailsInput,
  type ClientLocation,
  type ContactInput,
  type LocationInput,
} from "./clients-api";

type Tab = "details" | "sites" | "contacts";

export function ClientManagementDialog({
  clientId,
  onClose,
}: {
  clientId: number;
  onClose: () => void;
}) {
  const dialogRef = useDialog(onClose);
  const { actor, authenticatedRequest } = useAuth();
  const queryClient = useQueryClient();
  const [tab, setTab] = useState<Tab>("details");
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const client = useQuery({
    queryKey: ["client", clientId],
    queryFn: () => getClient(authenticatedRequest, clientId),
  });

  const apply = async (operation: Promise<ClientDetails>) => {
    setPending(true);
    setError(null);
    try {
      const updated = await operation;
      queryClient.setQueryData(["client", clientId], updated);
      await queryClient.invalidateQueries({ queryKey: ["clients"] });
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? caught.message
          : "The change could not be saved.",
      );
      throw caught;
    } finally {
      setPending(false);
    }
  };

  return (
    <div
      className="fixed inset-0 z-[70] flex items-end justify-center bg-slate-950/55 backdrop-blur-sm sm:items-center sm:p-5"
      ref={dialogRef}
      tabIndex={-1}
      role="dialog"
      aria-modal="true"
      aria-labelledby="client-management-title"
    >
      <div className="flex max-h-[100dvh] w-full max-w-5xl flex-col overflow-hidden rounded-t-3xl bg-[var(--surface)] shadow-2xl sm:max-h-[94dvh] sm:rounded-3xl">
        <header className="flex items-start justify-between border-b border-[var(--border)] px-5 py-5 sm:px-7">
          <div>
            <p className="text-xs font-bold uppercase tracking-[0.16em] text-[var(--brand)]">
              Client master
            </p>
            <h2 id="client-management-title" className="mt-1 text-xl font-bold">
              {client.data?.legal_name || "Loading client…"}
            </h2>
            <p className="mt-1 text-xs text-[var(--muted)]">
              ADD AND MODIFY CLIENT DETAILS, SITES, AND CONTACTS
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close client management"
            className="grid h-10 w-10 place-items-center rounded-xl hover:bg-[var(--surface-soft)]"
          >
            <X className="h-5 w-5" />
          </button>
        </header>
        <nav
          className="grid grid-cols-3 border-b border-[var(--border)] px-3 sm:px-7"
          aria-label="Client sections"
        >
          {(
            [
              ["details", Building2, "Details"],
              ["sites", MapPin, "Sites"],
              ["contacts", UserRound, "Contacts"],
            ] as const
          ).map(([value, Icon, label]) => (
            <button
              key={value}
              type="button"
              onClick={() => setTab(value)}
              className={`flex h-12 items-center justify-center gap-2 border-b-2 text-sm font-semibold ${tab === value ? "border-[var(--brand)] text-[var(--brand)]" : "border-transparent text-[var(--muted)]"}`}
            >
              <Icon className="h-4 w-4" />
              {label}
            </button>
          ))}
        </nav>
        <div className="overflow-y-auto p-5 sm:p-7">
          {error && (
            <div
              role="alert"
              className="mb-4 rounded-xl bg-red-50 p-3 text-sm text-red-800"
            >
              {error}
            </div>
          )}
          {client.isLoading && (
            <p className="py-16 text-center text-sm text-[var(--muted)]">
              Loading client details…
            </p>
          )}
          {client.isError && (
            <p className="py-16 text-center text-sm text-[var(--danger)]">
              Client details could not be loaded.
            </p>
          )}
          {client.data && tab === "details" && (
            <DetailsForm
              client={client.data}
              pending={pending}
              onSave={(input) =>
                apply(updateClient(authenticatedRequest, clientId, input))
              }
              onStatus={(active) =>
                apply(setClientActive(authenticatedRequest, clientId, active))
              }
            />
          )}
          {client.data && tab === "sites" && (
            <SitesEditor
              client={client.data}
              pending={pending}
              apply={apply}
              request={authenticatedRequest}
            />
          )}
          {client.data && tab === "contacts" && (
            <ContactsEditor
              client={client.data}
              pending={pending}
              canAssignAdmin={actor?.type === "EMPLOYEE"}
              apply={apply}
              request={authenticatedRequest}
            />
          )}
        </div>
      </div>
    </div>
  );
}

function DetailsForm({
  client,
  pending,
  onSave,
  onStatus,
}: {
  client: ClientDetails;
  pending: boolean;
  onSave: (data: ClientDetailsInput) => Promise<void>;
  onStatus: (active: boolean) => Promise<void>;
}) {
  const [data, setData] = useState<ClientDetailsInput>({});
  useEffect(
    () =>
      setData({
        client_code: client.client_code,
        legal_name: client.legal_name,
        display_name: client.display_name || "",
        gstin: client.gstin || "",
        pan: client.pan || "",
        primary_email: client.primary_email || "",
        primary_phone: client.primary_phone || "",
        website_url: client.website_url || "",
        notes: client.notes || "",
      }),
    [client],
  );
  const field = (
    name: keyof ClientDetailsInput,
    label: string,
    type = "text",
  ) => (
    <label className="block">
      <span className="mb-1.5 block text-xs font-semibold uppercase text-[var(--muted)]">
        {label}
      </span>
      <Input
        type={type}
        value={String(data[name] || "")}
        onChange={(event) =>
          setData((value) => ({ ...value, [name]: event.target.value }))
        }
        className={type === "email" || type === "url" ? "" : "uppercase"}
        required={name === "client_code" || name === "legal_name"}
      />
    </label>
  );
  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();
        void onSave(data);
      }}
      className="space-y-5"
    >
      <div className="grid gap-4 sm:grid-cols-2">
        {field("client_code", "Client code")}
        {field("legal_name", "Legal name")}
        {field("display_name", "Display name")}
        {field("gstin", "GSTIN")}
        {field("pan", "PAN")}
        {field("primary_email", "Primary email", "email")}
        {field("primary_phone", "Primary phone")}
        {field("website_url", "Website", "url")}
        <label className="block sm:col-span-2">
          <span className="mb-1.5 block text-xs font-semibold uppercase text-[var(--muted)]">
            Notes
          </span>
          <textarea
            value={String(data.notes || "")}
            onChange={(event) =>
              setData((value) => ({ ...value, notes: event.target.value }))
            }
            className="min-h-24 w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] p-3 text-sm uppercase outline-none focus:border-[var(--brand)]"
          />
        </label>
      </div>
      <div className="flex flex-wrap justify-between gap-3 border-t border-[var(--border)] pt-5">
        <Button
          type="button"
          disabled={pending}
          onClick={() => void onStatus(!Boolean(client.is_active))}
          className={
            Boolean(client.is_active)
              ? "bg-red-600 hover:bg-red-700"
              : "bg-emerald-600 hover:bg-emerald-700"
          }
        >
          {Boolean(client.is_active)
            ? "Suspend entire client"
            : "Reactivate client"}
        </Button>
        <Button type="submit" disabled={pending}>
          Save all client details
        </Button>
      </div>
      {!Boolean(client.is_active) && (
        <p className="text-xs font-semibold text-red-600">
          CLIENT LOGIN AND NEW TICKET CREATION ARE DISABLED.
        </p>
      )}
    </form>
  );
}

const emptyLocation = (): LocationInput => ({
  location_code: "",
  location_name: "",
  location_type: "BRANCH",
  address_line_1: "",
  address_line_2: "",
  landmark: "",
  city: "",
  district: "",
  state_name: "",
  postal_code: "",
  gstin: "",
  email: "",
  phone: "",
});

function SitesEditor({
  client,
  pending,
  apply,
  request,
}: {
  client: ClientDetails;
  pending: boolean;
  apply: (operation: Promise<ClientDetails>) => Promise<void>;
  request: Parameters<typeof saveLocation>[0];
}) {
  const [editing, setEditing] = useState<ClientLocation | "new" | null>(null);
  const [form, setForm] = useState<LocationInput>(emptyLocation());
  const select = (site: ClientLocation | "new") => {
    setEditing(site);
    setForm(
      site === "new"
        ? emptyLocation()
        : {
            location_code: site.location_code,
            location_name: site.location_name,
            location_type: site.location_type,
            address_line_1: site.address_line_1,
            address_line_2: site.address_line_2 || "",
            landmark: site.landmark || "",
            city: site.city,
            district: site.district || "",
            state_name: site.state_name,
            postal_code: site.postal_code,
            gstin: site.gstin || "",
            email: site.email || "",
            phone: site.phone || "",
          },
    );
  };
  const submit = async (event: FormEvent) => {
    event.preventDefault();
    try {
      await apply(
        saveLocation(
          request,
          client.client_id,
          form,
          editing === "new" ? undefined : editing?.location_id,
        ),
      );
      setEditing(null);
    } catch {
      /* error shown by parent */
    }
  };
  return (
    <div className="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
      <section>
        <div className="mb-3 flex items-center justify-between">
          <h3 className="font-bold">Client sites</h3>
          <Button onClick={() => select("new")} className="h-9 px-3">
            <Plus className="mr-1.5 h-4 w-4" />
            Add site
          </Button>
        </div>
        <div className="space-y-2">
          {client.locations.map((site) => (
            <div
              key={site.location_id}
              className="rounded-xl border border-[var(--border)] p-3"
            >
              <div className="flex items-start justify-between gap-3">
                <button
                  type="button"
                  onClick={() => select(site)}
                  className="min-w-0 text-left"
                >
                  <span className="block font-semibold">
                    {site.location_name}
                  </span>
                  <span className="text-xs text-[var(--muted)]">
                    {site.location_code} · {site.city}
                  </span>
                </button>
                <button
                  type="button"
                  disabled={pending}
                  onClick={() =>
                    void apply(
                      setLocationActive(
                        request,
                        client.client_id,
                        site.location_id,
                        !Boolean(site.is_active),
                      ),
                    )
                  }
                  className={`rounded-full px-2 py-1 text-[10px] font-bold uppercase ${Boolean(site.is_active) ? "bg-emerald-50 text-emerald-700" : "bg-red-50 text-red-700"}`}
                >
                  {Boolean(site.is_active) ? "Active" : "Suspended"}
                </button>
                <button
                  type="button"
                  disabled={pending}
                  className="text-xs font-semibold text-red-700"
                  onClick={() => {
                    if (
                      window.confirm(
                        `Remove ${site.location_name}? Existing service history is retained.`,
                      )
                    )
                      void apply(
                        request<ClientDetails>(
                          `/customers/${client.client_id}/locations/${site.location_id}`,
                          { method: "DELETE" },
                        ),
                      )
                        .then(() => setEditing(null))
                        .catch(() => {});
                  }}
                >
                  Remove site
                </button>
              </div>
            </div>
          ))}
        </div>
      </section>
      <section>
        {editing ? (
          <form
            onSubmit={submit}
            className="rounded-2xl border border-[var(--border)] p-4"
          >
            <h3 className="mb-4 font-bold">
              {editing === "new" ? "Add new site" : "Modify site"}
            </h3>
            <div className="grid gap-3 sm:grid-cols-2">
              {(
                [
                  "location_code",
                  "location_name",
                  "address_line_1",
                  "address_line_2",
                  "landmark",
                  "city",
                  "district",
                  "state_name",
                  "postal_code",
                  "gstin",
                  "email",
                  "phone",
                ] as const
              ).map((name) => (
                <label
                  key={name}
                  className={name.startsWith("address") ? "sm:col-span-2" : ""}
                >
                  <span className="mb-1 block text-[10px] font-bold uppercase text-[var(--muted)]">
                    {name.replaceAll("_", " ")}
                  </span>
                  <Input
                    value={String(form[name] || "")}
                    type={name === "email" ? "email" : "text"}
                    onChange={(event) =>
                      setForm((value) => ({
                        ...value,
                        [name]: event.target.value,
                      }))
                    }
                    className={name === "email" ? "" : "uppercase"}
                    required={[
                      "location_code",
                      "location_name",
                      "address_line_1",
                      "city",
                      "state_name",
                      "postal_code",
                    ].includes(name)}
                  />
                </label>
              ))}
              <label>
                <span className="mb-1 block text-[10px] font-bold uppercase text-[var(--muted)]">
                  Location type
                </span>
                <select
                  value={form.location_type}
                  onChange={(event) =>
                    setForm((value) => ({
                      ...value,
                      location_type: event.target
                        .value as LocationInput["location_type"],
                    }))
                  }
                  className="h-11 w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 text-sm"
                >
                  <option value="HEAD_OFFICE">HEAD OFFICE</option>
                  <option value="BRANCH">BRANCH</option>
                  <option value="WAREHOUSE">WAREHOUSE</option>
                  <option value="OTHER">OTHER</option>
                </select>
              </label>
            </div>
            <div className="mt-4 flex justify-end gap-2">
              <Button
                type="button"
                onClick={() => setEditing(null)}
                className="bg-slate-500 hover:bg-slate-600"
              >
                Cancel
              </Button>
              <Button type="submit" disabled={pending}>
                Save site
              </Button>
            </div>
          </form>
        ) : (
          <div className="grid min-h-48 place-items-center rounded-2xl border border-dashed border-[var(--border)] text-sm text-[var(--muted)]">
            Choose a site to modify, or add a new one.
          </div>
        )}
      </section>
    </div>
  );
}

const emptyContact = (): ContactInput => ({
  full_name: "",
  designation: "",
  email: "",
  mobile_number: "",
  alternate_number: "",
  role_code: "END_USER",
  has_all_locations: true,
  is_primary_contact: false,
  location_ids: [],
  portal_enabled: false,
  password: "",
});

function ContactsEditor({
  client,
  pending,
  canAssignAdmin,
  apply,
  request,
}: {
  client: ClientDetails;
  pending: boolean;
  canAssignAdmin: boolean;
  apply: (operation: Promise<ClientDetails>) => Promise<void>;
  request: Parameters<typeof saveContact>[0];
}) {
  const [editing, setEditing] = useState<ClientContact | "new" | null>(null);
  const [form, setForm] = useState<ContactInput>(emptyContact());
  const select = (contact: ClientContact | "new") => {
    setEditing(contact);
    setForm(
      contact === "new"
        ? emptyContact()
        : {
            full_name: contact.full_name,
            designation: contact.designation || "",
            email: contact.email,
            mobile_number: contact.mobile_number,
            alternate_number: contact.alternate_number || "",
            role_code: contact.role_code,
            has_all_locations: Boolean(contact.has_all_locations),
            is_primary_contact: Boolean(contact.is_primary_contact),
            location_ids: contact.location_ids || [],
            portal_enabled: Boolean(contact.portal_enabled),
            password: "",
          },
    );
  };
  const submit = async (event: FormEvent) => {
    event.preventDefault();
    try {
      await apply(
        saveContact(
          request,
          client.client_id,
          form,
          editing === "new" ? undefined : editing?.contact_id,
        ),
      );
      setEditing(null);
    } catch {
      /* error shown by parent */
    }
  };
  return (
    <div className="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
      <section>
        <div className="mb-3 flex items-center justify-between">
          <h3 className="font-bold">Contact people</h3>
          <Button onClick={() => select("new")} className="h-9 px-3">
            <Plus className="mr-1.5 h-4 w-4" />
            Add contact
          </Button>
        </div>
        <div className="space-y-2">
          {client.contacts.map((contact) => (
            <div
              key={contact.contact_id}
              className="rounded-xl border border-[var(--border)] p-3"
            >
              <div className="flex items-start justify-between gap-3">
                <button
                  type="button"
                  onClick={() => select(contact)}
                  className="min-w-0 text-left"
                >
                  <span className="block font-semibold">
                    {contact.full_name}
                  </span>
                  <span className="text-xs text-[var(--muted)]">
                    {contact.role_name} · {contact.email}
                  </span>
                  <span
                    className={`mt-1 inline-block rounded-full px-2 py-0.5 text-[9px] font-bold uppercase ${Boolean(contact.portal_enabled) ? "bg-blue-50 text-blue-700" : "bg-slate-100 text-slate-500"}`}
                  >
                    {Boolean(contact.portal_enabled)
                      ? `Portal ${contact.account_status || ""}`
                      : "No portal login"}
                  </span>
                </button>
                <button
                  type="button"
                  disabled={pending}
                  onClick={() =>
                    void apply(
                      setContactActive(
                        request,
                        client.client_id,
                        contact.contact_id,
                        !Boolean(contact.is_active),
                      ),
                    )
                  }
                  className={`rounded-full px-2 py-1 text-[10px] font-bold uppercase ${Boolean(contact.is_active) ? "bg-emerald-50 text-emerald-700" : "bg-red-50 text-red-700"}`}
                >
                  {Boolean(contact.is_active) ? "Active" : "Suspended"}
                </button>
                <button
                  type="button"
                  disabled={pending}
                  className="text-xs font-semibold text-red-700"
                  onClick={() => {
                    if (
                      window.confirm(
                        `Remove ${contact.full_name} and revoke their portal access? Service history is retained.`,
                      )
                    )
                      void apply(
                        request<ClientDetails>(
                          `/customers/${client.client_id}/contacts/${contact.contact_id}`,
                          { method: "DELETE" },
                        ),
                      )
                        .then(() => setEditing(null))
                        .catch(() => {});
                  }}
                >
                  Remove contact
                </button>
              </div>
            </div>
          ))}
        </div>
      </section>
      <section>
        {editing ? (
          <form
            onSubmit={submit}
            className="rounded-2xl border border-[var(--border)] p-4"
          >
            <h3 className="mb-4 font-bold">
              {editing === "new"
                ? "Add contact person"
                : "Modify contact person"}
            </h3>
            <div className="grid gap-3 sm:grid-cols-2">
              {(
                [
                  "full_name",
                  "designation",
                  "email",
                  "mobile_number",
                  "alternate_number",
                ] as const
              ).map((name) => (
                <label key={name}>
                  <span className="mb-1 block text-[10px] font-bold uppercase text-[var(--muted)]">
                    {name.replaceAll("_", " ")}
                  </span>
                  <Input
                    value={String(form[name] || "")}
                    type={name === "email" ? "email" : "text"}
                    onChange={(event) =>
                      setForm((value) => ({
                        ...value,
                        [name]: event.target.value,
                      }))
                    }
                    className={name === "email" ? "" : "uppercase"}
                    required={["full_name", "email", "mobile_number"].includes(
                      name,
                    )}
                  />
                </label>
              ))}
              <label>
                <span className="mb-1 block text-[10px] font-bold uppercase text-[var(--muted)]">
                  Role
                </span>
                <select
                  value={form.role_code}
                  onChange={(event) =>
                    setForm((value) => ({
                      ...value,
                      role_code: event.target
                        .value as ContactInput["role_code"],
                    }))
                  }
                  className="h-11 w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 text-sm"
                >
                  <option value="END_USER">END USER</option>
                  <option value="SYSTEM_OPERATOR">SYSTEM OPERATOR</option>
                  <option value="OWNER">OWNER</option>
                  {canAssignAdmin && (
                    <option value="CLIENT_ADMIN">CLIENT ADMIN</option>
                  )}
                </select>
              </label>
            </div>
            <div className="mt-4 rounded-xl border border-[var(--border)] bg-[var(--surface-soft)] p-3">
              <label className="flex items-start gap-2 text-sm">
                <input
                  type="checkbox"
                  className="mt-0.5"
                  checked={Boolean(form.portal_enabled)}
                  onChange={(event) =>
                    setForm((value) => ({
                      ...value,
                      portal_enabled: event.target.checked,
                      password: "",
                    }))
                  }
                />
                <span>
                  <span className="block font-semibold">
                    Enable client portal login
                  </span>
                  <span className="text-xs text-[var(--muted)]">
                    The contact can sign in using their email address.
                  </span>
                </span>
              </label>
              {form.portal_enabled && (
                <label className="mt-3 block">
                  <span className="mb-1 block text-[10px] font-bold uppercase text-[var(--muted)]">
                    {editing === "new" || editing.account_status === null
                      ? "Temporary password"
                      : "New password (leave blank to keep current)"}
                  </span>
                  <Input
                    type="password"
                    value={form.password || ""}
                    minLength={12}
                    required={
                      editing === "new" || editing.account_status === null
                    }
                    onChange={(event) =>
                      setForm((value) => ({
                        ...value,
                        password: event.target.value,
                      }))
                    }
                    autoComplete="new-password"
                    placeholder="At least 12 characters"
                  />
                  <span className="mt-1 block text-[10px] text-[var(--muted)]">
                    Changing the password signs the contact out from every
                    device.
                  </span>
                </label>
              )}
            </div>
            <label className="mt-4 flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={Boolean(form.has_all_locations)}
                onChange={(event) =>
                  setForm((value) => ({
                    ...value,
                    has_all_locations: event.target.checked,
                  }))
                }
              />
              Access to all sites
            </label>
            {!form.has_all_locations && (
              <div className="mt-3 grid gap-2 sm:grid-cols-2">
                {client.locations.map((site) => (
                  <label
                    key={site.location_id}
                    className="flex items-center gap-2 rounded-lg border border-[var(--border)] p-2 text-xs"
                  >
                    <input
                      type="checkbox"
                      checked={form.location_ids.includes(site.location_id)}
                      onChange={(event) =>
                        setForm((value) => ({
                          ...value,
                          location_ids: event.target.checked
                            ? [...value.location_ids, site.location_id]
                            : value.location_ids.filter(
                                (id) => id !== site.location_id,
                              ),
                        }))
                      }
                    />
                    {site.location_name}
                  </label>
                ))}
              </div>
            )}
            <div className="mt-4 flex justify-end gap-2">
              <Button
                type="button"
                onClick={() => setEditing(null)}
                className="bg-slate-500 hover:bg-slate-600"
              >
                Cancel
              </Button>
              <Button type="submit" disabled={pending}>
                Save contact and portal access
              </Button>
            </div>
          </form>
        ) : (
          <div className="grid min-h-48 place-items-center rounded-2xl border border-dashed border-[var(--border)] text-sm text-[var(--muted)]">
            Choose a contact to modify, or add a new one.
          </div>
        )}
      </section>
    </div>
  );
}
