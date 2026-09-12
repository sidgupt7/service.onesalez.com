import type { AuthenticatedRequest } from "../clients/clients-api";
import type { TicketDetails, TicketPriority } from "../portal/portal-api";

export type TicketAction = "accept" | "release" | "complete" | "decline";

export function transitionTicket(
  request: AuthenticatedRequest,
  id: number,
  action: TicketAction,
  note = "",
): Promise<TicketDetails> {
  return request<TicketDetails>(`/tickets/${id}/${action}`, {
    method: "POST",
    body: JSON.stringify({ note }),
  });
}

export function changePriority(
  request: AuthenticatedRequest,
  id: number,
  priority: TicketPriority,
): Promise<TicketDetails> {
  return request<TicketDetails>(`/tickets/${id}/priority`, {
    method: "PUT",
    body: JSON.stringify({ priority }),
  });
}

export function addTicketMessage(
  request: AuthenticatedRequest,
  id: number,
  message: string,
  internal: boolean,
): Promise<{ message_id: number }> {
  return request<{ message_id: number }>(`/tickets/${id}/messages`, {
    method: "POST",
    body: JSON.stringify({ message, is_internal: internal }),
  });
}
