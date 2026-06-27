import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useCallback, useMemo, useState, type PropsWithChildren } from 'react'

import { useScopeContext } from '../../app/scope/useScopeContext'
import {
  getRepositorySyncRuns,
  getSynchronizationError,
  requestRepositorySync,
} from './synchronizationApi'
import { SynchronizationContext } from './synchronizationContextInstance'

export function SynchronizationProvider({ children }: PropsWithChildren) {
  const queryClient = useQueryClient()
  const { scope } = useScopeContext()
  const organizationId = scope.kind === 'connected' ? scope.organization.id : null
  const role = scope.kind === 'connected' ? scope.role : null
  const [activeRepositoryId, setActiveRepositoryId] = useState<number | null>(null)
  const historyQuery = useQuery({
    queryKey: ['repository-sync-runs', organizationId, activeRepositoryId],
    queryFn: () => {
      if (organizationId === null || activeRepositoryId === null) {
        throw new Error('An active repository is required.')
      }

      return getRepositorySyncRuns(organizationId, activeRepositoryId)
    },
    enabled: organizationId !== null && activeRepositoryId !== null,
    refetchInterval: (query) => {
      const runs = query.state.data

      return runs?.some((run) => run.status === 'queued' || run.status === 'running')
        ? 2_000
        : false
    },
  })
  const syncMutation = useMutation({
    mutationFn: async (repositoryId: number) => {
      if (organizationId === null) {
        throw new Error('An active organization is required.')
      }

      return requestRepositorySync(organizationId, repositoryId)
    },
    onSuccess: async (run) => {
      setActiveRepositoryId(run.repository_id)
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: ['repository-sync-runs', organizationId, run.repository_id],
        }),
        queryClient.invalidateQueries({
          queryKey: ['organization-repositories', organizationId],
        }),
      ])
    },
  })
  const requestSync = useCallback(
    async (repositoryId: number) => {
      await syncMutation.mutateAsync(repositoryId)
    },
    [syncMutation],
  )
  const showHistory = useCallback((repositoryId: number) => {
    setActiveRepositoryId(repositoryId)
  }, [])
  const closeHistory = useCallback(() => setActiveRepositoryId(null), [])
  const refreshHistory = useCallback(async () => {
    const result = await historyQuery.refetch()

    if (result.error) {
      throw result.error
    }
  }, [historyQuery])
  const clearError = useCallback(() => syncMutation.reset(), [syncMutation])
  const error = syncMutation.error ?? historyQuery.error
  const value = useMemo(
    () => ({
      activeRepositoryId,
      runs: historyQuery.data ?? [],
      canSync: role === 'owner' || role === 'manager',
      isLoadingHistory: historyQuery.isLoading,
      isRequesting: syncMutation.isPending,
      error: error ? getSynchronizationError(error) : null,
      requestSync,
      showHistory,
      closeHistory,
      refreshHistory,
      clearError,
    }),
    [
      activeRepositoryId,
      clearError,
      closeHistory,
      error,
      historyQuery.data,
      historyQuery.isLoading,
      refreshHistory,
      requestSync,
      role,
      showHistory,
      syncMutation.isPending,
    ],
  )

  return (
    <SynchronizationContext.Provider value={value}>
      {children}
    </SynchronizationContext.Provider>
  )
}
