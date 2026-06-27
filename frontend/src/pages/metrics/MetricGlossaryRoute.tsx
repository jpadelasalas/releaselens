import { lazy, Suspense } from 'react'

const MetricGlossaryPage = lazy(() =>
  import('./MetricGlossaryPage').then((module) => ({
    default: module.MetricGlossaryPage,
  })),
)

export function MetricGlossaryRoute() {
  return (
    <Suspense fallback={<MetricGlossaryFallback />}>
      <MetricGlossaryPage />
    </Suspense>
  )
}

function MetricGlossaryFallback() {
  return (
    <main className="centered-page" aria-busy="true">
      <p className="eyebrow">Explainable analytics</p>
      <h1>Loading metric definitions...</h1>
    </main>
  )
}
