import { useQuery } from '@tanstack/react-query';
import {
  BarChart3,
  BriefcaseBusiness,
  Building2,
  Headphones,
  LayoutDashboard,
  KeyRound,
  LogOut,
  Menu,
  Moon,
  Settings,
  Sun,
  Users,
  X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';

import { getHealth } from '../../lib/api';
import { cn } from '../../lib/cn';
import { useAuth } from '../auth/AuthProvider';
import { ChangePasswordDialog } from '../auth/ChangePasswordDialog';

const navigation = [
  { label: 'Overview', icon: LayoutDashboard, path: '/admin', enabled: true },
  { label: 'Clients', icon: Building2, path: '/admin/clients', enabled: true },
  { label: 'Employees', icon: Users, path: '/admin/employees', enabled: true },
  { label: 'Service console', icon: Headphones, path: '/console', enabled: true },
  { label: 'Leads', icon: BriefcaseBusiness, path: '/admin/leads', enabled: false },
  { label: 'Reports', icon: BarChart3, path: '/admin/reports', enabled: true },
  { label: 'Settings', icon: Settings, path: '/admin/settings', enabled: false },
] as const;

export function AdminLayout() {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [darkMode, setDarkMode] = useState(false);
  const [passwordOpen, setPasswordOpen] = useState(false);
  const { actor, signOut } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();
  const health = useQuery({ queryKey: ['api-health'], queryFn: ({ signal }) => getHealth(signal), retry: 1 });
  const displayName = actor?.displayName || actor?.email || 'Administrator';
  const workspaceLabel = actor?.roles.includes('SYSTEM_ADMIN') ? 'System administrator' : actor?.roles.includes('SERVICE_ADMIN') ? 'Service administrator' : 'Service employee';
  const pageTitle = navigation.find((item) => item.path === location.pathname)?.label ?? 'Administration';
  const initials = displayName
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('');

  useEffect(() => setSidebarOpen(false), [location.pathname]);
  useEffect(() => {
    document.documentElement.classList.toggle('dark', darkMode);
  }, [darkMode]);

  const logout = async () => {
    await signOut();
    navigate('/login', { replace: true });
  };

  return (
    <div className="min-h-screen bg-[var(--canvas)] text-[var(--text)]">
      {sidebarOpen && (
        <button
          type="button"
          aria-label="Close navigation"
          className="fixed inset-0 z-40 bg-slate-950/45 backdrop-blur-[2px] lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      <aside
        className={cn(
          'fixed inset-y-0 left-0 z-50 flex w-[278px] flex-col border-r border-white/8 bg-[#0b1f3a] text-white shadow-2xl transition-transform duration-200 lg:translate-x-0 lg:shadow-none',
          sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        )}
      >
        <div className="flex h-20 items-center justify-between border-b border-white/8 px-5">
          <div className="flex items-center gap-3">
            <img src="/onesalez-logo.png" alt="ONESALEZ" className="h-10 w-10 rounded-xl bg-white object-contain p-1.5" />
            <div>
              <p className="text-sm font-bold tracking-wide">ONESALEZ</p>
              <p className="text-[11px] text-slate-400">Service CRM</p>
            </div>
          </div>
          <button
            type="button"
            aria-label="Close sidebar"
            onClick={() => setSidebarOpen(false)}
            className="grid h-9 w-9 place-items-center rounded-lg text-slate-400 hover:bg-white/8 hover:text-white lg:hidden"
          >
            <X aria-hidden="true" className="h-5 w-5" />
          </button>
        </div>

        <nav aria-label="Administrator navigation" className="flex-1 overflow-y-auto px-3 py-6">
          <p className="mb-3 px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Workspace</p>
          <div className="space-y-1">
            {navigation.filter((item) => (item.label !== 'Employees' || actor?.roles.includes('SYSTEM_ADMIN')) && (item.label !== 'Reports' || actor?.roles.includes('SYSTEM_ADMIN') || actor?.permissions.includes('analytics.view'))).map(({ label, icon: Icon, enabled, ...item }) => (
              enabled ? (
                <NavLink
                  key={label}
                  to={item.path}
                  end
                  className={({ isActive }) => cn(
                    'flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-300',
                    isActive ? 'bg-white text-[#0b1f3a] shadow-sm' : 'text-slate-300 hover:bg-white/8 hover:text-white',
                  )}
                >
                  <Icon aria-hidden="true" className="h-[18px] w-[18px]" />
                  {label}
                </NavLink>
              ) : (
                <div key={label} className="flex h-11 items-center gap-3 rounded-xl px-3 text-sm text-slate-500" aria-disabled="true">
                  <Icon aria-hidden="true" className="h-[18px] w-[18px]" />
                  <span>{label}</span>
                  <span className="ml-auto rounded-full bg-white/6 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-slate-500">Soon</span>
                </div>
              )
            ))}
          </div>
        </nav>

        <div className="border-t border-white/8 p-3">
          <div className="rounded-2xl bg-white/5 p-3">
            <div className="flex items-center gap-3">
              <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-400/15 text-sm font-bold text-blue-200">{initials}</div>
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold">{displayName}</p>
                <p className="truncate text-[11px] text-slate-400">{actor?.email}</p>
              </div>
            </div>
            <button
              type="button"
              onClick={() => setPasswordOpen(true)}
              className="mt-3 flex h-9 w-full items-center justify-center gap-2 rounded-xl border border-white/10 text-xs font-semibold text-slate-300 transition hover:bg-white/8 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-300"
            >
              <KeyRound aria-hidden="true" className="h-4 w-4" />
              Change password
            </button>
            <button
              type="button"
              onClick={logout}
              className="mt-2 flex h-9 w-full items-center justify-center gap-2 rounded-xl border border-white/10 text-xs font-semibold text-slate-300 transition hover:bg-white/8 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-300"
            >
              <LogOut aria-hidden="true" className="h-4 w-4" />
              Logout
            </button>
          </div>
        </div>
      </aside>

      <div className="lg:pl-[278px]">
        <header className="sticky top-0 z-30 flex h-18 items-center justify-between border-b border-[var(--border)] bg-[color:var(--surface)]/92 px-4 backdrop-blur-xl sm:px-6 lg:px-8">
          <div className="flex items-center gap-3">
            <button
              type="button"
              aria-label="Open sidebar"
              onClick={() => setSidebarOpen(true)}
              className="grid h-10 w-10 place-items-center rounded-xl border border-[var(--border)] text-[var(--muted)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--focus)] lg:hidden"
            >
              <Menu aria-hidden="true" className="h-5 w-5" />
            </button>
            <div>
              <p className="text-xs text-[var(--muted)]">{workspaceLabel}</p>
              <p className="text-sm font-semibold">{pageTitle}</p>
            </div>
          </div>

          <div className="flex items-center gap-2 sm:gap-3">
            <div className="hidden items-center gap-2 rounded-full border border-[var(--border)] bg-[var(--surface-soft)] px-3 py-1.5 text-[11px] font-medium text-[var(--muted)] sm:flex">
              <span className={cn('h-2 w-2 rounded-full', health.isSuccess ? 'bg-emerald-500' : 'bg-amber-500')} />
              {health.isSuccess ? 'API connected' : 'API unavailable'}
            </div>
            <button
              type="button"
              onClick={() => setDarkMode((value) => !value)}
              aria-label={darkMode ? 'Use light theme' : 'Use dark theme'}
              className="grid h-10 w-10 place-items-center rounded-xl border border-[var(--border)] bg-[var(--surface)] text-[var(--muted)] transition hover:text-[var(--text)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--focus)]"
            >
              {darkMode ? <Sun aria-hidden="true" className="h-4.5 w-4.5" /> : <Moon aria-hidden="true" className="h-4.5 w-4.5" />}
            </button>
            <div className="hidden h-10 items-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-2.5 sm:flex">
              <div className="grid h-7 w-7 place-items-center rounded-lg bg-[var(--brand)] text-[10px] font-bold text-white">{initials}</div>
              <span className="max-w-36 truncate text-xs font-semibold">{displayName}</span>
            </div>
          </div>
        </header>

        <div className="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
          <Outlet />
        </div>
      </div>
      {passwordOpen && <ChangePasswordDialog onClose={() => setPasswordOpen(false)} />}
    </div>
  );
}
