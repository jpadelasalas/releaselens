import { useCallback, useMemo, type PropsWithChildren } from 'react'
import { useSearchParams } from 'react-router-dom'

import { useScopeContext } from '../../app/scope/useScopeContext'
import { getExplorerTitle, parsePullRequestExplorerFilters } from './pullRequestExplorerUrl'
import { PullRequestExplorerFeatureContext } from './pullRequestExplorerContextInstance'
import { usePullRequestExplorer } from './usePullRequestExplorer'

export function PullRequestExplorerProvider({ children }: PropsWithChildren) {
  const { scope } = useScopeContext()
  const workspace = scope.kind === 'demo' || scope.kind === 'connected' ? scope : null
  const [searchParams, setSearchParams] = useSearchParams()
  const filters = useMemo(
    () => parsePullRequestExplorerFilters(searchParams),
    [searchParams],
  )
  const organizationId = workspace?.organization.id ?? null
  const { data: response, isLoading, isError, refetch } =
    usePullRequestExplorer(organizationId, filters)

  const retry = useCallback(() => {
    void refetch()
  }, [refetch])

  const setPage = useCallback(
    (page: number) => {
      const nextSearchParams = new URLSearchParams(searchParams)

      if (page <= 1) {
        nextSearchParams.delete('page')
      } else {
        nextSearchParams.set('page', String(page))
      }

      setSearchParams(nextSearchParams)
    },
    [searchParams, setSearchParams],
  )

  const value = useMemo(
    () => ({
      workspaceState: {
        workspace,
        title: getExplorerTitle(filters),
      },
      dataState: {
        filters,
        response,
        isLoading,
        isError,
      },
      actions: {
        retry,
        setPage,
      },
    }),
    [filters, isError, isLoading, response, retry, setPage, workspace],
  )

  return (
    <PullRequestExplorerFeatureContext.Provider value={value}>
      {children}
    </PullRequestExplorerFeatureContext.Provider>
  )
}
