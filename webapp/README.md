# ONESALEZ Service CRM Web App

React, TypeScript, Vite, Tailwind CSS, and shadcn-style component foundation for the ONESALEZ Service CRM.

## Development

```bash
npm install
npm run dev
```

Open `http://127.0.0.1:5173/login`.

## Quality checks

```bash
npm run check
npm run test:e2e
```

## Production build

```bash
npm run build
```

Upload the contents of `dist` to the Hostinger web directory. Node.js does not need to run on the production server.

The authentication milestone includes the responsive login interface, secure HttpOnly refresh-cookie integration, in-memory access tokens, session restoration, automatic token rotation after `401` responses, protected role routes, logout, API health indication, PWA shell, and test infrastructure.
