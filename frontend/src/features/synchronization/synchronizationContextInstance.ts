import { createContext } from 'react'

import type { SyncRun } from './synchronizationApi'

export type SynchronizationContextValue = {
  activeRepositoryId: number | null
  runs: SyncRun[]
  canSync: boolean
  isLoadingHistory: boolean
  isRequesting: boolean
  error: string | null
  requestSync: (repositoryId: number) => Promise<void>
  showHistory: (repositoryId: number) => void
  closeHistory: () => void
  refreshHistory: () => Promise<void>
  clearError: () => void
}

export const SynchronizationContext =
  createContext<SynchronizationContextValue | null>(null)
