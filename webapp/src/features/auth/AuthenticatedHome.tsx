import { LogOut, ShieldCheck } from "lucide-react";
import { useNavigate } from "react-router-dom";

import { Button } from "../../components/ui/Button";
import { useAuth } from "./AuthProvider";

export function AuthenticatedHome({
  title,
  description,
}: {
  title: string;
  description: string;
}) {
  const { actor, signOut } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await signOut();
    navigate("/login", { replace: true });
  };

  return (
    <main className="min-h-screen bg-[var(--canvas)] px-5 py-10 text-[var(--text)] sm:px-8">
      <section className="mx-auto max-w-3xl rounded-3xl border border-[var(--border)] bg-[var(--surface)] p-7 shadow-sm sm:p-10">
        <div className="flex flex-col justify-between gap-6 sm:flex-row sm:items-start">
          <div>
            <div className="mb-5 inline-flex items-center gap-2 rounded-full bg-[var(--surface-soft)] px-3 py-1.5 text-xs font-semibold text-[var(--brand)]">
              <ShieldCheck aria-hidden="true" className="h-4 w-4" />
              Secure session active
            </div>
            <h1 className="text-3xl font-semibold tracking-tight">{title}</h1>
            <p className="mt-3 max-w-xl text-sm leading-6 text-[var(--muted)]">
              {description}
            </p>
          </div>
          <Button
            onClick={handleLogout}
            className="gap-2 bg-transparent text-[var(--brand)] shadow-none ring-1 ring-[var(--border)] hover:bg-[var(--surface-soft)]"
          >
            <LogOut aria-hidden="true" className="h-4 w-4" />
            Logout
          </Button>
        </div>
        <dl className="mt-10 grid gap-4 rounded-2xl bg-[var(--surface-soft)] p-5 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-[var(--muted)]">Signed in as</dt>
            <dd className="mt-1 font-semibold">{actor?.email}</dd>
          </div>
          <div>
            <dt className="text-[var(--muted)]">Roles</dt>
            <dd className="mt-1 font-semibold">
              {actor?.roles.join(", ") || "Standard access"}
            </dd>
          </div>
        </dl>
      </section>
    </main>
  );
}
