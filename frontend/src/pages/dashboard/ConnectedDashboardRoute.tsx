import { lazy, Suspense } from 'react'

import { DashboardFeatureProvider } from '../../features/dashboard/DashboardFeatureContext'

const DashboardPage = lazy(() =>
  import('./DemoDashboardPage').then((module) => ({
    default: module.DemoDashboardPage,
  })),
)

export function ConnectedDashboardRoute() {
  return (
    <DashboardFeatureProvider>
      <Suspense fallback={<DashboardFallback />}>
        <DashboardPage />
      </Suspense>
    </DashboardFeatureProvider>
  )
}

function DashboardFallback() {
  return (
    <main className="centered-page" aria-busy="true">
      <p className="eyebrow">Connected workspace</p>
      <h1>Loading dashboard...</h1>
    </main>
  )
}
