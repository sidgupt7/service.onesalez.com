import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Building2, MapPin, Pencil, Plus, Search, UserRound } from 'lucide-react';
import { useState } from 'react';

import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import { ApiError } from '../../lib/api';
import { useAuth } from '../auth/AuthProvider';
import { ClientManagementDialog } from './ClientManagementDialog';
import { ClientOnboardingWizard } from './ClientOnboardingWizard';
import { listClients, onboardClient, type ClientOnboardingInput } from './clients-api';

export function ClientsPage() {
  const [search, setSearch] = useState('');
  const [wizardOpen, setWizardOpen] = useState(false);
  const [selectedClientId, setSelectedClientId] = useState<number | null>(null);
  const { authenticatedRequest } = useAuth();
  const queryClient = useQueryClient();
  const clients = useQuery({ queryKey: ['clients', search], queryFn: () => listClients(authenticatedRequest, search) });
  const createClient = useMutation({
    mutationFn: (input: ClientOnboardingInput) => onboardClient(authenticatedRequest, input),
    onSuccess: async () => { await queryClient.invalidateQueries({ queryKey: ['clients'] }); setWizardOpen(false); },
  });

  return (
    <div className="mx-auto max-w-7xl">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><p className="text-xs font-bold uppercase tracking-[0.16em] text-[var(--brand)]">Directory</p><h1 className="mt-1 text-2xl font-bold sm:text-3xl">Clients</h1><p className="mt-2 text-sm text-[var(--muted)]">Add or modify client details, sites, contacts, and access status.</p></div>
        <Button onClick={() => setWizardOpen(true)}><Plus className="mr-2 h-4 w-4" />Add new client</Button>
      </div>

      <div className="mt-7 rounded-2xl border border-[var(--border)] bg-[var(--surface)] shadow-sm">
        <div className="border-b border-[var(--border)] p-4 sm:p-5"><label className="relative block max-w-md"><span className="sr-only">Search clients</span><Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]" /><Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search name, code, or GSTIN" className="pl-10 uppercase" /></label></div>
        {clients.isLoading && <div className="p-10 text-center text-sm text-[var(--muted)]">Loading clients…</div>}
        {clients.isError && <div role="alert" className="p-10 text-center text-sm text-[var(--danger)]">Clients could not be loaded. Please try again.</div>}
        {clients.isSuccess && clients.data.length === 0 && <div className="px-5 py-16 text-center"><div className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-[var(--surface-soft)] text-[var(--brand)]"><Building2 className="h-6 w-6" /></div><h2 className="mt-4 font-bold">{search ? 'No clients found' : 'No clients yet'}</h2><p className="mt-1 text-sm text-[var(--muted)]">{search ? 'Try another search term.' : 'Add your first client to begin.'}</p></div>}
        {clients.isSuccess && clients.data.length > 0 && <div className="divide-y divide-[var(--border)]">{clients.data.map((client) => <article key={client.client_id} className="flex flex-col gap-4 p-5 transition hover:bg-[var(--surface-soft)]/50 sm:flex-row sm:items-center"><div className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-blue-50 font-bold text-[var(--brand)]">{(client.display_name || client.legal_name).slice(0, 2).toUpperCase()}</div><div className="min-w-0 flex-1"><div className="flex flex-wrap items-center gap-2"><h2 className="truncate font-bold uppercase">{client.display_name || client.legal_name}</h2><span className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${Boolean(client.is_active) ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'}`}>{Boolean(client.is_active) ? 'Active' : 'Suspended'}</span></div><p className="mt-1 text-xs uppercase text-[var(--muted)]">{client.client_code} · {client.legal_name}</p></div><div className="flex flex-wrap items-center gap-4 text-xs text-[var(--muted)]"><span className="flex items-center gap-1.5"><MapPin className="h-4 w-4" />{client.location_count} sites</span><span className="flex items-center gap-1.5"><UserRound className="h-4 w-4" />{client.contact_count} contacts</span><Button onClick={() => setSelectedClientId(client.client_id)} className="h-9 px-3"><Pencil className="mr-1.5 h-3.5 w-3.5" />Manage</Button></div></article>)}</div>}
      </div>

      {wizardOpen && <ClientOnboardingWizard pending={createClient.isPending} serverError={createClient.error instanceof ApiError ? createClient.error.message : createClient.isError ? 'The client could not be created.' : null} onClose={() => !createClient.isPending && setWizardOpen(false)} onSubmit={async (input) => { await createClient.mutateAsync(input); }} />}
      {selectedClientId !== null && <ClientManagementDialog clientId={selectedClientId} onClose={() => setSelectedClientId(null)} />}
    </div>
  );
}
