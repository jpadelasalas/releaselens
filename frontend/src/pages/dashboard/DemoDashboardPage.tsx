import { lazy, Suspense } from 'react'
import { Link } from 'react-router-dom'
import { ThemeToggle } from '../../components/theme/ThemeToggle'
import type { AnalyticsAttentionRecord } from '../../features/analytics/analyticsApi'
import {
  useDashboardContent,
  useDashboardControls,
  useDashboardWorkspace,
} from '../../features/dashboard/useDashboardFeatureContext'
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
  const { workspace, isLoading, isError, isSuccess } = useDashboardWorkspace()
  const controls = useDashboardControls()
  const content = useDashboardContent()
  const isDemo = workspace?.kind === 'demo'

  if (workspace === null) {
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

  return (
    <main className="dashboard-shell">
      <DashboardNav />

      <section className="dashboard-main">
        <header className="dashboard-header">
          <div>
            <p className="eyebrow">
              {isDemo ? 'Demo workspace' : 'Connected analytics'}
            </p>
            <h1>{workspace.organization.name}</h1>
            <p>
              {isDemo
                ? `Read-only synthetic data - ${workspace.organization.timezone} - Session ${workspace.sessionId.slice(0, 8)}`
                : `Imported GitHub data - ${workspace.organization.timezone}`}
            </p>
          </div>
          <div className="dashboard-header-actions">
            <ThemeToggle />
            {isSuccess && (
              <span className="freshness">{content.freshnessLabel}</span>
            )}
            <span className="demo-badge">
              {isDemo ? 'Demo read-only' : 'Private workspace'}
            </span>
          </div>
        </header>

        {isLoading ? (
          <DashboardLoadingSkeleton />
        ) : isError ? (
          <section className="retry-panel dashboard-error" role="alert">
            <strong>Dashboard analytics are unavailable.</strong>
            <span>Free-tier services may still be waking up. Retry in a moment.</span>
            <button type="button" onClick={controls.retry}>
              Retry Dashboard
            </button>
          </section>
        ) : (
          <>
            <DashboardFilters
              repositories={controls.repositories}
              initialFilters={controls.initialFilters}
              disabled={controls.filtersDisabled}
              onApply={controls.applyFilters}
              onClear={controls.clearFilters}
            />

            <section className="dashboard-meta" aria-label="Dashboard freshness">
              <span>
                {isSuccess ? content.repositoryCount : '...'} repositories selected
              </span>
              <span>{isSuccess ? content.freshnessLabel : 'Loading analytics...'}</span>
            </section>

            <section className="metrics-grid" aria-label="Dashboard metrics">
              {content.metrics.map((metric) => (
                <MetricCard key={metric.id} {...metric} />
              ))}
            </section>

            <section
              className="mt-[18px] grid gap-[18px] lg:grid-cols-2"
              aria-label="Pull request distributions"
            >
              <DistributionPanel
                title="Open PR age"
                description="Current open pull requests grouped by age."
                buckets={content.ageBuckets}
                getBucketUrl={content.getAgeBucketUrl}
              />
              <DistributionPanel
                title="PR size"
                description="Pull requests grouped by additions plus deletions."
                buckets={content.sizeBuckets}
                getBucketUrl={content.getSizeBucketUrl}
              />
            </section>

            <section className="dashboard-panels">
              <Suspense fallback={<ChartLoadingFallback />}>
                <WeeklyFlowChart
                  series={content.weeklySeries}
                  getPointUrl={content.getWeeklyPointUrl}
                />
              </Suspense>

              <article className="attention-panel">
                <h2>Attention list</h2>
                {isSuccess &&
                  content.attentionRecords.length === 0 && (
                    <p>No pull requests match the active attention rules.</p>
                  )}
                {content.attentionRecords.length > 0 && (
                  <ul>
                    {content.attentionRecords.map((record) => (
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

function formatAge(hours: number): string {
  if (hours < 24) {
    return `${hours}h`
  }

  const days = hours / 24
  return `${Number.isInteger(days) ? days : days.toFixed(1)}d`
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
