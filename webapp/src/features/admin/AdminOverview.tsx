import { CheckCircle2, LockKeyhole, Network, UsersRound } from 'lucide-react';

import { useAuth } from '../auth/AuthProvider';

export function AdminOverview() {
  const { actor } = useAuth();
  const accessLabel = actor?.roles.includes('SYSTEM_ADMIN')
    ? 'System administrator'
    : actor?.roles.includes('SERVICE_ADMIN')
      ? 'Service administrator'
      : 'Service employee';

  return (
    <main className="mx-auto max-w-7xl">
      <div className="text-xs font-medium text-[var(--muted)]">Home <span className="mx-1.5">/</span> Overview</div>
      <div className="mt-4 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Employee workspace</h1>
          <p className="mt-2 text-sm leading-6 text-[var(--muted)]">The secure foundation is active. We can now add business modules one focused step at a time.</p>
        </div>
        <div className="inline-flex w-fit items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
          <CheckCircle2 aria-hidden="true" className="h-4 w-4" />
          {accessLabel}
        </div>
      </div>

      <section aria-label="Workspace status" className="mt-8 grid gap-4 md:grid-cols-3">
        <StatusCard icon={LockKeyhole} label="Authentication" value="Secure and active" detail="HttpOnly session rotation enabled" />
        <StatusCard icon={Network} label="Backend API" value="Connected" detail="Local API and database verified" />
        <StatusCard icon={UsersRound} label="Signed in account" value={actor?.displayName || 'Administrator'} detail={actor?.email ?? ''} />
      </section>

      <section className="mt-6">
        <article className="max-w-xl rounded-3xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-sm sm:p-7">
          <h2 className="text-base font-semibold">Your access</h2>
          <dl className="mt-5 space-y-4 text-sm">
            <div><dt className="text-[var(--muted)]">Role</dt><dd className="mt-1 font-semibold">{actor?.roles.join(', ')}</dd></div>
            <div><dt className="text-[var(--muted)]">Email</dt><dd className="mt-1 break-all font-semibold">{actor?.email}</dd></div>
            <div><dt className="text-[var(--muted)]">Session</dt><dd className="mt-1 font-semibold text-emerald-700 dark:text-emerald-300">Protected</dd></div>
          </dl>
        </article>
      </section>
    </main>
  );
}

interface StatusCardProps {
  icon: typeof LockKeyhole;
  label: string;
  value: string;
  detail: string;
}

function StatusCard({ icon: Icon, label, value, detail }: StatusCardProps) {
  return (
    <article className="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 shadow-sm">
      <div className="flex items-center gap-3">
        <div className="grid h-10 w-10 place-items-center rounded-xl bg-[var(--surface-soft)] text-[var(--brand)]"><Icon aria-hidden="true" className="h-5 w-5" /></div>
        <p className="text-xs font-semibold uppercase tracking-wide text-[var(--muted)]">{label}</p>
      </div>
      <p className="mt-5 text-lg font-semibold">{value}</p>
      <p className="mt-1 text-xs leading-5 text-[var(--muted)]">{detail}</p>
    </article>
  );
}
