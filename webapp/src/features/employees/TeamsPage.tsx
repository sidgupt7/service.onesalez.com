import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { Button } from "../../components/ui/Button";
import { Input } from "../../components/ui/Input";
import { useAuth } from "../auth/AuthProvider";

interface Team {
  team_id: number;
  team_name: string;
  description: string | null;
  members: Array<{
    employee_id: number;
    full_name: string;
    is_team_lead: number | boolean;
  }>;
}
interface Employee {
  employee_id: number;
  full_name: string;
}
export function TeamsPage() {
  const { authenticatedRequest: request } = useAuth();
  const cache = useQueryClient();
  const [editing, setEditing] = useState<number | null>(null);
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [employee, setEmployee] = useState<Record<number, string>>({});
  const [lead, setLead] = useState<Record<number, boolean>>({});
  const teams = useQuery({
    queryKey: ["teams"],
    queryFn: () => request<Team[]>("/teams"),
  });
  const employees = useQuery({
    queryKey: ["team-employees"],
    queryFn: async () => {
      const all: Employee[] = [];
      for (let page = 1; ; page++) {
        const rows = await request<Employee[]>(`/users?page=${page}&limit=100`);
        all.push(...rows);
        if (rows.length < 100) break;
      }
      return all;
    },
  });
  const mutation = useMutation({
    mutationFn: ({
      path,
      method,
      data,
    }: {
      path: string;
      method: string;
      data?: unknown;
    }) =>
      request(path, {
        method,
        ...(data ? { body: JSON.stringify(data) } : {}),
      }),
    onSuccess: async () => {
      await cache.invalidateQueries({ queryKey: ["teams"] });
    },
  });
  const save = async () => {
    await mutation.mutateAsync({
      path: editing ? `/teams/${editing}` : "/teams",
      method: editing ? "PUT" : "POST",
      data: { team_name: name, description },
    });
    setEditing(null);
    setName("");
    setDescription("");
  };
  return (
    <main className="mx-auto max-w-5xl">
      <h1 className="text-2xl font-bold">Teams</h1>
      <p className="mt-2 text-sm text-[var(--muted)]">
        Organize employees and maintain team membership.
      </p>
      <form
        className="my-5 grid gap-3 rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 sm:grid-cols-2"
        onSubmit={(event) => {
          event.preventDefault();
          void save().catch(() => {});
        }}
      >
        <h2 className="font-bold sm:col-span-2">
          {editing ? "Edit team" : "Create team"}
        </h2>
        <label className="text-sm">
          Team name
          <Input
            required
            maxLength={150}
            value={name}
            onChange={(event) => setName(event.target.value)}
          />
        </label>
        <label className="text-sm">
          Description
          <Input
            maxLength={500}
            value={description}
            onChange={(event) => setDescription(event.target.value)}
          />
        </label>
        <div className="flex gap-2">
          <Button type="submit" disabled={mutation.isPending}>
            Save team
          </Button>
          {editing && (
            <Button
              type="button"
              onClick={() => {
                setEditing(null);
                setName("");
                setDescription("");
              }}
            >
              Cancel edit
            </Button>
          )}
        </div>
      </form>
      {[teams.error, employees.error, mutation.error]
        .filter(Boolean)
        .map((error, index) => (
          <p role="alert" key={index} className="my-3 text-red-700">
            {error?.message}
          </p>
        ))}
      {teams.isLoading && <p role="status">Loading teams…</p>}
      {teams.data?.length === 0 && (
        <p>No teams yet. Create your first team above.</p>
      )}
      <div className="space-y-4">
        {teams.data?.map((team) => (
          <article
            key={team.team_id}
            className="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5"
          >
            <h2 className="font-bold">{team.team_name}</h2>
            <p className="mt-1 text-sm text-[var(--muted)]">
              {team.description}
            </p>
            <div className="my-3 flex gap-2">
              <Button
                onClick={() => {
                  setEditing(team.team_id);
                  setName(team.team_name);
                  setDescription(team.description || "");
                  window.scrollTo({ top: 0 });
                }}
              >
                Edit team
              </Button>
              <Button
                disabled={mutation.isPending}
                onClick={() => {
                  if (
                    window.confirm(
                      `Remove ${team.team_name} and its memberships?`,
                    )
                  )
                    mutation.mutate({
                      path: `/teams/${team.team_id}`,
                      method: "DELETE",
                    });
                }}
              >
                Remove team
              </Button>
            </div>
            <ul className="divide-y divide-[var(--border)]">
              {team.members.map((member) => (
                <li
                  key={member.employee_id}
                  className="flex flex-wrap items-center justify-between gap-2 py-3 text-sm"
                >
                  <span>
                    {member.full_name}
                    {Boolean(member.is_team_lead) ? " · Team lead" : ""}
                  </span>
                  <Button
                    disabled={mutation.isPending}
                    onClick={() =>
                      mutation.mutate({
                        path: `/teams/${team.team_id}/members/${member.employee_id}`,
                        method: "DELETE",
                      })
                    }
                  >
                    Remove member
                  </Button>
                </li>
              ))}
            </ul>
            <form
              className="mt-3 flex flex-wrap items-center gap-3"
              onSubmit={(event) => {
                event.preventDefault();
                mutation.mutate({
                  path: `/teams/${team.team_id}/members`,
                  method: "POST",
                  data: {
                    employee_id: Number(employee[team.team_id]),
                    is_team_lead: Boolean(lead[team.team_id]),
                  },
                });
              }}
            >
              <select
                required
                aria-label={`Employee for ${team.team_name}`}
                value={employee[team.team_id] || ""}
                onChange={(event) =>
                  setEmployee((values) => ({
                    ...values,
                    [team.team_id]: event.target.value,
                  }))
                }
                className="h-11 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 text-sm"
              >
                <option value="">Choose employee</option>
                {employees.data?.map((person) => (
                  <option key={person.employee_id} value={person.employee_id}>
                    {person.full_name}
                  </option>
                ))}
              </select>
              <label className="text-sm">
                <input
                  type="checkbox"
                  checked={lead[team.team_id] || false}
                  onChange={(event) =>
                    setLead((values) => ({
                      ...values,
                      [team.team_id]: event.target.checked,
                    }))
                  }
                />{" "}
                Team lead
              </label>
              <Button disabled={mutation.isPending} type="submit">
                Add / update member
              </Button>
            </form>
          </article>
        ))}
      </div>
    </main>
  );
}
