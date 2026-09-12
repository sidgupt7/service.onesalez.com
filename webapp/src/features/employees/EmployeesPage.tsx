import { useDialog } from "../../lib/use-dialog";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  KeyRound,
  Pencil,
  Plus,
  Search,
  ShieldCheck,
  Trash2,
  UserCog,
  UserX,
  X,
} from "lucide-react";
import { useEffect, useState, type FormEvent } from "react";

import { Pagination } from "../../components/ui/Pagination";
import { useDebounced } from "../../lib/use-debounced";
import { Button } from "../../components/ui/Button";
import { Input } from "../../components/ui/Input";
import { ApiError } from "../../lib/api";
import { useAuth } from "../auth/AuthProvider";
import {
  addEmployee,
  deleteEmployee,
  listEmployees,
  reactivateEmployee,
  setEmployeePassword,
  suspendEmployee,
  updateEmployee,
  type AddEmployeeInput,
  type EmployeeRole,
  type EmployeeSummary,
  type UpdateEmployeeInput,
} from "./employees-api";

const blankEmployee = (): AddEmployeeInput => ({
  employee_code: "",
  full_name: "",
  official_email: "",
  mobile_number: "",
  designation: "",
  department: "",
  joining_date: "",
  password: "",
  roles: ["SERVICE_EMPLOYEE"],
});
const rolesFrom = (employee: EmployeeSummary): EmployeeRole[] =>
  employee.roles
    ?.split(",")
    .filter((role): role is EmployeeRole =>
      ["SERVICE_EMPLOYEE", "SERVICE_ADMIN", "SYSTEM_ADMIN"].includes(role),
    ) || [];

export function EmployeesPage() {
  const { actor, authenticatedRequest } = useAuth();
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const query = useDebounced(search);
  const [adding, setAdding] = useState(false);
  const [editing, setEditing] = useState<EmployeeSummary | null>(null);
  const employees = useQuery({
    queryKey: ["employees", query, page],
    queryFn: () => listEmployees(authenticatedRequest, page, query),
  });
  const refresh = async () =>
    queryClient.invalidateQueries({ queryKey: ["employees"] });
  const create = useMutation({
    mutationFn: (input: AddEmployeeInput) =>
      addEmployee(authenticatedRequest, input),
    onSuccess: async () => {
      await refresh();
      setAdding(false);
    },
  });
  const update = useMutation({
    mutationFn: ({ id, input }: { id: number; input: UpdateEmployeeInput }) =>
      updateEmployee(authenticatedRequest, id, input),
    onSuccess: async () => {
      await refresh();
      setEditing(null);
    },
  });
  const status = useMutation({
    mutationFn: ({
      employee,
      active,
    }: {
      employee: EmployeeSummary;
      active: boolean;
    }) =>
      active
        ? reactivateEmployee(authenticatedRequest, employee.employee_id)
        : suspendEmployee(
            authenticatedRequest,
            employee.employee_id,
            "SUSPENDED BY SYSTEM ADMINISTRATOR",
          ),
    onSuccess: refresh,
  });
  const remove = useMutation({
    mutationFn: (employee: EmployeeSummary) =>
      deleteEmployee(authenticatedRequest, employee.employee_id),
    onSuccess: refresh,
  });
  const password = useMutation({
    mutationFn: ({ id, value }: { id: number; value: string }) =>
      setEmployeePassword(authenticatedRequest, id, value),
  });
  const visible = employees.data?.items || [];
  const actionError = status.error || remove.error;

  const destroy = (employee: EmployeeSummary) => {
    if (
      window.confirm(
        `Delete employee ${employee.full_name}? Their login will be permanently disabled.`,
      )
    )
      remove.mutate(employee);
  };

  return (
    <div className="mx-auto max-w-7xl">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.16em] text-[var(--brand)]">
            Administration
          </p>
          <h1 className="mt-1 text-2xl font-bold sm:text-3xl">Employees</h1>
          <p className="mt-2 text-sm text-[var(--muted)]">
            Add, modify, suspend, reactivate, delete, and reset employee
            accounts.
          </p>
        </div>
        <Button onClick={() => setAdding(true)}>
          <Plus className="mr-2 h-4 w-4" />
          Add employee
        </Button>
      </div>
      {actionError && (
        <div
          role="alert"
          className="mt-5 rounded-xl bg-red-50 p-3 text-sm text-red-800"
        >
          {actionError instanceof ApiError
            ? actionError.message
            : "The employee action could not be completed."}
        </div>
      )}
      <section className="mt-7 rounded-2xl border border-[var(--border)] bg-[var(--surface)] shadow-sm">
        <div className="border-b border-[var(--border)] p-4">
          <label className="relative block max-w-md">
            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]" />
            <Input
              value={search}
              onChange={(event) => {
                setSearch(event.target.value);
                setPage(1);
              }}
              placeholder="Search employee"
              className="pl-10"
            />
          </label>
        </div>
        {employees.isLoading ? (
          <p className="p-12 text-center text-sm text-[var(--muted)]">
            Loading employees…
          </p>
        ) : employees.isError ? (
          <p className="p-12 text-center text-sm text-red-700">
            Employees could not be loaded.
          </p>
        ) : (
          <div className="divide-y divide-[var(--border)]">
            {visible.map((employee) => {
              const isSelf = employee.employee_id === actor?.id;
              const active = employee.employment_status === "ACTIVE";
              return (
                <article
                  key={employee.employee_id}
                  className="flex flex-col gap-4 p-5 lg:flex-row lg:items-center"
                >
                  <div className="grid h-11 w-11 place-items-center rounded-xl bg-blue-50 font-bold text-[var(--brand)]">
                    <UserCog className="h-5 w-5" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <h2 className="font-bold">{employee.full_name}</h2>
                      {isSelf && (
                        <span className="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold uppercase text-blue-700">
                          Your account
                        </span>
                      )}
                      <span
                        className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${active ? "bg-emerald-50 text-emerald-700" : "bg-red-50 text-red-700"}`}
                      >
                        {employee.employment_status}
                      </span>
                    </div>
                    <p className="mt-1 text-xs text-[var(--muted)]">
                      {employee.employee_code} · {employee.official_email}
                    </p>
                    <p className="mt-1 text-xs font-semibold text-[var(--muted)]">
                      {employee.roles?.split(",").join(" · ") || "No role"}
                    </p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <Button
                      onClick={() => setEditing(employee)}
                      className="h-9 px-3"
                    >
                      <Pencil className="mr-1.5 h-3.5 w-3.5" />
                      Edit
                    </Button>
                    <Button
                      disabled={isSelf || status.isPending}
                      onClick={() =>
                        status.mutate({ employee, active: !active })
                      }
                      className={`h-9 px-3 ${active ? "bg-amber-600 hover:bg-amber-700" : "bg-emerald-600 hover:bg-emerald-700"}`}
                    >
                      {active ? (
                        <UserX className="mr-1.5 h-3.5 w-3.5" />
                      ) : (
                        <ShieldCheck className="mr-1.5 h-3.5 w-3.5" />
                      )}
                      {active ? "Suspend" : "Reactivate"}
                    </Button>
                    <Button
                      disabled={isSelf || remove.isPending}
                      onClick={() => destroy(employee)}
                      className="h-9 bg-red-600 px-3 hover:bg-red-700"
                    >
                      <Trash2 className="mr-1.5 h-3.5 w-3.5" />
                      Delete
                    </Button>
                  </div>
                </article>
              );
            })}
          </div>
        )}
      </section>
      {employees.data && (
        <Pagination
          page={page}
          limit={employees.data.limit}
          total={employees.data.total}
          pending={employees.isFetching}
          onPage={setPage}
        />
      )}
      {adding && (
        <EmployeeDialog
          mode="add"
          pending={create.isPending}
          error={create.error}
          onClose={() => !create.isPending && setAdding(false)}
          onSubmit={(input) => create.mutate(input as AddEmployeeInput)}
        />
      )}
      {editing && (
        <EmployeeDialog
          mode="edit"
          employee={editing}
          pending={update.isPending || password.isPending}
          error={update.error || password.error}
          onClose={() =>
            !update.isPending && !password.isPending && setEditing(null)
          }
          onSubmit={(input) =>
            update.mutate({ id: editing.employee_id, input })
          }
          onPassword={(value) =>
            password.mutate(
              { id: editing.employee_id, value },
              { onSuccess: () => setEditing(null) },
            )
          }
        />
      )}
    </div>
  );
}

function EmployeeDialog({
  mode,
  employee,
  pending,
  error,
  onClose,
  onSubmit,
  onPassword,
}: {
  mode: "add" | "edit";
  employee?: EmployeeSummary;
  pending: boolean;
  error: Error | null;
  onClose: () => void;
  onSubmit: (input: AddEmployeeInput & UpdateEmployeeInput) => void;
  onPassword?: (password: string) => void;
}) {
  const dialogRef = useDialog(() => {
    if (!pending) onClose();
  });
  const [form, setForm] = useState<AddEmployeeInput>(blankEmployee());
  const [newPassword, setNewPassword] = useState("");
  useEffect(() => {
    if (employee)
      setForm({
        employee_code: employee.employee_code,
        full_name: employee.full_name,
        official_email: employee.official_email,
        mobile_number: employee.mobile_number || "",
        designation: employee.designation || "",
        department: employee.department || "",
        joining_date: employee.joining_date || "",
        password: "",
        roles: rolesFrom(employee),
      });
  }, [employee]);
  const submit = (event: FormEvent) => {
    event.preventDefault();
    onSubmit(form);
  };
  const toggleRole = (role: EmployeeRole) =>
    setForm((value) => ({
      ...value,
      roles: value.roles.includes(role)
        ? value.roles.filter((item) => item !== role)
        : [...value.roles, role],
    }));
  return (
    <div
      className="fixed inset-0 z-[70] flex items-end justify-center bg-slate-950/55 backdrop-blur-sm sm:items-center sm:p-5"
      ref={dialogRef}
      tabIndex={-1}
      role="dialog"
      aria-modal="true"
      aria-labelledby="employee-form-title"
    >
      <form
        onSubmit={submit}
        className="max-h-[100dvh] w-full max-w-2xl overflow-y-auto rounded-t-3xl bg-[var(--surface)] p-5 shadow-2xl sm:max-h-[92dvh] sm:rounded-3xl sm:p-7"
      >
        <div className="mb-6 flex items-start justify-between">
          <div>
            <p className="text-xs font-bold uppercase tracking-[0.16em] text-[var(--brand)]">
              Employee account
            </p>
            <h2 id="employee-form-title" className="mt-1 text-xl font-bold">
              {mode === "add" ? "Add employee" : "Modify employee"}
            </h2>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close employee form"
            className="grid h-9 w-9 place-items-center rounded-xl hover:bg-[var(--surface-soft)]"
          >
            <X className="h-5 w-5" />
          </button>
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          {(
            [
              "employee_code",
              "full_name",
              "official_email",
              "mobile_number",
              "designation",
              "department",
            ] as const
          ).map((name) => (
            <label key={name}>
              <span className="mb-1 block text-xs font-bold uppercase text-[var(--muted)]">
                {name.replaceAll("_", " ")}
              </span>
              <Input
                type={name === "official_email" ? "email" : "text"}
                value={form[name] || ""}
                onChange={(event) =>
                  setForm((value) => ({ ...value, [name]: event.target.value }))
                }
                required={[
                  "employee_code",
                  "full_name",
                  "official_email",
                ].includes(name)}
              />
            </label>
          ))}
          <label>
            <span className="mb-1 block text-xs font-bold uppercase text-[var(--muted)]">
              Joining date
            </span>
            <Input
              type="date"
              value={form.joining_date || ""}
              onChange={(event) =>
                setForm((value) => ({
                  ...value,
                  joining_date: event.target.value,
                }))
              }
            />
          </label>
          {mode === "add" && (
            <label>
              <span className="mb-1 block text-xs font-bold uppercase text-[var(--muted)]">
                Temporary password
              </span>
              <Input
                type="password"
                minLength={12}
                required
                value={form.password}
                onChange={(event) =>
                  setForm((value) => ({
                    ...value,
                    password: event.target.value,
                  }))
                }
                autoComplete="new-password"
                placeholder="At least 12 characters"
              />
            </label>
          )}
        </div>
        <fieldset className="mt-5">
          <legend className="text-xs font-bold uppercase text-[var(--muted)]">
            Roles
          </legend>
          <div className="mt-2 grid gap-2 sm:grid-cols-3">
            {(
              [
                "SERVICE_EMPLOYEE",
                "SERVICE_ADMIN",
                "SYSTEM_ADMIN",
              ] as EmployeeRole[]
            ).map((role) => (
              <label
                key={role}
                className="flex items-center gap-2 rounded-xl border border-[var(--border)] p-3 text-xs font-semibold"
              >
                <input
                  type="checkbox"
                  checked={form.roles.includes(role)}
                  onChange={() => toggleRole(role)}
                />
                {role.replaceAll("_", " ")}
              </label>
            ))}
          </div>
        </fieldset>
        {mode === "edit" && (
          <div className="mt-5 rounded-2xl border border-[var(--border)] bg-[var(--surface-soft)] p-4">
            <h3 className="flex items-center gap-2 text-sm font-bold">
              <KeyRound className="h-4 w-4 text-[var(--brand)]" />
              Set a new employee password
            </h3>
            <p className="mt-1 text-xs text-[var(--muted)]">
              This signs the employee out and removes their saved PIN logins.
            </p>
            <div className="mt-3 flex flex-col gap-2 sm:flex-row">
              <Input
                type="password"
                minLength={12}
                value={newPassword}
                onChange={(event) => setNewPassword(event.target.value)}
                autoComplete="new-password"
                placeholder="At least 12 characters"
              />
              <Button
                type="button"
                disabled={pending || newPassword.length < 12}
                onClick={() => onPassword?.(newPassword)}
                className="shrink-0"
              >
                Set password
              </Button>
            </div>
          </div>
        )}
        {error && (
          <div
            role="alert"
            className="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-800"
          >
            {error instanceof ApiError
              ? error.message
              : "The employee could not be saved."}
          </div>
        )}
        <div className="mt-6 flex justify-end gap-2">
          <Button
            type="button"
            onClick={onClose}
            className="bg-slate-500 hover:bg-slate-600"
          >
            Cancel
          </Button>
          <Button type="submit" disabled={pending || form.roles.length === 0}>
            {pending
              ? "Saving…"
              : mode === "add"
                ? "Create employee account"
                : "Save employee details"}
          </Button>
        </div>
      </form>
    </div>
  );
}
