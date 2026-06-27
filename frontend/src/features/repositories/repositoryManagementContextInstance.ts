import { createContext } from 'react'

import type {
  AvailableGitHubRepository,
  OrganizationRepository,
} from './repositoriesApi'

export type RepositoryManagementContextValue = {
  repositories: OrganizationRepository[]
  availableRepositories: AvailableGitHubRepository[]
  filteredRepositories: AvailableGitHubRepository[]
  selectedRepositoryIds: number[]
  search: string
  canManage: boolean
  hasActiveConnection: boolean
  isLoading: boolean
  isSaving: boolean
  error: string | null
  setSearch: (value: string) => void
  toggleSelection: (repositoryId: number) => void
  saveSelection: () => Promise<void>
  changeMonitoring: (repositoryId: number, enabled: boolean) => Promise<void>
  refreshAvailable: () => Promise<void>
  clearError: () => void
}

export const RepositoryManagementContext =
  createContext<RepositoryManagementContextValue | null>(null)
