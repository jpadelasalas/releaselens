type MetricCardProps = {
  label: string
  value: string
  detail: string
}

export function MetricCard({ label, value, detail }: MetricCardProps) {
  return (
    <article className="metric-card grid gap-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-[18px]">
      <span className="text-[13px] font-extrabold text-[var(--color-subtle)]">
        {label}
      </span>
      <strong className="text-[34px] text-[var(--color-heading)]">{value}</strong>
      <p className="text-[var(--color-muted)]">{detail}</p>
    </article>
  )
}
