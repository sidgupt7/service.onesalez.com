import { Button } from "./Button";

export function Pagination({
  page,
  limit,
  total,
  pending,
  onPage,
}: {
  page: number;
  limit: number;
  total: number;
  pending?: boolean;
  onPage: (page: number) => void;
}) {
  const pages = Math.max(1, Math.ceil(total / limit));
  return (
    <nav
      aria-label="Pagination"
      className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm"
    >
      <p role="status">
        {total === 0
          ? "No records"
          : `${(page - 1) * limit + 1}–${Math.min(page * limit, total)} of ${total}`}{" "}
        · Page {page} of {pages}
      </p>
      <div className="flex gap-2">
        <Button
          disabled={pending || page <= 1}
          onClick={() => onPage(page - 1)}
        >
          Previous
        </Button>
        <Button
          disabled={pending || page >= pages}
          onClick={() => onPage(page + 1)}
        >
          Next
        </Button>
      </div>
    </nav>
  );
}
