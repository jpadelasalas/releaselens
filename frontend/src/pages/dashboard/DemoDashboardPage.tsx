import { lazy, Suspense } from 'react'
import { Link } from 'react-router-dom'
import { useScopeContext } from '../../app/scope/useScopeContext'
import { ThemeToggle } from '../../components/theme/ThemeToggle'
import type { AnalyticsAttentionRecord } from '../../features/analytics/analyticsApi'
import { useDashboardAnalytics } from '../../features/analytics/useDashboardAnalytics'
import { useDashboardFilters } from '../../features/analytics/useDashboardFilters'
import { useOrganizationRepositories } from '../../features/repositories/useOrganizationRepositories'
import {
  buildAgeBucketUrl,
  buildAttentionUrl,
  buildClosedWithoutMergeUrl,
  buildSizeBucketUrl,
  buildWaitingForReviewUrl,
  buildWeeklyPointUrl,
} from '../../features/pull-requests/pullRequestExplorerUrl'
import { DashboardFilters } from './components/DashboardFilters'
import { DashboardLoadingSkeleton } from './components/DashboardLoadingSkeleton'
import { DashboardNav } from './components/DashboardNav'
import { DistributionPanel } from './components/DistributionPanel'
import { MetricCard } from './components/MetricCard'

const WeeklyFlowChart = lazy(() =>
  import('./components/WeeklyFlowChart').then((module) => ({
    default: module.WeeklyFlowChart,
  })),
)

export function DemoDashboardPage() {
  const { scope } = useScopeContext()
  const organizationId = scope.kind === 'demo' ? scope.organization.id : null
  const anchorDate = scope.kind === 'demo' ? scope.demo.anchor_date : null
  const dashboardFilters = useDashboardFilters(anchorDate)
  const dashboardQuery = useDashboardAnalytics(
    organizationId,
    dashboardFilters.filters,
  )
  const repositoriesQuery = useOrganizationRepositories(organizationId)

  if (scope.kind !== 'demo') {
    return (
      <main className="centered-page">
        <p className="eyebrow">Demo session required</p>
        <h1>Open the demo workspace first.</h1>
        <p>
          The dashboard is scoped to an anonymous read-only demo session. No
          registration is required.
        </p>
        <Link className="primary-action" to="/">
          Go to Landing Page
        </Link>
      </main>
    )
  }

  const analytics = dashboardQuery.data
  const metrics = analytics?.summary.metrics
  const largePrBucket = analytics?.distributions.buckets.pr_size.find(
    (bucket) => bucket.key === 'large',
  )
  const attentionRecords = analytics?.attention.records.slice(0, 5) ?? []
  const freshnessLabel = analytics?.summary.demo_freshness_at
    ? `Fresh ${formatDateTime(analytics.summary.demo_freshness_at)}`
    : 'Freshness pending'
  const repositoryCount = analytics?.summary.selected_repository_count ?? 0

  return (
    <main className="dashboard-shell">
      <DashboardNav />

      <section className="dashboard-main">
        <header className="dashboard-header">
          <div>
            <p className="eyebrow">Demo workspace</p>
            <h1>{scope.organization.name}</h1>
            <p>
              Read-only synthetic data - {scope.organization.timezone} - Session{' '}
              {scope.sessionId.slice(0, 8)}
            </p>
          </div>
          <div className="dashboard-header-actions">
            <ThemeToggle />
            {dashboardQuery.isSuccess && (
              <span className="freshness">{freshnessLabel}</span>
            )}
            <span className="demo-badge">Demo read-only</span>
          </div>
        </header>

        {dashboardQuery.isLoading ? (
          <DashboardLoadingSkeleton />
        ) : dashboardQuery.isError ? (
          <section className="retry-panel dashboard-error" role="alert">
            <strong>Dashboard analytics are unavailable.</strong>
            <span>Free-tier services may still be waking up. Retry in a moment.</span>
            <button type="button" onClick={() => void dashboardQuery.refetch()}>
              Retry Dashboard
            </button>
          </section>
        ) : (
          <>
            <DashboardFilters
              repositories={repositoriesQuery.data ?? []}
              initialFilters={dashboardFilters.defaults}
              disabled={repositoriesQuery.isLoading || dashboardQuery.isFetching}
              onApply={dashboardFilters.applyFilters}
              onClear={dashboardFilters.clearFilters}
            />

            <section className="dashboard-meta" aria-label="Dashboard freshness">
              <span>
                {dashboardQuery.isSuccess ? repositoryCount : '...'} repositories selected
              </span>
              <span>{dashboardQuery.isSuccess ? freshnessLabel : 'Loading analytics...'}</span>
            </section>

            <section className="metrics-grid" aria-label="Dashboard metrics">
              <MetricCard
                label="Waiting for review"
                value={
                  metrics
                    ? String(metrics.waiting_for_first_review)
                    : '...'
                }
                detail="Open, non-draft PRs without a qualifying human review."
                to={buildWaitingForReviewUrl(dashboardFilters.filters)}
                actionLabel="View waiting pull requests"
              />
              <MetricCard
                label="Median first review"
                value={
                  metrics
                    ? formatHours(metrics.median_first_review_hours)
                    : '...'
                }
                detail={
                  metrics
                    ? `${metrics.median_first_review_sample_size} qualifying reviewed PRs.`
                    : 'Submitted human review excluding self, bot, pending, and dismissed reviews.'
                }
              />
              <MetricCard
                label="Median merge time"
                value={
                  metrics ? formatHours(metrics.median_merge_hours) : '...'
                }
                detail={
                  metrics
                    ? `${metrics.median_merge_sample_size} merged PRs in the active filters.`
                    : 'Merged pull requests in the seeded demo window.'
                }
              />
              <MetricCard
                label="Large PRs"
                value={largePrBucket ? String(largePrBucket.count) : '...'}
                detail="Pull requests above 500 changed lines."
                to={buildSizeBucketUrl(dashboardFilters.filters, 'large')}
                actionLabel="View large pull requests"
              />
              <MetricCard
                label="Closed without merge"
                value={metrics ? String(metrics.closed_without_merge) : '...'}
                detail="Closed pull requests that were not merged."
                to={buildClosedWithoutMergeUrl(dashboardFilters.filters)}
                actionLabel="View closed pull requests"
              />
              <MetricCard
                label="Attention count"
                value={metrics ? String(metrics.attention_count) : '...'}
                detail="Open pull requests matching explicit attention rules."
                to={buildAttentionUrl(dashboardFilters.filters)}
                actionLabel="View attention records"
              />
            </section>

            <section
              className="mt-[18px] grid gap-[18px] lg:grid-cols-2"
              aria-label="Pull request distributions"
            >
              <DistributionPanel
                title="Open PR age"
                description="Current open pull requests grouped by age."
                buckets={analytics?.distributions.buckets.open_pr_age ?? []}
                getBucketUrl={(bucket) =>
                  buildAgeBucketUrl(
                    dashboardFilters.filters,
                    bucket.key as 'under_1_day' | '1_to_3_days' | '3_to_7_days' | 'over_7_days',
                  )
                }
              />
              <DistributionPanel
                title="PR size"
                description="Pull requests grouped by additions plus deletions."
                buckets={analytics?.distributions.buckets.pr_size ?? []}
                getBucketUrl={(bucket) =>
                  buildSizeBucketUrl(
                    dashboardFilters.filters,
                    bucket.key as 'xs' | 'small' | 'medium' | 'large',
                  )
                }
              />
            </section>

            <section className="dashboard-panels">
              <Suspense fallback={<ChartLoadingFallback />}>
                <WeeklyFlowChart
                  series={
                    analytics?.trends.series.opened_vs_merged_by_week ?? []
                  }
                  getPointUrl={(event, week) =>
                    buildWeeklyPointUrl(
                      dashboardFilters.filters,
                      event,
                      week,
                    )
                  }
                />
              </Suspense>

              <article className="attention-panel">
                <h2>Attention list</h2>
                {dashboardQuery.isLoading && (
                  <p>Loading attention records...</p>
                )}
                {dashboardQuery.isSuccess &&
                  attentionRecords.length === 0 && (
                    <p>No pull requests match the active attention rules.</p>
                  )}
                {attentionRecords.length > 0 && (
                  <ul>
                    {attentionRecords.map((record) => (
                      <AttentionItem key={record.pull_request_id} record={record} />
                    ))}
                  </ul>
                )}
              </article>
            </section>
          </>
        )}
      </section>
    </main>
  )
}

function ChartLoadingFallback() {
  return (
    <article className="flow-panel" aria-busy="true">
      <h2>Opened versus merged</h2>
      <p>Loading chart...</p>
      <div className="mt-5 h-[300px] animate-pulse rounded-md bg-[var(--color-primary-soft)]" />
    </article>
  )
}

function AttentionItem({ record }: { record: AnalyticsAttentionRecord }) {
  return (
    <li>
      <strong>
        {record.repository} #{record.number}
      </strong>
      <span>{formatAttentionReasons(record.reasons)}</span>
      <span>
        {formatAge(record.age_hours)} old - {record.change_size} changed lines
      </span>
    </li>
  )
}

function formatHours(hours: number | null): string {
  if (hours === null) {
    return 'N/A'
  }

  if (hours < 24) {
    return `${round(hours)}h`
  }

  return `${round(hours / 24)}d`
}

function formatAge(hours: number): string {
  return formatHours(hours)
}

function formatDateTime(value: string): string {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function formatAttentionReasons(reasons: string[]): string {
  return reasons.map(formatReason).join(', ')
}

function formatReason(reason: string): string {
  return reason
    .toLowerCase()
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}

function round(value: number): string {
  return Number.isInteger(value) ? String(value) : value.toFixed(1)
}
