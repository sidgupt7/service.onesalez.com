import { Mail, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';

import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import { ApiError } from '../../lib/api';
import { requestPasswordReset, type AuthRealm } from './auth-api';

export function ForgotPasswordDialog({ initialRealm, initialEmail, onClose }: { initialRealm: AuthRealm; initialEmail: string; onClose: () => void }) {
  const [realm, setRealm] = useState<AuthRealm>(initialRealm);
  const [email, setEmail] = useState(initialEmail.trim().toLowerCase());
  const [pending, setPending] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const submit = async (event: FormEvent) => {
    event.preventDefault();
    setPending(true);
    setError(null);
    try {
      const result = await requestPasswordReset(email, realm);
      setMessage(result.message);
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The reset request could not be submitted.');
    } finally {
      setPending(false);
    }
  };
  return <div className="fixed inset-0 z-[90] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="forgot-password-title"><form onSubmit={submit} className="w-full max-w-md rounded-3xl bg-[var(--surface)] p-5 shadow-2xl sm:p-7"><div className="flex items-start justify-between"><div><div className="grid h-11 w-11 place-items-center rounded-xl bg-blue-50 text-[var(--brand)]"><Mail className="h-5 w-5" /></div><h2 id="forgot-password-title" className="mt-4 text-xl font-bold">Reset password</h2><p className="mt-1 text-sm text-[var(--muted)]">We will send a time-limited reset link if the account exists.</p></div><button type="button" onClick={onClose} aria-label="Close reset form" className="grid h-9 w-9 place-items-center rounded-xl hover:bg-[var(--surface-soft)]"><X className="h-5 w-5" /></button></div>{message ? <div className="mt-6 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900">{message}</div> : <><div className="mt-6 grid grid-cols-2 rounded-xl bg-[var(--surface-soft)] p-1"><RealmChoice active={realm === 'client'} label="Client" onClick={() => setRealm('client')} /><RealmChoice active={realm === 'employee'} label="Employee" onClick={() => setRealm('employee')} /></div><label className="mt-4 block"><span className="mb-1.5 block text-xs font-bold uppercase text-[var(--muted)]">Email address</span><Input type="email" required autoComplete="email" value={email} onChange={(event) => setEmail(event.target.value.toLowerCase())} placeholder="name@company.com" /></label>{error && <div role="alert" className="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-800">{error}</div>}</>}<div className="mt-6 flex justify-end gap-2"><Button type="button" onClick={onClose} className="bg-slate-500 hover:bg-slate-600">{message ? 'Close' : 'Cancel'}</Button>{!message && <Button type="submit" disabled={pending}>{pending ? 'Submitting…' : 'Send reset link'}</Button>}</div></form></div>;
}

function RealmChoice({ active, label, onClick }: { active: boolean; label: string; onClick: () => void }) {
  return <button type="button" onClick={onClick} className={`h-10 rounded-lg text-sm font-semibold ${active ? 'bg-[var(--surface)] text-[var(--brand)] shadow-sm' : 'text-[var(--muted)]'}`}>{label}</button>;
}
