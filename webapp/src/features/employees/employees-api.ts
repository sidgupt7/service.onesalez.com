import type { AuthenticatedRequest } from '../clients/clients-api';

export type EmployeeRole = 'SERVICE_EMPLOYEE' | 'SERVICE_ADMIN' | 'SYSTEM_ADMIN';

export interface EmployeeSummary {
  employee_id: number;
  employee_code: string;
  full_name: string;
  official_email: string;
  mobile_number: string | null;
  designation: string | null;
  department: string | null;
  joining_date: string | null;
  employment_status: 'ACTIVE' | 'SUSPENDED' | 'LEFT';
  account_status: 'ACTIVE' | 'SUSPENDED' | 'INVITED' | null;
  roles: string | null;
}

export type UpdateEmployeeInput = Omit<AddEmployeeInput, 'password'>;

export interface AddEmployeeInput {
  employee_code: string;
  full_name: string;
  official_email: string;
  mobile_number?: string;
  designation?: string;
  department?: string;
  joining_date?: string;
  password: string;
  roles: EmployeeRole[];
}

export function listEmployees(request: AuthenticatedRequest): Promise<EmployeeSummary[]> {
  return request<EmployeeSummary[]>('/users?page=1&limit=100');
}

export function addEmployee(request: AuthenticatedRequest, input: AddEmployeeInput): Promise<{ employee_id: number }> {
  return request<{ employee_id: number }>('/users', { method: 'POST', body: JSON.stringify(input) });
}

export function updateEmployee(request: AuthenticatedRequest, id: number, input: UpdateEmployeeInput): Promise<EmployeeSummary> {
  return request<EmployeeSummary>(`/users/${id}`, { method: 'PUT', body: JSON.stringify(input) });
}

export function suspendEmployee(request: AuthenticatedRequest, id: number, reason: string): Promise<{ message: string }> {
  return request<{ message: string }>(`/users/${id}/suspend`, { method: 'POST', body: JSON.stringify({ reason }) });
}

export function reactivateEmployee(request: AuthenticatedRequest, id: number): Promise<{ message: string }> {
  return request<{ message: string }>(`/users/${id}/reactivate`, { method: 'POST' });
}

export function setEmployeePassword(request: AuthenticatedRequest, id: number, password: string): Promise<{ message: string }> {
  return request<{ message: string }>(`/users/${id}/password`, { method: 'PUT', body: JSON.stringify({ password }) });
}

export function deleteEmployee(request: AuthenticatedRequest, id: number): Promise<{ message: string }> {
  return request<{ message: string }>(`/users/${id}`, { method: 'DELETE' });
}
