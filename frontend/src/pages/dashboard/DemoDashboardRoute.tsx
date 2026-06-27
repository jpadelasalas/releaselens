import { lazy, Suspense } from 'react'

const DemoDashboardPage = lazy(() =>
  import('./DemoDashboardPage').then((module) => ({
    default: module.DemoDashboardPage,
  })),
)

export function DemoDashboardRoute() {
  return (
    <Suspense fallback={<DashboardRouteFallback />}>
      <DemoDashboardPage />
    </Suspense>
  )
}

function DashboardRouteFallback() {
  return (
    <main className="centered-page" aria-busy="true">
      <p className="eyebrow">Demo workspace</p>
      <h1>Loading dashboard...</h1>
    </main>
  )
}
