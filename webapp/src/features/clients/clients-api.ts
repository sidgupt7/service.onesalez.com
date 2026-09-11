export interface ClientSummary {
  client_id: number;
  client_code: string;
  legal_name: string;
  display_name: string | null;
  primary_email: string | null;
  primary_phone: string | null;
  is_active: boolean | number;
  location_count: number;
  contact_count: number;
}

export interface ClientLocation {
  location_id: number;
  location_code: string;
  location_name: string;
  location_type: 'HEAD_OFFICE' | 'BRANCH' | 'WAREHOUSE' | 'OTHER';
  address_line_1: string;
  address_line_2: string | null;
  landmark: string | null;
  city: string;
  district: string | null;
  state_name: string;
  postal_code: string;
  gstin: string | null;
  email: string | null;
  phone: string | null;
  is_primary: boolean | number;
  is_active: boolean | number;
}

export interface ClientContact {
  contact_id: number;
  full_name: string;
  designation: string | null;
  email: string;
  mobile_number: string;
  alternate_number: string | null;
  role_code: 'SYSTEM_OPERATOR' | 'END_USER' | 'CLIENT_ADMIN' | 'OWNER';
  role_name: string;
  has_all_locations: boolean | number;
  is_primary_contact: boolean | number;
  is_active: boolean | number;
  portal_enabled: boolean | number;
  account_status: 'INVITED' | 'ACTIVE' | 'SUSPENDED' | null;
  location_ids: number[];
}

export interface ClientDetails extends ClientSummary {
  gstin: string | null;
  pan: string | null;
  website_url: string | null;
  notes: string | null;
  locations: ClientLocation[];
  contacts: ClientContact[];
}

export type ClientDetailsInput = Partial<Pick<ClientDetails, 'client_code' | 'legal_name' | 'display_name' | 'gstin' | 'pan' | 'primary_email' | 'primary_phone' | 'website_url' | 'notes'>>;
export type LocationInput = Omit<ClientLocation, 'location_id' | 'is_primary' | 'is_active'>;
export type ContactInput = Omit<ClientContact, 'contact_id' | 'role_name' | 'is_active' | 'account_status'> & { password?: string };

export interface ClientOnboardingInput {
  client: {
    client_code: string;
    legal_name: string;
    display_name?: string;
    gstin?: string;
    primary_phone?: string;
  };
  location: {
    location_code: string;
    location_name: string;
    location_type: 'HEAD_OFFICE' | 'BRANCH' | 'WAREHOUSE' | 'OTHER';
    address_line_1: string;
    address_line_2?: string;
    city: string;
    state_name: string;
    postal_code: string;
  };
  administrator: {
    full_name: string;
    designation?: string;
    email: string;
    mobile_number: string;
    password: string;
  };
}

export type AuthenticatedRequest = <T>(path: string, options?: RequestInit) => Promise<T>;

export function listClients(request: AuthenticatedRequest, search: string): Promise<ClientSummary[]> {
  const query = new URLSearchParams({ page: '1', limit: '50' });
  if (search.trim()) query.set('search', search.trim());
  return request<ClientSummary[]>(`/customers?${query.toString()}`);
}

export function onboardClient(request: AuthenticatedRequest, input: ClientOnboardingInput): Promise<ClientSummary> {
  return request<ClientSummary>('/customers/onboard', { method: 'POST', body: JSON.stringify(input) });
}

export function getClient(request: AuthenticatedRequest, id: number): Promise<ClientDetails> {
  return request<ClientDetails>(`/customers/${id}`);
}

export function updateClient(request: AuthenticatedRequest, id: number, input: ClientDetailsInput): Promise<ClientDetails> {
  return request<ClientDetails>(`/customers/${id}`, { method: 'PUT', body: JSON.stringify(input) });
}

export function setClientActive(request: AuthenticatedRequest, id: number, active: boolean): Promise<ClientDetails> {
  return request<ClientDetails>(`/customers/${id}/${active ? 'reactivate' : 'suspend'}`, { method: 'POST' });
}

export function saveLocation(request: AuthenticatedRequest, clientId: number, input: LocationInput, locationId?: number): Promise<ClientDetails> {
  return request<ClientDetails>(`/customers/${clientId}/locations${locationId ? `/${locationId}` : ''}`, { method: locationId ? 'PUT' : 'POST', body: JSON.stringify(input) });
}

export function setLocationActive(request: AuthenticatedRequest, clientId: number, locationId: number, active: boolean): Promise<ClientDetails> {
  return request<ClientDetails>(`/customers/${clientId}/locations/${locationId}/${active ? 'reactivate' : 'suspend'}`, { method: 'POST' });
}

export function saveContact(request: AuthenticatedRequest, clientId: number, input: ContactInput, contactId?: number): Promise<ClientDetails> {
  return request<ClientDetails>(`/customers/${clientId}/contacts${contactId ? `/${contactId}` : ''}`, { method: contactId ? 'PUT' : 'POST', body: JSON.stringify(input) });
}

export function setContactActive(request: AuthenticatedRequest, clientId: number, contactId: number, active: boolean): Promise<ClientDetails> {
  return request<ClientDetails>(`/customers/${clientId}/contacts/${contactId}/${active ? 'reactivate' : 'suspend'}`, { method: 'POST' });
}
