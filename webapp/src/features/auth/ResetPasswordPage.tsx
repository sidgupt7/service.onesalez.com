import { KeyRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Link, useSearchParams } from 'react-router-dom';

import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import { ApiError } from '../../lib/api';
import { resetPassword, type AuthRealm } from './auth-api';

export function ResetPasswordPage() {
  const [params] = useSearchParams();
  const realm: AuthRealm = params.get('realm') === 'client' ? 'client' : 'employee';
  const token = params.get('token') || '';
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [pending, setPending] = useState(false);
  const [success, setSuccess] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (password !== confirm) { setError('The passwords do not match.'); return; }
    setPending(true);
    setError(null);
    try { await resetPassword(token, password, realm); setSuccess(true); }
    catch (caught) { setError(caught instanceof ApiError ? caught.message : 'The password could not be reset.'); }
    finally { setPending(false); }
  };
  return <main className="grid min-h-screen place-items-center bg-[var(--canvas)] p-4 text-[var(--text)]"><section className="w-full max-w-md rounded-3xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-xl sm:p-8"><img src="/onesalez-logo.png" alt="ONESALEZ" className="h-11 w-11 rounded-xl object-contain" /><div className="mt-6 grid h-11 w-11 place-items-center rounded-xl bg-blue-50 text-[var(--brand)]"><KeyRound className="h-5 w-5" /></div><h1 className="mt-4 text-2xl font-bold">Choose a new password</h1><p className="mt-2 text-sm text-[var(--muted)]">Resetting the password removes existing sessions and saved PIN logins.</p>{success ? <div className="mt-6"><div className="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900">Password updated successfully.</div><Link to="/login" className="mt-5 flex h-11 items-center justify-center rounded-xl bg-[var(--brand)] text-sm font-semibold text-white">Return to login</Link></div> : token === '' ? <div className="mt-6 rounded-xl bg-red-50 p-4 text-sm text-red-800">This reset link is incomplete.</div> : <form onSubmit={submit} className="mt-6 space-y-4"><PasswordField label="New password" value={password} onChange={setPassword} /><PasswordField label="Confirm password" value={confirm} onChange={setConfirm} />{error && <div role="alert" className="rounded-xl bg-red-50 p-3 text-sm text-red-800">{error}</div>}<Button type="submit" disabled={pending} className="w-full">{pending ? 'Updating…' : 'Update password'}</Button></form>}</section></main>;
}

function PasswordField({ label, value, onChange }: { label: string; value: string; onChange: (value: string) => void }) {
  return <label className="block"><span className="mb-1.5 block text-xs font-bold uppercase text-[var(--muted)]">{label}</span><Input type="password" required minLength={12} autoComplete="new-password" value={value} onChange={(event) => onChange(event.target.value)} placeholder="At least 12 characters" /></label>;
}
