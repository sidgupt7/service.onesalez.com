import { useTheme } from "../../lib/use-theme";
import { zodResolver } from "@hookform/resolvers/zod";
import { useQuery } from "@tanstack/react-query";
import {
  ArrowRight,
  Building2,
  CheckCircle2,
  CircleAlert,
  Eye,
  EyeOff,
  Headphones,
  KeyRound,
  Moon,
  RotateCcw,
  ShieldCheck,
  Sun,
  Trash2,
  UserRoundCog,
} from "lucide-react";
import { type FormEvent, useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { useLocation, useNavigate } from "react-router-dom";
import { z } from "zod";

import { Button } from "../../components/ui/Button";
import { Input } from "../../components/ui/Input";
import { ApiError, getHealth } from "../../lib/api";
import { cn } from "../../lib/cn";
import { resetApp } from "../../lib/reset-app";
import { useAuth } from "./AuthProvider";
import { ForgotPasswordDialog } from "./ForgotPasswordDialog";
import {
  forgetTrustedProfile,
  homeForActor,
  trustedProfiles,
  type TrustedDeviceProfile,
} from "./auth-api";

const loginSchema = z
  .object({
    realm: z.enum(["client", "employee"]),
    email: z.email("Enter a valid work email address."),
    password: z.string().min(1, "Enter your password."),
    trustDevice: z.boolean(),
    quickPin: z.string(),
    confirmPin: z.string(),
  })
  .superRefine((values, context) => {
    if (!values.trustDevice) return;
    if (!/^\d{6}$/.test(values.quickPin)) {
      context.addIssue({
        code: "custom",
        path: ["quickPin"],
        message: "Enter exactly 6 digits.",
      });
    }
    if (values.quickPin !== values.confirmPin) {
      context.addIssue({
        code: "custom",
        path: ["confirmPin"],
        message: "PINs do not match.",
      });
    }
  });

type LoginForm = z.infer<typeof loginSchema>;

export function LoginPage() {
  const { actor, signIn, signInWithPin, status } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [darkMode, setDarkMode] = useTheme();
  const [showPassword, setShowPassword] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [forgotOpen, setForgotOpen] = useState(false);
  const [resettingApp, setResettingApp] = useState(false);
  const [resetError, setResetError] = useState<string | null>(null);
  const [profiles, setProfiles] = useState<TrustedDeviceProfile[]>(() =>
    trustedProfiles(),
  );
  const [selectedProfile, setSelectedProfile] =
    useState<TrustedDeviceProfile | null>(() => trustedProfiles()[0] || null);
  const [quickPin, setQuickPin] = useState("");
  const [quickSubmitting, setQuickSubmitting] = useState(false);
  const health = useQuery({
    queryKey: ["api-health"],
    queryFn: ({ signal }) => getHealth(signal),
    retry: 1,
    refetchInterval: 60_000,
  });
  const {
    register,
    handleSubmit,
    watch,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<LoginForm>({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      realm: "client",
      email: "",
      password: "",
      trustDevice: false,
      quickPin: "",
      confirmPin: "",
    },
  });
  const realm = watch("realm");
  const email = watch("email");
  const trustDevice = watch("trustDevice");

  useEffect(() => {
    document.documentElement.classList.toggle("dark", darkMode);
  }, [darkMode]);

  useEffect(() => {
    if (status === "authenticated" && actor) {
      navigate(homeForActor(actor), { replace: true });
    }
  }, [actor, navigate, status]);

  const submitLogin = handleSubmit(
    async ({
      realm: loginRealm,
      email,
      password,
      trustDevice: shouldTrust,
      quickPin: pin,
    }) => {
      setNotice(null);
      try {
        const signedInActor = await signIn(
          { realm: loginRealm, email: email.trim().toLowerCase(), password },
          shouldTrust ? pin : undefined,
        );
        const returnPath = (location.state as { from?: string } | null)?.from;
        navigate(returnPath || homeForActor(signedInActor), { replace: true });
      } catch (error) {
        setNotice(
          error instanceof ApiError
            ? error.message
            : "Unable to sign in. Please try again.",
        );
      }
    },
  );

  const submitQuickLogin = async (event: FormEvent) => {
    event.preventDefault();
    if (!selectedProfile || !/^\d{6}$/.test(quickPin)) {
      setNotice("Enter your 6-digit PIN.");
      return;
    }
    setNotice(null);
    setQuickSubmitting(true);
    try {
      const signedInActor = await signInWithPin(selectedProfile, quickPin);
      const returnPath = (location.state as { from?: string } | null)?.from;
      navigate(returnPath || homeForActor(signedInActor), { replace: true });
    } catch (error) {
      setNotice(
        error instanceof ApiError
          ? error.message
          : "Unable to use quick login. Please try again.",
      );
    } finally {
      setQuickSubmitting(false);
    }
  };

  const forgetProfile = (profile: TrustedDeviceProfile) => {
    forgetTrustedProfile(profile.deviceId);
    const remaining = profiles.filter(
      (item) => item.deviceId !== profile.deviceId,
    );
    setProfiles(remaining);
    setSelectedProfile(remaining[0] || null);
    setQuickPin("");
  };

  const handleResetApp = async () => {
    setResettingApp(true);
    setResetError(null);
    try {
      await resetApp();
    } catch {
      setResetError(
        "Could not clear the app cache. Close other ONESALEZ tabs and try again.",
      );
      setResettingApp(false);
    }
  };

  return (
    <main className="min-h-screen bg-[var(--canvas)] lg:grid lg:grid-cols-[minmax(380px,0.92fr)_minmax(520px,1.08fr)]">
      <section className="relative hidden overflow-hidden bg-[#0b1f3a] px-12 py-10 text-white lg:flex lg:min-h-screen lg:flex-col">
        <div className="absolute -left-32 top-1/4 h-96 w-96 rounded-full bg-blue-500/10 blur-3xl" />
        <div className="absolute -right-20 bottom-0 h-80 w-80 rounded-full bg-cyan-400/10 blur-3xl" />

        <div className="relative flex items-center gap-3">
          <img
            src="/onesalez-logo.png"
            alt="ONESALEZ"
            className="h-11 w-11 rounded-xl bg-white object-contain p-1.5"
          />
          <div>
            <p className="text-lg font-bold tracking-wide">ONESALEZ</p>
            <p className="text-xs text-blue-200">Service CRM</p>
          </div>
        </div>

        <div className="relative my-auto max-w-xl py-16">
          <div className="mb-7 inline-flex items-center gap-2 rounded-full border border-blue-300/20 bg-white/5 px-3 py-1.5 text-xs font-medium text-blue-100">
            <ShieldCheck aria-hidden="true" className="h-4 w-4" />
            Secure service operations, one place
          </div>
          <h1 className="max-w-lg text-4xl font-semibold leading-tight tracking-tight xl:text-5xl">
            Support that feels organized from the first request.
          </h1>
          <p className="mt-6 max-w-lg text-base leading-7 text-slate-300">
            Give clients a clear way to ask for help, and give your service team
            the context to resolve it with confidence.
          </p>

          <div className="mt-12 grid max-w-lg gap-5 sm:grid-cols-2">
            <Feature
              icon={Headphones}
              title="One service console"
              description="Open, accepted, and completed work stays visible."
            />
            <Feature
              icon={CheckCircle2}
              title="Complete history"
              description="Every attempt and outcome remains accountable."
            />
          </div>
        </div>

        <p className="relative text-xs text-slate-400">
          Built for practical service teams across India.
        </p>
      </section>

      <section className="relative flex min-h-screen flex-col px-5 py-5 sm:px-8 lg:px-12 lg:py-8 xl:px-20">
        <div className="flex items-center justify-between lg:justify-end">
          <div className="flex items-center gap-2 lg:hidden">
            <img
              src="/onesalez-logo.png"
              alt="ONESALEZ"
              className="h-9 w-9 rounded-lg object-contain"
            />
            <span className="text-sm font-bold tracking-wide">ONESALEZ</span>
          </div>
          <button
            type="button"
            onClick={() => setDarkMode((value) => !value)}
            aria-label={darkMode ? "Use light theme" : "Use dark theme"}
            className="grid h-10 w-10 place-items-center rounded-xl border border-[var(--border)] bg-[var(--surface)] text-[var(--muted)] transition hover:text-[var(--text)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--focus)]"
          >
            {darkMode ? (
              <Sun aria-hidden="true" className="h-4.5 w-4.5" />
            ) : (
              <Moon aria-hidden="true" className="h-4.5 w-4.5" />
            )}
          </button>
        </div>

        <div className="mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-10 sm:py-14">
          <div className="mb-8">
            <div className="mb-4 flex items-center gap-2 text-xs font-medium text-[var(--muted)]">
              <span
                className={cn(
                  "h-2 w-2 rounded-full",
                  health.isSuccess
                    ? "bg-emerald-500"
                    : health.isError
                      ? "bg-amber-500"
                      : "animate-pulse bg-slate-400",
                )}
              />
              {health.isSuccess
                ? "Service API connected"
                : health.isError
                  ? "Working in interface preview mode"
                  : "Checking service connection"}
            </div>
            <h2 className="text-3xl font-semibold tracking-tight text-[var(--text)]">
              Welcome back
            </h2>
            <p className="mt-2 text-sm leading-6 text-[var(--muted)]">
              Use quick PIN login on this device, or sign in with your password.
            </p>
          </div>

          {profiles.length > 0 && (
            <div className="mb-7 rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-4 shadow-sm">
              <div className="mb-3 flex items-center gap-2">
                <KeyRound
                  aria-hidden="true"
                  className="h-4.5 w-4.5 text-[var(--brand)]"
                />
                <p className="text-sm font-semibold text-[var(--text)]">
                  Quick login
                </p>
              </div>
              <div className="mb-4 space-y-2">
                {profiles.map((profile) => (
                  <div
                    key={profile.deviceId}
                    className={cn(
                      "flex items-center rounded-xl border p-1 transition",
                      selectedProfile?.deviceId === profile.deviceId
                        ? "border-[var(--brand)] bg-[var(--surface-soft)]"
                        : "border-[var(--border)]",
                    )}
                  >
                    <button
                      type="button"
                      onClick={() => {
                        setSelectedProfile(profile);
                        setQuickPin("");
                      }}
                      className="min-w-0 flex-1 px-2 py-1.5 text-left"
                    >
                      <span className="block truncate text-sm font-semibold text-[var(--text)]">
                        {profile.actor.displayName || profile.actor.email}
                      </span>
                      <span className="block truncate text-xs text-[var(--muted)]">
                        {profile.actor.email} ·{" "}
                        {profile.actor.type === "EMPLOYEE"
                          ? "Employee"
                          : "Client"}
                      </span>
                    </button>
                    <button
                      type="button"
                      onClick={() => forgetProfile(profile)}
                      aria-label={`Forget ${profile.actor.email}`}
                      className="grid h-9 w-9 place-items-center rounded-lg text-[var(--muted)] hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30"
                    >
                      <Trash2 aria-hidden="true" className="h-4 w-4" />
                    </button>
                  </div>
                ))}
              </div>
              <form onSubmit={submitQuickLogin} className="flex gap-2">
                <Input
                  type="password"
                  value={quickPin}
                  onChange={(event) =>
                    setQuickPin(
                      event.target.value.replace(/\D/g, "").slice(0, 6),
                    )
                  }
                  inputMode="numeric"
                  autoComplete="off"
                  maxLength={6}
                  placeholder="6-digit PIN"
                  aria-label="6-digit PIN"
                  className="text-center tracking-[0.35em]"
                />
                <Button
                  type="submit"
                  disabled={quickSubmitting}
                  className="shrink-0 px-4"
                >
                  {quickSubmitting ? "Checking…" : "Login"}
                </Button>
              </form>
            </div>
          )}

          {profiles.length > 0 && (
            <div className="mb-6 flex items-center gap-3 text-xs text-[var(--muted)]">
              <span className="h-px flex-1 bg-[var(--border)]" />
              Password login
              <span className="h-px flex-1 bg-[var(--border)]" />
            </div>
          )}

          <div
            className="mb-6 grid grid-cols-2 rounded-2xl bg-[var(--surface-soft)] p-1.5"
            aria-label="Choose workspace"
          >
            <RealmButton
              active={realm === "client"}
              icon={Building2}
              label="Client portal"
              onClick={() => setValue("realm", "client")}
            />
            <RealmButton
              active={realm === "employee"}
              icon={UserRoundCog}
              label="Employee"
              onClick={() => setValue("realm", "employee")}
            />
          </div>

          <form onSubmit={submitLogin} noValidate className="space-y-5">
            <input type="hidden" {...register("realm")} />
            <Field label="Email address" error={errors.email?.message}>
              <Input
                type="email"
                aria-label="Email address"
                autoComplete="email"
                placeholder="name@company.com"
                aria-invalid={Boolean(errors.email)}
                {...register("email", {
                  onChange: (event) => {
                    event.target.value = event.target.value.toLowerCase();
                  },
                })}
              />
            </Field>
            <Field
              label="Password"
              error={errors.password?.message}
              action={
                <button
                  type="button"
                  onClick={() => setForgotOpen(true)}
                  className="text-xs font-semibold text-[var(--brand)] hover:underline"
                >
                  Forgot password?
                </button>
              }
            >
              <div className="relative">
                <Input
                  aria-label="Password"
                  type={showPassword ? "text" : "password"}
                  autoComplete="current-password"
                  placeholder="Enter your password"
                  aria-invalid={Boolean(errors.password)}
                  className="pr-11"
                  {...register("password")}
                />
                <button
                  type="button"
                  onClick={() => setShowPassword((value) => !value)}
                  aria-label={showPassword ? "Hide password" : "Show password"}
                  className="absolute inset-y-0 right-0 grid w-11 place-items-center text-[var(--muted)] hover:text-[var(--text)]"
                >
                  {showPassword ? (
                    <EyeOff aria-hidden="true" className="h-4.5 w-4.5" />
                  ) : (
                    <Eye aria-hidden="true" className="h-4.5 w-4.5" />
                  )}
                </button>
              </div>
            </Field>

            <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-[var(--border)] bg-[var(--surface-soft)] p-3">
              <input
                type="checkbox"
                {...register("trustDevice")}
                className="mt-0.5 h-4 w-4 accent-[var(--brand)]"
              />
              <span>
                <span className="block text-sm font-semibold text-[var(--text)]">
                  Enable 6-digit PIN on this device
                </span>
                <span className="mt-0.5 block text-xs leading-5 text-[var(--muted)]">
                  Use only on a private device you control.
                </span>
              </span>
            </label>

            {trustDevice && (
              <div className="grid grid-cols-2 gap-3">
                <Field label="Create PIN" error={errors.quickPin?.message}>
                  <Input
                    aria-label="Create PIN"
                    type="password"
                    inputMode="numeric"
                    autoComplete="off"
                    maxLength={6}
                    placeholder="6 digits"
                    className="text-center tracking-[0.25em]"
                    {...register("quickPin", {
                      onChange: (event) => {
                        event.target.value = event.target.value
                          .replace(/\D/g, "")
                          .slice(0, 6);
                      },
                    })}
                  />
                </Field>
                <Field label="Confirm PIN" error={errors.confirmPin?.message}>
                  <Input
                    aria-label="Confirm PIN"
                    type="password"
                    inputMode="numeric"
                    autoComplete="off"
                    maxLength={6}
                    placeholder="Repeat PIN"
                    className="text-center tracking-[0.25em]"
                    {...register("confirmPin", {
                      onChange: (event) => {
                        event.target.value = event.target.value
                          .replace(/\D/g, "")
                          .slice(0, 6);
                      },
                    })}
                  />
                </Field>
              </div>
            )}

            {notice && (
              <div
                role="alert"
                className="flex gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-sm leading-5 text-red-900 dark:border-red-800 dark:bg-red-950/40 dark:text-red-100"
              >
                <CircleAlert
                  aria-hidden="true"
                  className="mt-0.5 h-4 w-4 shrink-0"
                />
                {notice}
              </div>
            )}

            <Button
              type="submit"
              disabled={isSubmitting}
              className="w-full gap-2"
            >
              {isSubmitting
                ? "Signing in…"
                : `Sign in to ${realm === "client" ? "client portal" : "service console"}`}
              <ArrowRight aria-hidden="true" className="h-4 w-4" />
            </Button>
          </form>

          <p className="mt-8 text-center text-xs leading-5 text-[var(--muted)]">
            Need access? Contact your{" "}
            {realm === "client"
              ? "client administrator"
              : "ONESALEZ system administrator"}
            .
          </p>
          <div className="mt-5 border-t border-[var(--border)] pt-4 text-center">
            <button
              type="button"
              onClick={handleResetApp}
              disabled={resettingApp || isSubmitting || quickSubmitting}
              aria-describedby="reset-app-help"
              className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 text-sm font-semibold text-[var(--brand)] hover:bg-[var(--surface-soft)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--focus)] disabled:opacity-60"
            >
              <RotateCcw
                aria-hidden="true"
                className={cn("h-4 w-4", resettingApp && "animate-spin")}
              />
              {resettingApp ? "Resetting app…" : "Reset app"}
            </button>
            <p
              id="reset-app-help"
              className="mt-1 text-xs leading-5 text-[var(--muted)]"
            >
              Page out of date? Clear cached app files and reload. Saved PINs
              are kept.
            </p>
            {resetError && (
              <p role="alert" className="mt-2 text-sm text-[var(--danger)]">
                {resetError}
              </p>
            )}
            {resettingApp && (
              <p role="status" className="sr-only">
                Clearing cached app files and loading the latest version.
              </p>
            )}
          </div>
        </div>

        <footer className="text-center text-[11px] text-[var(--muted)]">
          © 2026 ONESALEZ · Secure service workspace
        </footer>
      </section>
      {forgotOpen && (
        <ForgotPasswordDialog
          initialRealm={realm}
          initialEmail={email}
          onClose={() => setForgotOpen(false)}
        />
      )}
    </main>
  );
}

interface FeatureProps {
  icon: typeof Headphones;
  title: string;
  description: string;
}

function Feature({ icon: Icon, title, description }: FeatureProps) {
  return (
    <div className="flex gap-3">
      <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white/8 text-blue-200">
        <Icon aria-hidden="true" className="h-5 w-5" />
      </div>
      <div>
        <p className="text-sm font-semibold">{title}</p>
        <p className="mt-1 text-xs leading-5 text-slate-400">{description}</p>
      </div>
    </div>
  );
}

interface RealmButtonProps {
  active: boolean;
  icon: typeof Building2;
  label: string;
  onClick: () => void;
}

function RealmButton({ active, icon: Icon, label, onClick }: RealmButtonProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={cn(
        "flex h-10 items-center justify-center gap-2 rounded-xl text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--focus)] sm:text-sm",
        active
          ? "bg-[var(--surface)] text-[var(--text)] shadow-sm"
          : "text-[var(--muted)] hover:text-[var(--text)]",
      )}
    >
      <Icon aria-hidden="true" className="h-4 w-4" />
      {label}
    </button>
  );
}

interface FieldProps {
  label: string;
  error?: string;
  action?: React.ReactNode;
  children: React.ReactNode;
}

function Field({ label, error, action, children }: FieldProps) {
  return (
    <div className="block">
      <span className="mb-2 flex items-center justify-between text-sm font-medium text-[var(--text)]">
        {label}
        {action}
      </span>
      {children}
      {error && (
        <span className="mt-1.5 block text-xs text-[var(--danger)]">
          {error}
        </span>
      )}
    </div>
  );
}
