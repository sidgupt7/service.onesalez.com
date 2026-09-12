// The API's MariaDB DATETIME fields are stored in India Standard Time.
// ISO timestamps with an explicit offset are preserved as received.
export function serviceDate(value: string): Date {
  return new Date(
    /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/.test(value)
      ? `${value.replace(" ", "T").replace(/(\.\d{3})\d+/, "$1")}+05:30`
      : value,
  );
}

export function formatServiceDate(value: string): string {
  const date = serviceDate(value);
  return Number.isNaN(date.getTime())
    ? "—"
    : new Intl.DateTimeFormat("en-IN", {
        dateStyle: "medium",
        timeStyle: "short",
        timeZone: "Asia/Kolkata",
      }).format(date);
}
