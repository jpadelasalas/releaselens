import { useContext } from 'react'

import { PullRequestExplorerFeatureContext } from './pullRequestExplorerContextInstance'

function usePullRequestExplorerFeatureContext() {
  const context = useContext(PullRequestExplorerFeatureContext)

  if (!context) {
    throw new Error(
      'Pull request explorer hooks must be used within PullRequestExplorerProvider.',
    )
  }

  return context
}

export function usePullRequestExplorerWorkspace() {
  return usePullRequestExplorerFeatureContext().workspaceState
}

export function usePullRequestExplorerData() {
  return usePullRequestExplorerFeatureContext().dataState
}

export function usePullRequestExplorerActions() {
  return usePullRequestExplorerFeatureContext().actions
}
