import type { AnalyticsBucket } from '../../../features/analytics/analyticsApi'

type DistributionPanelProps = {
  title: string
  description: string
  buckets: AnalyticsBucket[]
}

export function DistributionPanel({
  title,
  description,
  buckets,
}: DistributionPanelProps) {
  const maxCount = Math.max(1, ...buckets.map((bucket) => bucket.count))

  return (
    <article className="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-[22px]">
      <h2 className="text-[var(--color-heading)]">{title}</h2>
      <p className="text-[var(--color-muted)]">{description}</p>
      <div className="mt-5 grid gap-3">
        {buckets.map((bucket) => (
          <div key={bucket.key} className="grid grid-cols-[100px_1fr_32px] items-center gap-3">
            <span className="text-[13px] text-[var(--color-muted)]">
              {bucket.label}
            </span>
            <div className="h-2 overflow-hidden rounded-full bg-[var(--color-primary-soft)]">
              <div
                className="h-full rounded-full bg-[var(--color-primary)]"
                style={{ width: `${(bucket.count / maxCount) * 100}%` }}
              />
            </div>
            <strong className="text-right text-[var(--color-heading)]">
              {bucket.count}
            </strong>
          </div>
        ))}
      </div>
    </article>
  )
}
