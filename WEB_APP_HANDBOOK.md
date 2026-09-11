# ONESALEZ Service CRM — Web App Handbook

## 1. Purpose

This handbook defines the approved frontend architecture, design standards, user experience rules, security expectations, testing requirements, and deployment approach for the ONESALEZ Service CRM web application.

The web application will serve:

- Client contacts raising and following service tickets.
- ONESALEZ service employees working from the shared service console.
- ONESALEZ administrators managing clients, employees, reports, and service operations.

## 2. Role and Delivery Standard

- **Role:** Senior UI/UX Engineer and SaaS Product Designer.
- **Output:** Clean, reusable, production-ready, fully responsive React code.
- **Architecture:** Component-based frontend with strict TypeScript types and clear separation between pages, features, components, API access, validation, and application state.
- **Deployment output:** Vite-generated static assets that can be served directly by Hostinger.

## 3. Approved Technology Stack

### Core

- **React** — component-based user interface.
- **TypeScript** — type-safe frontend development.
- **Vite** — local development server and production build tool.
- **Tailwind CSS** — consistent utility-based styling and responsive layouts.
- **shadcn/ui** — accessible, customizable UI component foundation.

### Navigation and API data

- **React Router** — page navigation and protected routes.
- **TanStack Query** — API requests, caching, invalidation, retries, and background refresh.
- **TanStack Table** — sorting, filtering, pagination, and reusable data tables.

### Forms and validation

- **React Hook Form** — performant form state and field handling.
- **Zod** — reusable TypeScript-compatible validation schemas.

### Language, icons, and charts

- **react-i18next** — multilingual text and language switching.
- **Lucide React** — consistent application icons.
- **Recharts** — executive dashboard and reporting charts.

### Testing and quality

- **Vitest** — unit and component test runner.
- **Testing Library** — user-focused React component testing.
- **Playwright** — browser automation and end-to-end feature testing.

### Progressive Web App

- **vite-plugin-pwa** — installable mobile and desktop web application experience.

Node.js is required only on the development/build machine. It is not required as a continuously running production server. Vite compiles the application into static files for Hostinger.

## 4. Design Standards

- Modern, clean SaaS aesthetic.
- Professional navy blue, gray, and white primary palette.
- Consistent spacing based on an 8-pixel grid.
- Inter or system fonts with a 14–16 pixel base size and clear typographic hierarchy.
- Clear visual hierarchy and strong separation between navigation, page headings, controls, and content.
- Subtle shadows, transitions, and purposeful micro-interactions.
- Full responsive behavior across mobile, tablet, laptop, and desktop.
- Light and dark themes.
- WCAG 2.1 AA accessibility target.
- Keyboard-accessible navigation and controls.
- Visible focus states.
- Sufficient color contrast.
- Status must never be communicated through color alone.
- Respect the user's reduced-motion preference.

## 5. Required UI Components

- Responsive sidebar navigation with icons.
- Mobile navigation drawer.
- Top header with search, notifications, and user profile.
- Breadcrumb navigation.
- Data tables with server-driven sorting, filtering, and pagination.
- Modal and drawer-based forms where appropriate.
- Dropdown and action menus.
- Status badges with text and color-coded indicators.
- Accessible form inputs with inline validation.
- Toast notifications.
- Loading skeletons and progress indicators.
- Empty states with meaningful next actions.
- Confirmation dialogs for consequential actions.
- Reusable date-range, status, client, employee, and location filters.
- Responsive dashboard cards and charts.

## 6. User Experience Principles

### Progressive disclosure

Show primary tasks prominently. Place advanced options in secondary menus, expandable sections, or contextual actions.

### Validation

- Validate input while preserving what the user has entered.
- Display helpful field-specific messages.
- Match frontend Zod rules with backend API constraints.
- Treat backend validation responses as authoritative.

### Destructive and lifecycle actions

Require explicit confirmation before:

- Soft-deleting a record.
- Suspending an account.
- Declining a ticket.
- Completing a ticket.
- Performing a bulk update.

### Auto-save policy

Auto-save is allowed only for low-risk data such as:

- Draft notes.
- Unsaved ticket descriptions where a draft feature exists.
- Personal UI preferences.
- Filter and table preferences.

Use explicit Save or Confirm actions for:

- Client and location records.
- Contact and employee accounts.
- Role or permission changes.
- Ticket acceptance, release, completion, or decline.
- Lead conversion and bulk actions.

### Search and quick actions

- Make common actions reachable with minimal navigation.
- Provide autocomplete only when a supporting backend endpoint exists.
- Debounce searches to avoid unnecessary API requests.
- Preserve filters when users navigate into a record and return.

## 7. Responsive Application Behavior

### Mobile

- Collapsible navigation drawer.
- Touch-friendly controls with adequate target sizes.
- Cards or compact row layouts when wide tables are unsuitable.
- Sticky primary action where it improves completion.
- Avoid horizontal scrolling for primary workflows.

### Tablet

- Collapsible sidebar.
- Two-column forms where space permits.
- Responsive tables with prioritized columns.

### Desktop

- Persistent sidebar.
- Dense but readable service console.
- Multi-column dashboards and detail panels.
- Keyboard-friendly table and ticket workflows.

## 8. Authentication and Token Security

The frontend will use the PHP authentication API.

Approved token handling:

- Keep the short-lived access token in application memory.
- Store the refresh token in a Secure, HttpOnly, SameSite cookie.
- Do not store access or refresh tokens in `localStorage`.
- Refresh tokens must rotate after use.
- Clear local authentication state immediately after logout or an unrecoverable authentication failure.
- Redirect unauthorized users to login while preserving an appropriate return path.

The PHP backend requires a small update to issue, read, rotate, and revoke the refresh token through the secure cookie.

## 9. API Integration Rules

- Use a single typed API client.
- Keep endpoint paths and request/response types centralized.
- Use TanStack Query for server state rather than duplicating API data in global client state.
- Cancel stale requests when searches or filters change.
- Retry only safe requests and temporary failures.
- Never automatically retry destructive operations.
- Display friendly errors while retaining diagnostic details for development logging.
- Respect `401`, `403`, `404`, `422`, `429`, and `500` responses distinctly.
- Invalidate only the affected queries after a successful mutation.
- Avoid N+1 API request patterns.

## 10. Service Console Refresh Strategy

The initial version will use controlled polling instead of WebSockets.

- Refresh the active service console approximately every 15–30 seconds.
- Pause unnecessary polling when the browser tab is not active.
- Refresh immediately after acceptance, release, completion, or decline.
- Show a subtle activity indicator during background refresh.
- Do not replace or reset a form the user is actively editing.

WebSockets or Server-Sent Events may be evaluated later if usage demonstrates a genuine need.

## 11. Notifications and Autocomplete

The UI may reserve space for notifications and global search, but these features require supporting backend APIs.

Until those APIs exist:

- Do not display fabricated notifications.
- Do not pretend global search is functional.
- Use scoped searches already supported by leads, clients, employees, and tickets.

## 12. Multilingual Support

- All visible interface text must come from translation files.
- Do not hard-code user-facing strings inside reusable components.
- English will be the initial language unless additional languages are selected.
- Format dates, times, and numbers using the selected locale.
- Store API enum values independently from their translated display labels.
- Allow layouts to accommodate longer translated text.

## 13. Progressive Web App and Caching

The application should be installable on supported desktop and mobile devices.

Allowed caching:

- Application shell.
- Versioned JavaScript, CSS, icons, and static assets.
- Non-sensitive public resources.

Do not persist sensitive CRM records, tickets, authentication tokens, client details, or reports for offline use.

When the network is unavailable:

- Show a clear offline indicator.
- Disable actions that require the API.
- Do not imply that a ticket or update was saved unless the server confirmed it.

## 14. Application Structure

The frontend will be created at:

```text
C:\Users\sid\Documents\ONESALEZ_SERVICE_CRM\webapp
```

Recommended structure:

```text
webapp/
├── public/
├── src/
│   ├── app/              # Router, providers, application configuration
│   ├── assets/           # Images and static design assets
│   ├── components/       # Reusable shared UI components
│   ├── features/         # Feature modules such as auth, clients, and tickets
│   ├── hooks/            # Shared React hooks
│   ├── i18n/             # Translation configuration and language files
│   ├── layouts/          # Client, employee, and admin layouts
│   ├── lib/              # API client, utilities, constants
│   ├── pages/            # Route-level pages
│   ├── schemas/          # Shared Zod schemas
│   ├── styles/           # Global styles and Tailwind entry
│   ├── test/             # Test setup and helpers
│   └── types/            # Shared TypeScript types
├── tests/
│   └── e2e/              # Playwright tests
├── package.json
├── tsconfig.json
└── vite.config.ts
```

## 15. Component and Code Standards

- Use strict TypeScript configuration.
- Avoid `any`; use explicit types and `unknown` where input has not been validated.
- Keep components focused on one responsibility.
- Separate reusable presentation components from feature-specific business behavior.
- Keep API calls outside visual components.
- Use semantic HTML before adding ARIA attributes.
- Prefer composition over large components with many conditional modes.
- Use meaningful business names rather than generic names such as `data`, `item`, or `thing` when the context permits.
- Comment complex workflows and non-obvious decisions, not basic syntax.
- Use realistic ONESALEZ business examples instead of Lorem Ipsum.

## 16. Testing Requirements

### Vitest and Testing Library

Test:

- Form validation and error messages.
- Permission-based rendering.
- Loading, empty, error, and success states.
- Table filtering and pagination behavior.
- Ticket lifecycle controls.
- Authentication state changes.
- Accessibility of critical interactive components.

### Playwright

Test complete workflows:

- Client login and ticket creation.
- Employee login and ticket acceptance.
- Ticket release and re-acceptance.
- Ticket completion.
- Administrator decline flow.
- Client and employee administration.
- Session refresh and logout.
- Mobile navigation and essential responsive workflows.

## 17. Hostinger Deployment

Node.js does not need to run continuously on Hostinger for this architecture.

Development and deployment flow:

1. Install dependencies locally with `npm install`.
2. Run the development application locally with `npm run dev`.
3. Run tests and type checking.
4. Create static production assets with `npm run build`.
5. Upload the generated `dist` contents to the Hostinger web directory.
6. Keep the PHP API responsible for authentication, authorization, database access, and business rules.

Production frontend environment variables must contain only public configuration such as the API base URL. Secrets must never be included in Vite environment variables or compiled browser code.

## 18. Business Context

- **Target users:** Service business teams with approximately 5–50 users.
- **Primary use case:** Manage clients, locations, contacts, service tickets, employee activity, reporting, and sales leads.
- **Emotional tone:** Professional, trustworthy, calm, and efficient.
- **Operational priorities:** Fast ticket handling, clear ownership, reliable history, and low user confusion.
- **Constraints:** Mobile support, multilingual readiness, API integration, accessibility, and reliable Hostinger deployment.

## 19. Output Expectations

- Production-ready React and TypeScript components.
- Vite-generated static deployment assets.
- Clean, modular feature structure.
- Reusable component patterns.
- Strict TypeScript types.
- Responsive breakpoints and layouts.
- Accessible keyboard and screen-reader behavior.
- Comments for complex business logic.
- Realistic ONESALEZ business content and test fixtures.
- No Lorem Ipsum or misleading fake functionality.
- Unit, component, and browser tests for critical workflows.

