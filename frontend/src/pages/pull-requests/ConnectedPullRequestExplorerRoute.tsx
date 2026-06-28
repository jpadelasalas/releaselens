import { lazy, Suspense } from 'react'

import { PullRequestExplorerProvider } from '../../features/pull-requests/PullRequestExplorerContext'

const PullRequestExplorerPage = lazy(() =>
  import('./DemoPullRequestExplorerPage').then((module) => ({
    default: module.DemoPullRequestExplorerPage,
  })),
)

export function ConnectedPullRequestExplorerRoute() {
  return (
    <PullRequestExplorerProvider>
      <Suspense fallback={<ExplorerFallback />}>
        <PullRequestExplorerPage />
      </Suspense>
    </PullRequestExplorerProvider>
  )
}

function ExplorerFallback() {
  return (
    <main className="centered-page" aria-busy="true">
      <p className="eyebrow">Connected workspace</p>
      <h1>Loading pull requests...</h1>
    </main>
  )
}
