# ONESALEZ Service Tracker

## Base Understanding — Version 1

**Status:** Initial understanding captured for discussion and future revision.  
**Captured on:** 19 June 2026

## 1. Business Context

ONESALEZ develops software and provides ongoing support services to its clients.

Each client is a trading business engaged in retail, wholesale, or both. A client may operate from one or many locations across one or many states in India.

ONESALEZ has multiple employees who perform the day-to-day work of supporting these clients. Clients raise service requests, and ONESALEZ service employees contact them and work to resolve their problems.

ONESALEZ intends to build a suite of web- and app-based Service CRM applications. The first phase is the **ONESALEZ Service Tracker**.

## 2. Important Role Distinction

The system must keep these two concepts separate:

- **ONESALEZ employee/admin:** A member of ONESALEZ who provides service or administers the Service CRM.
- **Client-side admin:** A contact person employed by the client who has an administrative role at the client's business or location.

A client-side admin is not an ONESALEZ system administrator.

## 3. Client Master Data

ONESALEZ employees will record and maintain:

- All clients.
- Each client's one or many locations.
- Contact persons belonging to each client.
- The location scope of each contact person: one location, multiple selected locations, or all client locations.
- The functional role of each contact person, such as system operator, end user, client-side admin, or owner.

### Assisted client access

- A contact person may exist only as a business contact, or may optionally be given client portal login access.
- When portal access is enabled, the contact's email address is the login identifier and an initial temporary password is required.
- Client users have varied levels of digital confidence and may ask ONESALEZ support employees for direct credential assistance.
- Any authorized ONESALEZ employee may enable or disable a contact's portal access and set a new password on the contact's behalf.
- A support-assisted password change must revoke the contact's existing refresh sessions and trusted-device PIN credentials so the new password takes effect safely on every device.
- Contact, client, and portal suspension rules continue to block login and ticket creation even after a password is changed.

## 4. Client Service Request Flow

Clients can use either a web application or a .NET MAUI desktop/mobile application.

The flow is:

1. A client contact logs in using their credentials.
2. The contact describes the issue and submits a service request.
3. The system generates a unique service request/ticket number.

The ticket should be associated with the client, the reporting contact person, and the relevant client location or locations where applicable.

## 5. Service Employee Console

An ONESALEZ service employee logs in and sees a shared service console. Tickets are visible to the relevant ONESALEZ employees and are separated into three principal working sections:

1. **Open**
2. **Accepted**
3. **Completed**

A separate hidden/restricted **Declined** section is available to authorized ONESALEZ admins.

## 6. Ticket Lifecycle

### New ticket

- A newly submitted ticket enters the **Open** list.

### Acceptance

- A service employee accepts a ticket from the Open list.
- The ticket moves to **Accepted**.
- The system records the accepting employee and acceptance date/time.

### Service attempt outcomes

After working on an accepted ticket, the employee records one of two outcomes:

1. **Service completed**
   - The employee records the resolution/work note.
   - The ticket moves to **Completed**.
   - Completion details and date/time are recorded.

2. **Service not completed / released**
   - The employee records a mandatory note explaining the attempt or reason for release.
   - The ticket returns to **Open** so that it can be accepted again.
   - The attempt remains permanently available in the ticket history.

A ticket can therefore have many service attempts, possibly by different employees, before it is successfully completed.

### Declining a ticket

- An authorized ONESALEZ admin may decline a ticket at any stage.
- The ticket moves to a restricted/hidden **Declined** section.
- The decline action should retain its history, including who declined it, when, and why.

## 7. Audit and History

The system should preserve the full lifecycle of each ticket, including:

- Creation and ticket number.
- Client, location, and reporting contact.
- Every acceptance and release.
- Every employee who attempted the service.
- Notes for each attempt.
- Status changes with date/time.
- Completion details.
- Decline details, where applicable.

This history is the foundation for service ledgers, employee accountability, and management reporting.

## 8. Reporting

The required reports include:

1. Date-to-date service ledger.
2. Client-wise, date-to-date service ledger.
3. Employee-wise, date-to-date service ledger.
4. Date-to-date pending service ledger.
5. Date-to-date declined service ledger.

The preferred design is one unified reporting system in which users can apply filters such as date range, client, employee, ticket status, location, and other relevant criteria instead of maintaining separate disconnected reports.

## 9. Executive Dashboard

When an authorized ONESALEZ admin logs in, the service console should include an executive dashboard that presents the most important service information on one screen. Exact dashboard indicators will be decided during later discussions.

## 10. Initial Scope Summary

Version 1 establishes the following core areas:

- Client, location, and contact-person management.
- Client authentication and ticket submission.
- Unique service request numbering.
- Shared employee service console.
- Open, Accepted, Completed, and restricted Declined ticket states.
- Multiple service attempts with permanent notes and history.
- Unified operational reporting.
- ONESALEZ admin executive dashboard.

This document is a living base understanding. It will be revised as business rules and requirements are clarified through discussion.
