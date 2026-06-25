import { Link } from 'react-router-dom'
import { useScopeContext } from '../../app/scope/useScopeContext'
import { ThemeToggle } from '../../components/theme/ThemeToggle'

export function DemoDashboardPage() {
  const { scope } = useScopeContext()

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

  return (
    <main className="dashboard-shell">
      <aside className="dashboard-nav">
        <Link className="brand" to="/">
          <span className="brand-mark">R</span>
          <span>ReleaseLens</span>
        </Link>
        <span className="nav-current">Dashboard</span>
        <span>Pull Requests</span>
        <span>Repositories</span>
        <span>Sync Runs</span>
        <span>Metric Glossary</span>
      </aside>

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
            <span className="demo-badge">Demo read-only</span>
          </div>
        </header>

        <section className="metrics-grid" aria-label="Dashboard metrics">
          <MetricCard
            label="Waiting for review"
            value="18"
            detail="Open, non-draft PRs without a qualifying human review."
          />
          <MetricCard
            label="Median first review"
            value="9.4h"
            detail="Submitted human review excluding self, bot, pending, and dismissed reviews."
          />
          <MetricCard
            label="Median merge time"
            value="34h"
            detail="Merged pull requests in the seeded 16-week demo window."
          />
          <MetricCard
            label="Large PRs"
            value="27"
            detail="Pull requests above the configured change-size threshold."
          />
        </section>

        <section className="dashboard-panels">
          <article className="flow-panel">
            <div>
              <h2>Review flow trend</h2>
              <p>Weekly pull-request volume and delayed-review pressure.</p>
            </div>
            <div className="large-chart" aria-label="Synthetic review flow chart">
              {[42, 55, 48, 66, 74, 61, 83, 72, 88, 79, 93, 86].map(
                (height, index) => (
                  <span key={index} style={{ height: `${height}%` }} />
                ),
              )}
            </div>
          </article>

          <article className="attention-panel">
            <h2>Attention list</h2>
            <ul>
              <li>
                <strong>customer-portal #1005</strong>
                <span>Waiting 8 days for first review</span>
              </li>
              <li>
                <strong>billing-api #1010</strong>
                <span>Bot-only review does not qualify</span>
              </li>
              <li>
                <strong>mobile-shell #1009</strong>
                <span>Self-review excluded from first-review metric</span>
              </li>
            </ul>
          </article>
        </section>
      </section>
    </main>
  )
}

function MetricCard({
  label,
  value,
  detail,
}: {
  label: string
  value: string
  detail: string
}) {
  return (
    <article className="metric-card">
      <span>{label}</span>
      <strong>{value}</strong>
      <p>{detail}</p>
    </article>
  )
}
