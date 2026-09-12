export function csvCell(value: unknown): string {
  let text = String(value ?? "");
  // Spreadsheet applications can execute formulas even inside quoted CSV cells.
  if (/^[\s\u0000-\u001f]*[=+@-]/.test(text) || /^[\t\r\n]/.test(text))
    text = `'${text}`;
  return `"${text.replaceAll('"', '""')}"`;
}
