import { describe, expect, it } from "vitest";
import { csvCell } from "./csv";

describe("CSV export", () => {
  it("quotes separators, line breaks, and embedded quotes", () => {
    expect(csvCell('North, "Branch"\nOffice')).toBe(
      '"North, ""Branch""\nOffice"',
    );
  });
  it.each([
    '=HYPERLINK("https://example.invalid")',
    "+1+1",
    "-1+2",
    "@SUM(A1)",
    "  =1",
    "\ttext",
  ])("neutralizes spreadsheet input %s", (value) => {
    expect(csvCell(value).startsWith("\"'")).toBe(true);
  });
});
