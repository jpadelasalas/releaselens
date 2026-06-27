import { useQuery } from '@tanstack/react-query'

import { getGitHubConnection } from './githubConnectionApi'

export function useGitHubConnection(organizationId: number | null) {
  return useQuery({
    queryKey: ['github-connection', organizationId],
    queryFn: () => {
      if (organizationId === null) {
        throw new Error('Organization id is required to load the GitHub connection.')
      }

      return getGitHubConnection(organizationId)
    },
    enabled: organizationId !== null,
  })
}
