import type { AuthenticatedRequest } from '../clients/clients-api';
import type { TicketPriority, TicketStatus } from '../portal/portal-api';

export interface ReportFilters {
  from: string;
  to: string;
  status: '' | TicketStatus | 'PENDING';
  clientId: string;
  locationId: string;
  employeeId: string;
  search: string;
}

export interface LedgerRow {
  ticket_id: number;
  service_request_number: string;
  subject: string;
  issue_description: string;
  priority: TicketPriority;
  ticket_status: TicketStatus;
  created_at: string;
  accepted_at: string | null;
  completed_at: string | null;
  final_resolution: string | null;
  declined_at: string | null;
  decline_reason: string | null;
  client_id: number;
  client_code: string;
  client_name: string;
  location_id: number;
  location_code: string;
  location_name: string;
  city: string;
  state_name: string;
  reported_by_name: string;
  current_employee_name: string | null;
  completed_by_name: string | null;
  declined_by_name: string | null;
  attempt_count: number;
  service_employees: string | null;
}

export interface ReportSummary {
  total: number;
  open_count: number;
  accepted_count: number;
  completed_count: number;
  declined_count: number;
  avg_resolution_minutes: number | null;
}

interface ReportOptions {
  clients: Array<{ client_id: number; client_code: string; legal_name: string }>;
  employees: Array<{ employee_id: number; employee_code: string; full_name: string }>;
  locations: Array<{ location_id: number; client_id: number; location_code: string; location_name: string }>;
}

export interface ServiceLedgerReport {
  items: LedgerRow[];
  summary: ReportSummary;
  filters: ReportOptions;
  total: number;
  page: number;
  limit: number;
}

export function getServiceLedger(request: AuthenticatedRequest, filters: ReportFilters): Promise<ServiceLedgerReport> {
  const query = new URLSearchParams({ from: filters.from, to: filters.to, limit: '500' });
  if (filters.status) query.set('status', filters.status);
  if (filters.clientId) query.set('client_id', filters.clientId);
  if (filters.locationId) query.set('location_id', filters.locationId);
  if (filters.employeeId) query.set('employee_id', filters.employeeId);
  if (filters.search.trim()) query.set('search', filters.search.trim());
  return request<ServiceLedgerReport>(`/reports/service-ledger?${query.toString()}`);
}
