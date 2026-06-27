import { createContext } from 'react'

import type { GitHubConnection } from './githubConnectionSchemas'

export type GitHubConnectionFeatureContextValue = {
  connection: GitHubConnection | null
  canConnect: boolean
  canDisconnect: boolean
  isLoading: boolean
  isRefreshing: boolean
  isSubmitting: boolean
  error: string | null
  connect: () => Promise<void>
  disconnect: () => Promise<void>
  refresh: () => Promise<void>
  clearError: () => void
}

export const GitHubConnectionFeatureContext =
  createContext<GitHubConnectionFeatureContextValue | null>(null)
