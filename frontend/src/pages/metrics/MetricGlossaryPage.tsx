import ArrowBackOutlinedIcon from '@mui/icons-material/ArrowBackOutlined'
import { Link } from 'react-router-dom'

import { useScopeContext } from '../../app/scope/useScopeContext'
import { ThemeToggle } from '../../components/theme/ThemeToggle'
import {
  metricDefinitions,
  type MetricDefinition,
} from '../../features/metrics/metricDefinitions'
import { useMetricHashNavigation } from '../../features/metrics/useMetricHashNavigation'
import { DashboardNav } from '../dashboard/components/DashboardNav'

export function MetricGlossaryPage() {
  const { scope } = useScopeContext()
  useMetricHashNavigation()

  if (scope.kind !== 'demo' && scope.kind !== 'connected') {
    return (
      <main className="centered-page">
        <p className="eyebrow">Demo session required</p>
        <h1>Open the demo workspace first.</h1>
        <p>The glossary accompanies the synthetic demo analytics.</p>
        <Link className="primary-action" to="/">
          Go to Landing Page
        </Link>
      </main>
    )
  }

  const dashboardPath = scope.kind === 'connected'
    ? '/app/dashboard'
    : '/demo/dashboard'

  return (
    <main className="dashboard-shell">
      <DashboardNav activeItem="Metric Glossary" />

      <section className="dashboard-main min-w-0">
        <header className="dashboard-header">
          <div>
            <Link
              className="mb-2 inline-flex items-center gap-1 text-sm font-bold text-[var(--color-primary)] no-underline"
              to={dashboardPath}
            >
              <ArrowBackOutlinedIcon fontSize="small" />
              Dashboard
            </Link>
            <p className="eyebrow">Explainable analytics</p>
            <h1>Metric glossary</h1>
            <p>How every dashboard signal is calculated, filtered, and interpreted.</p>
          </div>
          <div className="dashboard-header-actions">
            <ThemeToggle />
            <span className="demo-badge">Definitions for V1</span>
          </div>
        </header>

        <section className="mb-6 border-l-4 border-[var(--color-primary)] bg-[var(--color-primary-soft)] p-4">
          <h2 className="text-lg text-[var(--color-heading)]">Read metrics in context</h2>
          <p className="mt-1 max-w-4xl text-[var(--color-muted)]">
            Repository filters apply everywhere. Date filters use the event named by
            each metric: creation, merge, or close. Metrics describe team workflow,
            not individual productivity, and should never be used to rank developers.
          </p>
        </section>

        <div className="grid items-start gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
          <nav
            className="grid gap-1 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-3 lg:sticky lg:top-4"
            aria-label="Metric definitions"
          >
            <strong className="px-2 pb-2 text-sm text-[var(--color-heading)]">
              On this page
            </strong>
            {metricDefinitions.map((definition) => (
              <a
                key={definition.id}
                className="rounded-md px-2 py-2 text-sm font-semibold text-[var(--color-muted)] no-underline hover:bg-[var(--color-primary-soft)] hover:text-[var(--color-primary-strong)]"
                href={`#${definition.id}`}
              >
                {definition.name}
              </a>
            ))}
          </nav>

          <section className="grid min-w-0 gap-4" aria-label="Glossary entries">
            {metricDefinitions.map((definition) => (
              <MetricDefinitionEntry key={definition.id} definition={definition} />
            ))}
          </section>
        </div>
      </section>
    </main>
  )
}

function MetricDefinitionEntry({
  definition,
}: {
  definition: MetricDefinition
}) {
  return (
    <article
      id={definition.id}
      className="scroll-mt-4 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-5 outline-none md:p-6"
      tabIndex={-1}
    >
      <header className="border-b border-[var(--color-border)] pb-4">
        <span className="text-xs font-extrabold uppercase text-[var(--color-primary)]">
          {definition.category}
        </span>
        <h2 className="mt-1 text-xl text-[var(--color-heading)]">{definition.name}</h2>
        <p className="mt-1 text-[var(--color-muted)]">{definition.summary}</p>
      </header>

      <dl className="mt-5 grid gap-x-6 gap-y-4 md:grid-cols-2">
        <DefinitionField label="Formula" value={definition.formula} />
        <DefinitionField label="Cohort" value={definition.cohort} />
        <DefinitionField label="Date basis" value={definition.dateBasis} />
        <DefinitionField label="Sample behavior" value={definition.sampleSize} />
        <DefinitionList
          label="Exclusions"
          values={definition.exclusions.length > 0 ? definition.exclusions : ['None']}
        />
        <DefinitionList label="Limitations" values={definition.limitations} />
      </dl>

      <div className="mt-5 rounded-md bg-[var(--color-primary-soft)] p-4">
        <strong className="text-sm text-[var(--color-heading)]">Interpretation</strong>
        <p className="mt-1 text-sm text-[var(--color-muted)]">
          {definition.interpretation}
        </p>
      </div>
    </article>
  )
}

function DefinitionField({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-xs font-extrabold uppercase text-[var(--color-subtle)]">
        {label}
      </dt>
      <dd className="m-0 mt-1 text-sm text-[var(--color-text)]">{value}</dd>
    </div>
  )
}

function DefinitionList({ label, values }: { label: string; values: string[] }) {
  return (
    <div>
      <dt className="text-xs font-extrabold uppercase text-[var(--color-subtle)]">
        {label}
      </dt>
      <dd className="m-0 mt-1">
        <ul className="m-0 grid gap-1 pl-5 text-sm text-[var(--color-text)]">
          {values.map((value) => (
            <li key={value}>{value}</li>
          ))}
        </ul>
      </dd>
    </div>
  )
}
