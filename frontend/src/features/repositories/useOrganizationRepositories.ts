import { useQuery } from '@tanstack/react-query'
import { getOrganizationRepositories } from './repositoriesApi'

export function useOrganizationRepositories(organizationId: number | null) {
  return useQuery({
    queryKey: ['organization-repositories', organizationId],
    queryFn: () => {
      if (organizationId === null) {
        throw new Error('Organization id is required to load repositories.')
      }

      return getOrganizationRepositories(organizationId)
    },
    enabled: organizationId !== null,
    staleTime: 5 * 60_000,
  })
}
