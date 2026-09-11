import { zodResolver } from '@hookform/resolvers/zod';
import { Check, ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';

import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import type { ClientOnboardingInput } from './clients-api';

const schema = z.object({
  clientCode: z.string().trim().min(1, 'Client code is required').max(30),
  legalName: z.string().trim().min(2, 'Legal name is required').max(200),
  displayName: z.string().trim().max(200),
  gstin: z.string().trim().max(15, 'GSTIN cannot exceed 15 characters'),
  primaryPhone: z.string().trim().max(20),
  locationCode: z.string().trim().min(1, 'Location code is required').max(30),
  locationName: z.string().trim().min(2, 'Location name is required').max(200),
  locationType: z.enum(['HEAD_OFFICE', 'BRANCH', 'WAREHOUSE', 'OTHER']),
  addressLine1: z.string().trim().min(3, 'Address is required').max(250),
  addressLine2: z.string().trim().max(250),
  city: z.string().trim().min(2, 'City is required').max(100),
  stateName: z.string().trim().min(2, 'State is required').max(100),
  postalCode: z.string().trim().regex(/^\d{6}$/, 'Enter a 6-digit PIN code'),
  adminName: z.string().trim().min(2, 'Administrator name is required').max(200),
  designation: z.string().trim().max(150),
  email: z.email('Enter a valid email address'),
  mobileNumber: z.string().trim().min(8, 'Enter a valid mobile number').max(20),
  password: z.string().min(12, 'Temporary password must be at least 12 characters'),
});

type FormValues = z.infer<typeof schema>;

const stepFields: Array<Array<keyof FormValues>> = [
  ['clientCode', 'legalName', 'displayName', 'gstin', 'primaryPhone'],
  ['locationCode', 'locationName', 'locationType', 'addressLine1', 'addressLine2', 'city', 'stateName', 'postalCode'],
  ['adminName', 'designation', 'email', 'mobileNumber', 'password'],
];

interface Props {
  pending: boolean;
  serverError: string | null;
  onClose: () => void;
  onSubmit: (input: ClientOnboardingInput) => Promise<void>;
}

export function ClientOnboardingWizard({ pending, serverError, onClose, onSubmit }: Props) {
  const [step, setStep] = useState(0);
  const { register, handleSubmit, trigger, formState: { errors } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { locationType: 'HEAD_OFFICE' },
  });

  const next = async () => {
    if (await trigger(stepFields[step])) setStep((value) => Math.min(2, value + 1));
  };

  const submit = async (values: FormValues) => onSubmit({
    client: {
      client_code: values.clientCode,
      legal_name: values.legalName,
      display_name: values.displayName || undefined,
      gstin: values.gstin || undefined,
      primary_phone: values.primaryPhone || undefined,
    },
    location: {
      location_code: values.locationCode,
      location_name: values.locationName,
      location_type: values.locationType,
      address_line_1: values.addressLine1,
      address_line_2: values.addressLine2 || undefined,
      city: values.city,
      state_name: values.stateName,
      postal_code: values.postalCode,
    },
    administrator: {
      full_name: values.adminName,
      designation: values.designation || undefined,
      email: values.email,
      mobile_number: values.mobileNumber,
      password: values.password,
    },
  });

  return (
    <div className="fixed inset-0 z-[70] flex items-end justify-center bg-slate-950/55 p-0 backdrop-blur-sm sm:items-center sm:p-5" role="dialog" aria-modal="true" aria-labelledby="onboarding-title">
      <div className="flex max-h-[100dvh] w-full max-w-3xl flex-col overflow-hidden rounded-t-3xl bg-[var(--surface)] shadow-2xl sm:max-h-[92dvh] sm:rounded-3xl">
        <div className="flex items-start justify-between border-b border-[var(--border)] px-5 py-5 sm:px-7">
          <div>
            <p className="text-xs font-bold uppercase tracking-[0.16em] text-[var(--brand)]">New client</p>
            <h2 id="onboarding-title" className="mt-1 text-xl font-bold">Client onboarding</h2>
            <p className="mt-1 text-sm text-[var(--muted)]">Company, first location, and client administrator.</p>
          </div>
          <button type="button" aria-label="Close onboarding" onClick={onClose} disabled={pending} className="grid h-10 w-10 place-items-center rounded-xl text-[var(--muted)] hover:bg-[var(--surface-soft)]"><X className="h-5 w-5" /></button>
        </div>

        <div className="grid grid-cols-3 border-b border-[var(--border)] px-5 py-4 sm:px-7">
          {['Business', 'Location', 'Administrator'].map((label, index) => (
            <div key={label} className="flex items-center gap-2">
              <span className={`grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-bold ${index <= step ? 'bg-[var(--brand)] text-white' : 'bg-[var(--surface-soft)] text-[var(--muted)]'}`}>{index < step ? <Check className="h-4 w-4" /> : index + 1}</span>
              <span className={`hidden text-xs font-semibold sm:block ${index <= step ? 'text-[var(--text)]' : 'text-[var(--muted)]'}`}>{label}</span>
            </div>
          ))}
        </div>

        <form onSubmit={handleSubmit(submit)} className="flex min-h-0 flex-1 flex-col">
          <div className="overflow-y-auto px-5 py-6 sm:px-7">
            {step === 0 && <Section title="Business identity" description="The legal client record used throughout the CRM.">
              <Field label="Client code" required error={errors.clientCode?.message}><Input {...register('clientCode')} placeholder="e.g. ACME001" autoFocus /></Field>
              <Field label="Legal business name" required error={errors.legalName?.message}><Input {...register('legalName')} placeholder="Name on registration documents" /></Field>
              <Field label="Display name" error={errors.displayName?.message}><Input {...register('displayName')} placeholder="Short trading name (optional)" /></Field>
              <Field label="GSTIN" error={errors.gstin?.message}><Input {...register('gstin')} placeholder="Optional" maxLength={15} /></Field>
              <Field label="Primary phone" error={errors.primaryPhone?.message}><Input {...register('primaryPhone')} placeholder="Optional" /></Field>
            </Section>}

            {step === 1 && <Section title="First location" description="This is created as the primary location. More locations can be added later.">
              <Field label="Location code" required error={errors.locationCode?.message}><Input {...register('locationCode')} placeholder="e.g. HO01" autoFocus /></Field>
              <Field label="Location name" required error={errors.locationName?.message}><Input {...register('locationName')} placeholder="Head Office" /></Field>
              <Field label="Location type" required error={errors.locationType?.message}>
                <select {...register('locationType')} className="h-11 w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3.5 text-sm outline-none focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--focus)]">
                  <option value="HEAD_OFFICE">Head office</option><option value="BRANCH">Branch</option><option value="WAREHOUSE">Warehouse</option><option value="OTHER">Other</option>
                </select>
              </Field>
              <Field label="Address line 1" required error={errors.addressLine1?.message} wide><Input {...register('addressLine1')} /></Field>
              <Field label="Address line 2" error={errors.addressLine2?.message} wide><Input {...register('addressLine2')} /></Field>
              <Field label="City" required error={errors.city?.message}><Input {...register('city')} /></Field>
              <Field label="State" required error={errors.stateName?.message}><Input {...register('stateName')} /></Field>
              <Field label="PIN code" required error={errors.postalCode?.message}><Input {...register('postalCode')} inputMode="numeric" maxLength={6} /></Field>
            </Section>}

            {step === 2 && <Section title="Client administrator" description="This person can sign in and manage other contacts for this client.">
              <Field label="Full name" required error={errors.adminName?.message}><Input {...register('adminName')} autoFocus /></Field>
              <Field label="Designation" error={errors.designation?.message}><Input {...register('designation')} placeholder="Owner, IT Manager…" /></Field>
              <Field label="Email (login ID)" required error={errors.email?.message}><Input {...register('email')} type="email" autoComplete="off" /></Field>
              <Field label="Mobile number" required error={errors.mobileNumber?.message}><Input {...register('mobileNumber')} inputMode="tel" /></Field>
              <Field label="Temporary password" required error={errors.password?.message} wide><Input {...register('password')} type="password" autoComplete="new-password" /><p className="mt-1.5 text-xs text-[var(--muted)]">At least 12 characters. Share it securely with the client administrator.</p></Field>
            </Section>}
            {serverError && <div role="alert" className="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{serverError}</div>}
          </div>

          <div className="flex items-center justify-between border-t border-[var(--border)] px-5 py-4 sm:px-7">
            <Button type="button" onClick={() => step === 0 ? onClose() : setStep((value) => value - 1)} disabled={pending} className="bg-transparent px-3 text-[var(--muted)] shadow-none hover:bg-[var(--surface-soft)] hover:text-[var(--text)]"><ChevronLeft className="mr-1 h-4 w-4" />{step === 0 ? 'Cancel' : 'Back'}</Button>
            {step < 2
              ? <Button type="button" onClick={next}>Continue<ChevronRight className="ml-1 h-4 w-4" /></Button>
              : <Button type="submit" disabled={pending}>{pending ? 'Creating client…' : 'Create client'}</Button>}
          </div>
        </form>
      </div>
    </div>
  );
}

function Section({ title, description, children }: { title: string; description: string; children: React.ReactNode }) {
  return <><div className="mb-5"><h3 className="font-bold">{title}</h3><p className="mt-1 text-sm text-[var(--muted)]">{description}</p></div><div className="grid gap-4 sm:grid-cols-2">{children}</div></>;
}

function Field({ label, required, error, wide, children }: { label: string; required?: boolean; error?: string; wide?: boolean; children: React.ReactNode }) {
  return <label className={wide ? 'sm:col-span-2' : ''}><span className="mb-1.5 block text-xs font-semibold">{label}{required && <span className="text-[var(--danger)]"> *</span>}</span>{children}{error && <span className="mt-1 block text-xs text-[var(--danger)]">{error}</span>}</label>;
}
