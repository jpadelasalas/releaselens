import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  useCallback,
  useMemo,
  useState,
  type PropsWithChildren,
} from 'react'

import { useScopeContext } from '../../app/scope/useScopeContext'
import { useGitHubConnectionFeatureContext } from '../github-connection/useGitHubConnectionFeatureContext'
import {
  getRepositoryError,
  importOrganizationRepositories,
  updateRepositoryMonitoring,
} from './repositoriesApi'
import { RepositoryManagementContext } from './repositoryManagementContextInstance'
import { useAvailableGitHubRepositories } from './useAvailableGitHubRepositories'
import { useOrganizationRepositories } from './useOrganizationRepositories'

export function RepositoryManagementProvider({ children }: PropsWithChildren) {
  const queryClient = useQueryClient()
  const { scope } = useScopeContext()
  const { connection } = useGitHubConnectionFeatureContext()
  const organizationId = scope.kind === 'connected' ? scope.organization.id : null
  const role = scope.kind === 'connected' ? scope.role : null
  const canManage = role === 'owner' || role === 'manager'
  const hasActiveConnection = connection?.status === 'active'
  const [search, setSearch] = useState('')
  const [selectionOverride, setSelectionOverride] = useState<number[] | null>(
    null,
  )
  const repositoriesQuery = useOrganizationRepositories(organizationId)
  const availableQuery = useAvailableGitHubRepositories(
    organizationId,
    canManage && hasActiveConnection,
  )
  const monitoredRepositoryIds = useMemo(
    () =>
      (availableQuery.data ?? [])
        .filter((repository) => repository.is_monitored)
        .map((repository) => repository.github_repository_id),
    [availableQuery.data],
  )
  const selectedRepositoryIds =
    selectionOverride ?? monitoredRepositoryIds
  const importMutation = useMutation({
    mutationFn: async () => {
      if (organizationId === null) {
        throw new Error('An active organization is required.')
      }

      return importOrganizationRepositories(
        organizationId,
        selectedRepositoryIds,
      )
    },
    onSuccess: async () => {
      await invalidateRepositoryQueries()
      setSelectionOverride(null)
    },
  })
  const monitoringMutation = useMutation({
    mutationFn: async ({
      repositoryId,
      enabled,
    }: {
      repositoryId: number
      enabled: boolean
    }) => {
      if (organizationId === null) {
        throw new Error('An active organization is required.')
      }

      return updateRepositoryMonitoring(
        organizationId,
        repositoryId,
        enabled,
      )
    },
    onSuccess: async () => {
      await invalidateRepositoryQueries()
      setSelectionOverride(null)
    },
  })

  function invalidateRepositoryQueries() {
    return Promise.all([
      queryClient.invalidateQueries({
        queryKey: ['organization-repositories', organizationId],
      }),
      queryClient.invalidateQueries({
        queryKey: ['available-github-repositories', organizationId],
      }),
    ])
  }

  const filteredRepositories = useMemo(() => {
    const normalizedSearch = search.trim().toLocaleLowerCase()

    if (normalizedSearch === '') {
      return availableQuery.data ?? []
    }

    return (availableQuery.data ?? []).filter((repository) =>
      repository.full_name.toLocaleLowerCase().includes(normalizedSearch),
    )
  }, [availableQuery.data, search])
  const toggleSelection = useCallback((repositoryId: number) => {
    setSelectionOverride((current) => {
      const selection = current ?? monitoredRepositoryIds

      return selection.includes(repositoryId)
        ? selection.filter((id) => id !== repositoryId)
        : [...selection, repositoryId]
    })
  }, [monitoredRepositoryIds])
  const saveSelection = useCallback(async () => {
    await importMutation.mutateAsync()
  }, [importMutation])
  const changeMonitoring = useCallback(
    async (repositoryId: number, enabled: boolean) => {
      await monitoringMutation.mutateAsync({ repositoryId, enabled })
    },
    [monitoringMutation],
  )
  const refreshAvailable = useCallback(async () => {
    const result = await availableQuery.refetch()

    if (result.error) {
      throw result.error
    }
  }, [availableQuery])
  const clearError = useCallback(() => {
    importMutation.reset()
    monitoringMutation.reset()
  }, [importMutation, monitoringMutation])
  const error =
    importMutation.error ??
    monitoringMutation.error ??
    repositoriesQuery.error ??
    availableQuery.error
  const value = useMemo(
    () => ({
      repositories: repositoriesQuery.data ?? [],
      availableRepositories: availableQuery.data ?? [],
      filteredRepositories,
      selectedRepositoryIds,
      search,
      canManage,
      hasActiveConnection,
      isLoading:
        repositoriesQuery.isLoading ||
        (canManage && hasActiveConnection && availableQuery.isLoading),
      isSaving: importMutation.isPending || monitoringMutation.isPending,
      error: error ? getRepositoryError(error) : null,
      setSearch,
      toggleSelection,
      saveSelection,
      changeMonitoring,
      refreshAvailable,
      clearError,
    }),
    [
      availableQuery.data,
      availableQuery.isLoading,
      canManage,
      changeMonitoring,
      clearError,
      error,
      filteredRepositories,
      hasActiveConnection,
      importMutation.isPending,
      monitoringMutation.isPending,
      refreshAvailable,
      repositoriesQuery.data,
      repositoriesQuery.isLoading,
      saveSelection,
      search,
      selectedRepositoryIds,
      toggleSelection,
    ],
  )

  return (
    <RepositoryManagementContext.Provider value={value}>
      {children}
    </RepositoryManagementContext.Provider>
  )
}
