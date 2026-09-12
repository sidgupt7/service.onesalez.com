import { useState } from "react";
import { Button } from "../../components/ui/Button";
import { useTheme } from "../../lib/use-theme";
import { resetApp as resetApplicationCache } from "../../lib/reset-app";
import { useAuth } from "../auth/AuthProvider";
import { ChangePasswordDialog } from "../auth/ChangePasswordDialog";
import { trustedProfiles, forgetTrustedProfile } from "../auth/auth-api";

export function SettingsPage() {
  const { actor, authenticatedRequest } = useAuth();
  const [dark, setDark] = useTheme();
  const [password, setPassword] = useState(false);
  const [profiles, setProfiles] = useState(() =>
    trustedProfiles().filter((profile) => profile.actor.email === actor?.email),
  );
  const [error, setError] = useState("");
  const [pending, setPending] = useState(false);
  const forget = async (id: string) => {
    setPending(true);
    setError("");
    try {
      await authenticatedRequest("/auth/trusted-device", {
        method: "DELETE",
        body: JSON.stringify({ device_id: id }),
      });
      forgetTrustedProfile(id);
      setProfiles((values) =>
        values.filter((profile) => profile.deviceId !== id),
      );
    } catch (error) {
      setError(
        error instanceof Error ? error.message : "Could not remove device.",
      );
    } finally {
      setPending(false);
    }
  };
  return (
    <main className="mx-auto max-w-3xl">
      <h1 className="text-2xl font-bold">Settings</h1>
      <section className="mt-5 space-y-5 rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6">
        <div>
          <h2 className="font-bold">Your account</h2>
          <p className="mt-2">{actor?.displayName}</p>
          <p className="text-sm text-[var(--muted)]">{actor?.email}</p>
          <p className="mt-1 text-xs">{actor?.roles.join(", ")}</p>
          <Button className="mt-3" onClick={() => setPassword(true)}>
            Change password
          </Button>
        </div>
        <div>
          <h2 className="font-bold">Appearance</h2>
          <label className="mt-3 flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={dark}
              onChange={(event) => setDark(event.target.checked)}
            />
            Use dark theme on this browser
          </label>
        </div>
        <div>
          <h2 className="font-bold">Quick PIN login on this browser</h2>
          {profiles.length === 0 ? (
            <p className="mt-2 text-sm text-[var(--muted)]">
              No saved PIN login for this account.
            </p>
          ) : (
            profiles.map((profile) => (
              <div
                key={profile.deviceId}
                className="mt-3 flex items-center justify-between gap-3"
              >
                <span className="text-sm">{profile.deviceName}</span>
                <Button
                  disabled={pending}
                  onClick={() => void forget(profile.deviceId)}
                >
                  Remove PIN login
                </Button>
              </div>
            ))
          )}
        </div>
        <div>
          <h2 className="font-bold">Application cache</h2>
          <p className="my-2 text-sm text-[var(--muted)]">
            Reload the latest app files when this browser shows an outdated
            page.
          </p>
          <Button
            disabled={pending}
            onClick={async () => {
              setPending(true);
              try {
                await resetApplicationCache();
              } catch (error) {
                setError(
                  error instanceof Error ? error.message : "Reset failed.",
                );
                setPending(false);
              }
            }}
          >
            Reset app
          </Button>
        </div>
        {error && (
          <p role="alert" className="text-red-700">
            {error}
          </p>
        )}
      </section>
      {password && <ChangePasswordDialog onClose={() => setPassword(false)} />}
    </main>
  );
}
