import { lazy, Suspense } from 'react'
import { DashboardFeatureProvider } from '../../features/dashboard/DashboardFeatureContext'

const DemoDashboardPage = lazy(() =>
  import('./DemoDashboardPage').then((module) => ({
    default: module.DemoDashboardPage,
  })),
)

export function DemoDashboardRoute() {
  return (
    <DashboardFeatureProvider>
      <Suspense fallback={<DashboardRouteFallback />}>
        <DemoDashboardPage />
      </Suspense>
    </DashboardFeatureProvider>
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
