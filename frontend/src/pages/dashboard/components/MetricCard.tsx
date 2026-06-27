import ArrowForwardOutlinedIcon from '@mui/icons-material/ArrowForwardOutlined'
import HelpOutlineOutlinedIcon from '@mui/icons-material/HelpOutlineOutlined'
import { Link } from 'react-router-dom'

type MetricCardProps = {
  label: string
  value: string
  detail: string
  to?: string
  actionLabel?: string
  definitionTo?: string
}

export function MetricCard({
  label,
  value,
  detail,
  to,
  actionLabel = 'View supporting records',
  definitionTo,
}: MetricCardProps) {
  return (
    <article className="metric-card grid gap-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-[18px]">
      <span className="text-[13px] font-extrabold text-[var(--color-subtle)]">
        {label}
      </span>
      <strong className="text-[34px] text-[var(--color-heading)]">{value}</strong>
      <p className="text-[var(--color-muted)]">{detail}</p>
      {(to || definitionTo) && (
        <div className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-2">
          {to && (
            <Link
              className="inline-flex items-center gap-1 text-[13px] font-extrabold text-[var(--color-primary)] no-underline"
              to={to}
            >
              {actionLabel}
              <ArrowForwardOutlinedIcon fontSize="inherit" aria-hidden="true" />
            </Link>
          )}
          {definitionTo && (
            <Link
              className="inline-flex items-center gap-1 text-[13px] font-extrabold text-[var(--color-muted)] no-underline"
              to={definitionTo}
            >
              <HelpOutlineOutlinedIcon fontSize="inherit" aria-hidden="true" />
              Definition
            </Link>
          )}
        </div>
      )}
    </article>
  )
}
