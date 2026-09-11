import type { AuthenticatedRequest, ClientDetails } from '../clients/clients-api';

export type TicketStatus = 'OPEN' | 'ACCEPTED' | 'COMPLETED' | 'DECLINED';
export type TicketPriority = 'NORMAL' | 'HIGH' | 'URGENT';

export interface TicketSummary {
  ticket_id: number;
  service_request_number: string;
  subject: string;
  issue_description: string;
  priority: TicketPriority;
  ticket_status: TicketStatus;
  client_name: string;
  location_name: string;
  current_employee_id: number | null;
  current_employee_name: string | null;
  created_at: string;
  accepted_at: string | null;
  completed_at: string | null;
  final_resolution: string | null;
  decline_reason: string | null;
}

export interface TicketAttempt {
  attempt_id: number;
  attempt_number: number;
  employee_name: string;
  attempt_status: string;
  accepted_at: string;
  ended_at: string | null;
  service_note: string | null;
}

export interface TicketMessage {
  message_id: number;
  author_type: 'CLIENT_CONTACT' | 'EMPLOYEE';
  message: string;
  is_internal: boolean | number;
  created_at: string;
}

export interface DescriptionHistory {
  description_history_id: number;
  previous_description: string;
  new_description: string;
  changed_by_name: string;
  created_at: string;
}

export interface TicketDetails extends TicketSummary {
  reported_by_name: string;
  attempts: TicketAttempt[];
  messages: TicketMessage[];
  description_history: DescriptionHistory[];
}

export interface CreateTicketInput {
  location_id: number;
  subject: string;
  issue_description: string;
  priority: TicketPriority;
}

export function portalClient(request: AuthenticatedRequest, clientId: number): Promise<ClientDetails> {
  return request<ClientDetails>(`/customers/${clientId}`);
}

export function listTickets(request: AuthenticatedRequest, status = '', search = ''): Promise<TicketSummary[]> {
  const query = new URLSearchParams({ page: '1', limit: '100' });
  if (status) query.set('status', status);
  if (search.trim()) query.set('search', search.trim());
  return request<TicketSummary[]>(`/tickets?${query.toString()}`);
}

export function ticketDetails(request: AuthenticatedRequest, id: number): Promise<TicketDetails> {
  return request<TicketDetails>(`/tickets/${id}`);
}

export function createTicket(request: AuthenticatedRequest, input: CreateTicketInput): Promise<TicketDetails> {
  return request<TicketDetails>('/tickets', { method: 'POST', body: JSON.stringify(input) });
}

export function updateTicketDescription(request: AuthenticatedRequest, id: number, issueDescription: string): Promise<TicketDetails> {
  return request<TicketDetails>(`/tickets/${id}/description`, { method: 'PUT', body: JSON.stringify({ issue_description: issueDescription }) });
}
