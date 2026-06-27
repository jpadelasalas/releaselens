import { keepPreviousData, useQuery } from '@tanstack/react-query'
import {
  type PullRequestExplorerFilters,
  getPullRequests,
} from './pullRequestApi'

export function usePullRequestExplorer(
  organizationId: number | null,
  filters: PullRequestExplorerFilters,
) {
  return useQuery({
    queryKey: ['pull-request-explorer', organizationId, filters],
    queryFn: () => {
      if (organizationId === null) {
        throw new Error('Organization id is required to load pull requests.')
      }

      return getPullRequests(organizationId, filters)
    },
    enabled: organizationId !== null,
    placeholderData: keepPreviousData,
  })
}
