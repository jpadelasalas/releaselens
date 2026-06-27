import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useCallback, useMemo, type PropsWithChildren } from 'react'

import { useScopeContext } from '../../app/scope/useScopeContext'
import {
  disconnectGitHubConnection,
  getGitHubConnectionError,
  startGitHubConnection,
} from './githubConnectionApi'
import { GitHubConnectionFeatureContext } from './githubConnectionContextInstance'
import { useGitHubConnection } from './useGitHubConnection'

export function GitHubConnectionFeatureProvider({ children }: PropsWithChildren) {
  const queryClient = useQueryClient()
  const { scope } = useScopeContext()
  const organizationId = scope.kind === 'connected' ? scope.organization.id : null
  const role = scope.kind === 'connected' ? scope.role : null
  const connectionQuery = useGitHubConnection(organizationId)
  const connectMutation = useMutation({
    mutationFn: async () => {
      if (organizationId === null) {
        throw new Error('An active organization is required.')
      }

      return startGitHubConnection(organizationId)
    },
    onSuccess: (url) => window.location.assign(url),
  })
  const disconnectMutation = useMutation({
    mutationFn: async () => {
      if (organizationId === null) {
        throw new Error('An active organization is required.')
      }

      return disconnectGitHubConnection(organizationId)
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ['github-connection', organizationId],
      })
    },
  })
  const connect = useCallback(async () => {
    await connectMutation.mutateAsync()
  }, [connectMutation])
  const disconnect = useCallback(async () => {
    await disconnectMutation.mutateAsync()
  }, [disconnectMutation])
  const refresh = useCallback(async () => {
    const result = await connectionQuery.refetch()

    if (result.error) {
      throw result.error
    }
  }, [connectionQuery])
  const clearError = useCallback(() => {
    connectMutation.reset()
    disconnectMutation.reset()
  }, [connectMutation, disconnectMutation])
  const error =
    connectMutation.error ?? disconnectMutation.error ?? connectionQuery.error
  const value = useMemo(
    () => ({
      connection: connectionQuery.data ?? null,
      canConnect: role === 'owner' || role === 'manager',
      canDisconnect: role === 'owner',
      isLoading: connectionQuery.isLoading,
      isRefreshing: connectionQuery.isFetching && !connectionQuery.isLoading,
      isSubmitting: connectMutation.isPending || disconnectMutation.isPending,
      error: error ? getGitHubConnectionError(error) : null,
      connect,
      disconnect,
      refresh,
      clearError,
    }),
    [
      clearError,
      connect,
      connectMutation.isPending,
      connectionQuery.data,
      connectionQuery.isLoading,
      connectionQuery.isFetching,
      disconnect,
      disconnectMutation.isPending,
      error,
      refresh,
      role,
    ],
  )

  return (
    <GitHubConnectionFeatureContext.Provider value={value}>
      {children}
    </GitHubConnectionFeatureContext.Provider>
  )
}
