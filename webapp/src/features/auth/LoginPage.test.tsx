import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { LoginPage } from './LoginPage';
import { AuthProvider } from './AuthProvider';

function renderLoginPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <MemoryRouter initialEntries={['/login']}>
          <LoginPage />
        </MemoryRouter>
      </AuthProvider>
    </QueryClientProvider>,
  );
}

describe('LoginPage', () => {
  beforeEach(() => localStorage.clear());

  it('switches between client and employee workspaces', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));
    const user = userEvent.setup();
    renderLoginPage();

    await user.click(screen.getByRole('button', { name: 'Employee' }));

    expect(screen.getByRole('button', { name: /sign in to service console/i })).toBeInTheDocument();
  });

  it('shows helpful validation messages', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));
    const user = userEvent.setup();
    renderLoginPage();

    await user.click(screen.getByRole('button', { name: /sign in to client portal/i }));

    expect(await screen.findByText('Enter a valid work email address.')).toBeInTheDocument();
    expect(screen.getByText('Enter your password.')).toBeInTheDocument();
  });

  it('reveals PIN enrollment fields for a trusted device', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));
    const user = userEvent.setup();
    renderLoginPage();

    await user.click(screen.getByRole('checkbox', { name: /enable 6-digit pin/i }));

    expect(screen.getByLabelText('Create PIN')).toHaveAttribute('type', 'password');
    expect(screen.getByLabelText('Confirm PIN')).toHaveAttribute('type', 'password');
  });

  it('opens password recovery and normalizes email casing', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));
    const user = userEvent.setup();
    renderLoginPage();

    const email = screen.getByRole('textbox', { name: 'Email address' });
    await user.type(email, 'USER@EXAMPLE.COM');
    expect(email).toHaveValue('user@example.com');

    await user.click(screen.getByRole('button', { name: 'Forgot password?' }));
    expect(screen.getByRole('heading', { name: 'Reset password' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Send reset link' })).toBeInTheDocument();
  });
});
