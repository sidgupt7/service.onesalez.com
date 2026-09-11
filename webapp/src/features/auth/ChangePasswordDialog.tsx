import { KeyRound, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';

import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import { ApiError } from '../../lib/api';
import { useAuth } from './AuthProvider';
import { forgetTrustedProfilesForActor } from './auth-api';

export function ChangePasswordDialog({ onClose }: { onClose: () => void }) {
  const { actor, authenticatedRequest, signOut } = useAuth();
  const navigate = useNavigate();
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (newPassword !== confirmPassword) {
      setError('The new passwords do not match.');
      return;
    }
    setPending(true);
    setError(null);
    try {
      await authenticatedRequest<{ message: string }>('/auth/change-password', {
        method: 'POST',
        body: JSON.stringify({ current_password: currentPassword, new_password: newPassword }),
      });
      if (actor?.email) forgetTrustedProfilesForActor(actor.email);
      await signOut();
      navigate('/login', { replace: true, state: { passwordChanged: true } });
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The password could not be changed.');
      setPending(false);
    }
  };

  return <div className="fixed inset-0 z-[90] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="change-password-title"><form onSubmit={submit} className="w-full max-w-md rounded-3xl bg-[var(--surface)] p-5 shadow-2xl sm:p-7"><div className="flex items-start justify-between gap-4"><div><div className="grid h-11 w-11 place-items-center rounded-xl bg-blue-50 text-[var(--brand)]"><KeyRound className="h-5 w-5" /></div><h2 id="change-password-title" className="mt-4 text-xl font-bold">Change password</h2><p className="mt-1 text-sm text-[var(--muted)]">This signs you out and removes PIN login for this account on trusted devices.</p></div><button type="button" onClick={onClose} disabled={pending} aria-label="Close password form" className="grid h-9 w-9 shrink-0 place-items-center rounded-xl hover:bg-[var(--surface-soft)]"><X className="h-5 w-5" /></button></div><div className="mt-6 space-y-4"><PasswordField label="Current password" value={currentPassword} onChange={setCurrentPassword} autoComplete="current-password" minimum={1} /><PasswordField label="New password" value={newPassword} onChange={setNewPassword} autoComplete="new-password" minimum={12} /><PasswordField label="Confirm new password" value={confirmPassword} onChange={setConfirmPassword} autoComplete="new-password" minimum={12} /></div>{error && <div role="alert" className="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-800">{error}</div>}<div className="mt-6 flex justify-end gap-2"><Button type="button" onClick={onClose} disabled={pending} className="bg-slate-500 hover:bg-slate-600">Cancel</Button><Button type="submit" disabled={pending}>{pending ? 'Changing…' : 'Change password'}</Button></div></form></div>;
}

function PasswordField({ label, value, onChange, autoComplete, minimum }: { label: string; value: string; onChange: (value: string) => void; autoComplete: string; minimum: number }) {
  return <label className="block"><span className="mb-1.5 block text-xs font-bold uppercase text-[var(--muted)]">{label}</span><Input type="password" minLength={minimum} required value={value} onChange={(event) => onChange(event.target.value)} autoComplete={autoComplete} placeholder={minimum === 1 ? 'Enter current password' : 'At least 12 characters'} /></label>;
}
