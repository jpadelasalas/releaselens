import { lazy, Suspense } from 'react'
import { PullRequestExplorerProvider } from '../../features/pull-requests/PullRequestExplorerContext'

const DemoPullRequestExplorerPage = lazy(() =>
  import('./DemoPullRequestExplorerPage').then((module) => ({
    default: module.DemoPullRequestExplorerPage,
  })),
)

export function DemoPullRequestExplorerRoute() {
  return (
    <PullRequestExplorerProvider>
      <Suspense fallback={<ExplorerRouteFallback />}>
        <DemoPullRequestExplorerPage />
      </Suspense>
    </PullRequestExplorerProvider>
  )
}

function ExplorerRouteFallback() {
  return (
    <main className="centered-page" aria-busy="true">
      <p className="eyebrow">Demo workspace</p>
      <h1>Loading pull requests...</h1>
    </main>
  )
}
