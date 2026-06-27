export function DashboardLoadingSkeleton() {
  return (
    <div aria-label="Loading dashboard analytics" aria-busy="true">
      <div className="mb-4 h-[74px] animate-pulse rounded-lg bg-[var(--color-primary-soft)]" />

      <div className="grid gap-[14px] md:grid-cols-2 xl:grid-cols-4">
        {Array.from({ length: 4 }, (_, index) => (
          <div
            key={index}
            className="grid gap-3 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-[18px]"
          >
            <span className="h-3 w-28 animate-pulse rounded bg-[var(--color-primary-soft)]" />
            <span className="h-10 w-20 animate-pulse rounded bg-[var(--color-primary-soft)]" />
            <span className="h-3 w-full animate-pulse rounded bg-[var(--color-primary-soft)]" />
          </div>
        ))}
      </div>

      <div className="mt-[18px] grid gap-[18px] lg:grid-cols-2">
        {Array.from({ length: 2 }, (_, index) => (
          <div
            key={index}
            className="h-[220px] animate-pulse rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]"
          />
        ))}
      </div>

      <div className="mt-[18px] grid gap-[18px] lg:grid-cols-[minmax(0,1.4fr)_minmax(300px,0.6fr)]">
        <div className="h-[350px] animate-pulse rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]" />
        <div className="h-[350px] animate-pulse rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]" />
      </div>
    </div>
  )
}
