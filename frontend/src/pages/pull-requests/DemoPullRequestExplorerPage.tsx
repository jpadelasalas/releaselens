import ArrowBackOutlinedIcon from '@mui/icons-material/ArrowBackOutlined'
import ChevronLeftOutlinedIcon from '@mui/icons-material/ChevronLeftOutlined'
import ChevronRightOutlinedIcon from '@mui/icons-material/ChevronRightOutlined'
import { Link, useSearchParams } from 'react-router-dom'
import { useScopeContext } from '../../app/scope/useScopeContext'
import { ThemeToggle } from '../../components/theme/ThemeToggle'
import type { PullRequestRecord } from '../../features/pull-requests/pullRequestApi'
import {
  getExplorerTitle,
  parsePullRequestExplorerFilters,
} from '../../features/pull-requests/pullRequestExplorerUrl'
import { usePullRequestExplorer } from '../../features/pull-requests/usePullRequestExplorer'
import { DashboardNav } from '../dashboard/components/DashboardNav'

export function DemoPullRequestExplorerPage() {
  const { scope } = useScopeContext()
  const [searchParams, setSearchParams] = useSearchParams()
  const organizationId = scope.kind === 'demo' ? scope.organization.id : null
  const filters = parsePullRequestExplorerFilters(searchParams)
  const pullRequestsQuery = usePullRequestExplorer(organizationId, filters)

  if (scope.kind !== 'demo') {
    return (
      <main className="centered-page">
        <h1>Open the demo workspace first.</h1>
        <Link className="primary-action" to="/">
          Go to Landing Page
        </Link>
      </main>
    )
  }

  const response = pullRequestsQuery.data

  function setPage(page: number) {
    const nextParams = new URLSearchParams(searchParams)

    if (page <= 1) {
      nextParams.delete('page')
    } else {
      nextParams.set('page', String(page))
    }

    setSearchParams(nextParams)
  }

  return (
    <main className="dashboard-shell">
      <DashboardNav activeItem="Pull Requests" />

      <section className="dashboard-main min-w-0">
        <header className="dashboard-header">
          <div>
            <Link
              className="mb-2 inline-flex items-center gap-1 text-sm font-bold text-[var(--color-primary)] no-underline"
              to="/demo/dashboard"
            >
              <ArrowBackOutlinedIcon fontSize="small" />
              Dashboard
            </Link>
            <p className="eyebrow">Supporting records</p>
            <h1>{getExplorerTitle(filters)}</h1>
            <p>
              Exact records behind the dashboard metric under the preserved
              repository and date filters.
            </p>
          </div>
          <div className="dashboard-header-actions">
            <ThemeToggle />
            {response && (
              <span className="demo-badge">{response.meta.total} matching</span>
            )}
          </div>
        </header>

        {pullRequestsQuery.isLoading && <ExplorerLoadingState />}

        {pullRequestsQuery.isError && (
          <section className="retry-panel dashboard-error" role="alert">
            <strong>Pull requests are unavailable.</strong>
            <span>Retry the filtered explorer request.</span>
            <button type="button" onClick={() => void pullRequestsQuery.refetch()}>
              Retry
            </button>
          </section>
        )}

        {response && response.data.length === 0 && (
          <section className="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-6">
            <h2 className="text-[var(--color-heading)]">No matching pull requests</h2>
            <p className="text-[var(--color-muted)]">
              The current metric filters do not match any records.
            </p>
            <Link className="primary-action mt-4" to="/demo/dashboard">
              Clear Filters
            </Link>
          </section>
        )}

        {response && response.data.length > 0 && (
          <>
            <PullRequestTable records={response.data} />
            <nav
              className="mt-4 flex items-center justify-between"
              aria-label="Pull request pagination"
            >
              <span className="text-sm text-[var(--color-muted)]">
                Page {response.meta.current_page} of {response.meta.last_page}
              </span>
              <div className="flex gap-2">
                <PaginationButton
                  label="Previous page"
                  disabled={response.meta.current_page <= 1}
                  onClick={() => setPage(response.meta.current_page - 1)}
                >
                  <ChevronLeftOutlinedIcon />
                </PaginationButton>
                <PaginationButton
                  label="Next page"
                  disabled={response.meta.current_page >= response.meta.last_page}
                  onClick={() => setPage(response.meta.current_page + 1)}
                >
                  <ChevronRightOutlinedIcon />
                </PaginationButton>
              </div>
            </nav>
          </>
        )}
      </section>
    </main>
  )
}

function PullRequestTable({ records }: { records: PullRequestRecord[] }) {
  return (
    <div className="overflow-x-auto rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]">
      <table className="w-full min-w-[900px] border-collapse text-left">
        <thead className="bg-[var(--color-primary-soft)] text-xs uppercase text-[var(--color-muted)]">
          <tr>
            <th className="p-3">Pull request</th>
            <th className="p-3">Repository</th>
            <th className="p-3">Author</th>
            <th className="p-3">Age</th>
            <th className="p-3">Size</th>
            <th className="p-3">Review</th>
            <th className="p-3">Attention</th>
          </tr>
        </thead>
        <tbody>
          {records.map((record) => (
            <tr key={record.id} className="border-t border-[var(--color-border)]">
              <td className="p-3">
                <strong className="block text-[var(--color-heading)]">
                  #{record.number} {record.title}
                </strong>
                <span className="text-xs text-[var(--color-subtle)]">
                  {record.state}{record.is_draft ? ' - Draft' : ''}
                </span>
              </td>
              <td className="p-3 text-[var(--color-muted)]">
                {record.repository.name}
              </td>
              <td className="p-3 text-[var(--color-muted)]">
                {record.author ?? 'Unknown'}
              </td>
              <td className="p-3 text-[var(--color-muted)]">
                {formatHours(record.age_hours)}
              </td>
              <td className="p-3 text-[var(--color-muted)]">
                {record.change_size} lines
              </td>
              <td className="p-3">
                <span className="rounded-full bg-[var(--color-warning-bg)] px-2 py-1 text-xs font-bold text-[var(--color-heading)]">
                  {formatReason(record.review_status)}
                </span>
              </td>
              <td className="p-3">
                <div className="flex flex-wrap gap-1">
                  {record.attention_reasons.map((reason) => (
                    <span
                      key={reason}
                      className="rounded-full bg-[var(--color-primary-soft)] px-2 py-1 text-xs font-bold text-[var(--color-primary-strong)]"
                    >
                      {formatReason(reason)}
                    </span>
                  ))}
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function ExplorerLoadingState() {
  return (
    <div
      className="h-[420px] animate-pulse rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]"
      aria-label="Loading pull requests"
      aria-busy="true"
    />
  )
}

function PaginationButton({
  children,
  disabled,
  label,
  onClick,
}: {
  children: React.ReactNode
  disabled: boolean
  label: string
  onClick: () => void
}) {
  return (
    <button
      className="inline-grid min-h-10 min-w-10 place-items-center rounded-md border border-[var(--color-border-strong)] text-[var(--color-heading)] disabled:cursor-not-allowed disabled:opacity-40"
      type="button"
      disabled={disabled}
      onClick={onClick}
      aria-label={label}
      title={label}
    >
      {children}
    </button>
  )
}

function formatHours(hours: number): string {
  if (hours < 24) {
    return `${hours}h`
  }

  const days = hours / 24
  return `${Number.isInteger(days) ? days : days.toFixed(1)}d`
}

function formatReason(reason: string): string {
  return reason
    .toLowerCase()
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}
