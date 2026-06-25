import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useScopeContext } from '../../app/scope/useScopeContext'
import { BrandLink } from '../../components/navigation/BrandLink'
import { ThemeToggle } from '../../components/theme/ThemeToggle'
import { createDemoSession } from '../../features/demo-session/demoSessionApi'

type DemoLaunchState = 'idle' | 'loading' | 'unavailable'

export function LandingPage() {
  const navigate = useNavigate()
  const { activateDemoSession } = useScopeContext()
  const [demoState, setDemoState] = useState<DemoLaunchState>('idle')

  async function launchDemo() {
    setDemoState('loading')

    try {
      const demoSession = await createDemoSession()

      activateDemoSession(demoSession)
      navigate('/demo/dashboard')
    } catch {
      setDemoState('unavailable')
    }
  }

  return (
    <main className="landing-shell">
      <nav className="topbar" aria-label="Primary navigation">
        <BrandLink />
        <div className="topbar-actions">
          <a href="#responsible-use">Responsible Use</a>
          <ThemeToggle />
          <Link className="secondary-link" to="/sign-in">
            Sign In
          </Link>
        </div>
      </nav>

      <section className="hero-section">
        <div className="hero-copy">
          <p className="eyebrow">Portfolio MVP - Synthetic GitHub analytics</p>
          <h1>See where pull-request review flow is slowing down.</h1>
          <p className="hero-lede">
            ReleaseLens helps engineering leads inspect review delays, stale pull
            requests, and repository-level flow signals without turning team data
            into employee rankings.
          </p>

          <div className="hero-actions">
            <button
              type="button"
              className="primary-action"
              onClick={launchDemo}
              disabled={demoState === 'loading'}
              aria-describedby="demo-status"
            >
              {demoState === 'loading' ? 'Opening Demo...' : 'View Demo Workspace'}
            </button>
            <Link className="secondary-action" to="/sign-in">
              Sign In
            </Link>
          </div>

          <div id="demo-status" className="demo-status" aria-live="polite">
            {demoState === 'loading' && (
              <span>Creating a read-only demo session and loading the dashboard.</span>
            )}
            {demoState === 'unavailable' && (
              <div className="retry-panel" role="alert">
                <strong>Demo service is unavailable.</strong>
                <span>Free-tier services may be waking up. Retry in a moment.</span>
                <button type="button" onClick={launchDemo}>
                  Retry Demo
                </button>
              </div>
            )}
          </div>

          <p className="cold-start">
            Cold-start notice: the hosted demo may take a short moment to wake on
            free infrastructure.
          </p>
        </div>

        <DashboardPreview />
      </section>

      <section className="value-grid" aria-label="ReleaseLens value">
        <article>
          <span className="metric-icon">01</span>
          <h2>Find delayed reviews</h2>
          <p>
            Identify open pull requests waiting for a first qualifying human review
            and drill into the supporting records.
          </p>
        </article>
        <article>
          <span className="metric-icon">02</span>
          <h2>Explain every metric</h2>
          <p>
            Metric cards carry definitions, freshness, sample size, exclusions,
            and links to the exact pull requests behind the number.
          </p>
        </article>
        <article>
          <span className="metric-icon">03</span>
          <h2>Keep tenant data scoped</h2>
          <p>
            Demo access is read-only and server-scoped to a synthetic organization,
            separate from connected GitHub workspaces.
          </p>
        </article>
      </section>

      <section id="responsible-use" className="trust-section">
        <div>
          <h2>Responsible-use statement</h2>
          <p>
            ReleaseLens is designed to help teams inspect workflow conditions, not
            rank people. Pull-request metrics are influenced by complexity, team
            structure, time zones, leave, and incident work, so they should be used
            to improve systems rather than judge individuals.
          </p>
        </div>
        <div>
          <h2>Independent project disclaimer</h2>
          <p>
            ReleaseLens is an independent portfolio product based on public GitHub
            documentation and synthetic data. The demo contains no employer,
            client, private repository, credential, or confidential material.
          </p>
        </div>
      </section>
    </main>
  )
}

function DashboardPreview() {
  return (
    <div className="preview-frame" aria-label="ReleaseLens dashboard preview">
      <div className="preview-window-bar">
        <span />
        <span />
        <span />
      </div>
      <div className="preview-app">
        <aside className="preview-sidebar">
          <strong>ReleaseLens</strong>
          <span className="active-pill">Dashboard</span>
          <span>Pull requests</span>
          <span>Repositories</span>
          <span>Sync runs</span>
        </aside>
        <div className="preview-content">
          <div className="preview-heading">
            <div>
              <strong>Northstar Engineering</strong>
              <span>Synthetic demo data</span>
            </div>
            <span className="freshness">Fresh 15m ago</span>
          </div>
          <div className="preview-cards">
            <div>
              <span>Waiting for review</span>
              <strong>18</strong>
            </div>
            <div>
              <span>Median first review</span>
              <strong>9.4h</strong>
            </div>
            <div>
              <span>Large PRs</span>
              <strong>27</strong>
            </div>
          </div>
          <div className="preview-chart" aria-hidden="true">
            {[45, 62, 38, 75, 58, 82, 64, 91].map((height, index) => (
              <span key={index} style={{ height: `${height}%` }} />
            ))}
          </div>
        </div>
      </div>
    </div>
  )
}
