import { createContext } from 'react'

import type { DemoScope } from '../../app/scope/scopeTypes'
import type {
  PullRequestExplorerFilters,
  PullRequestExplorerResponse,
} from './pullRequestApi'

export interface PullRequestExplorerWorkspaceState {
  workspace: DemoScope | null
  title: string
}

export interface PullRequestExplorerDataState {
  filters: PullRequestExplorerFilters
  response: PullRequestExplorerResponse | undefined
  isLoading: boolean
  isError: boolean
}

export interface PullRequestExplorerActions {
  retry: () => void
  setPage: (page: number) => void
}

export interface PullRequestExplorerFeatureContextValue {
  workspaceState: PullRequestExplorerWorkspaceState
  dataState: PullRequestExplorerDataState
  actions: PullRequestExplorerActions
}

export const PullRequestExplorerFeatureContext =
  createContext<PullRequestExplorerFeatureContextValue | null>(null)
