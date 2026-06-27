import { useQuery } from '@tanstack/react-query'

import { getAvailableGitHubRepositories } from './repositoriesApi'

export function useAvailableGitHubRepositories(
  organizationId: number | null,
  enabled: boolean,
) {
  return useQuery({
    queryKey: ['available-github-repositories', organizationId],
    queryFn: () => {
      if (organizationId === null) {
        throw new Error('Organization id is required to discover repositories.')
      }

      return getAvailableGitHubRepositories(organizationId)
    },
    enabled: organizationId !== null && enabled,
    staleTime: 30_000,
  })
}
