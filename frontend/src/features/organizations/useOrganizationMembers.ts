import { useQuery } from '@tanstack/react-query'

import { getOrganizationMembers } from './organizationApi'

export function useOrganizationMembers(
  organizationId: number | null,
  enabled: boolean,
) {
  return useQuery({
    queryKey: ['organization-members', organizationId],
    queryFn: () => {
      if (organizationId === null) {
        throw new Error('Organization id is required to load members.')
      }

      return getOrganizationMembers(organizationId)
    },
    enabled: organizationId !== null && enabled,
  })
}
