import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  getDeployment,
  getDeployments,
  linkDeploymentRelease,
} from './deploymentsApi'

export function useDeployments(
  organizationId: number | null,
  filters?: { status?: string; environment?: string },
) {
  return useQuery({
    queryKey: ['deployments', organizationId, filters],
    queryFn: () => {
      if (organizationId === null) {
        throw new Error('Organization id is required to load deployments.')
      }

      return getDeployments(organizationId, filters)
    },
    enabled: organizationId !== null,
  })
}

export function useDeployment(organizationId: number | null, deploymentId: number | null) {
  return useQuery({
    queryKey: ['deployment', organizationId, deploymentId],
    queryFn: () => {
      if (organizationId === null || deploymentId === null) {
        throw new Error('Organization id and deployment id are required.')
      }

      return getDeployment(organizationId, deploymentId)
    },
    enabled: organizationId !== null && deploymentId !== null,
  })
}

export function useLinkDeploymentRelease(organizationId: number | null) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ deploymentId, releaseId }: { deploymentId: number; releaseId: number | null }) => {
      if (organizationId === null) {
        throw new Error('An active organization is required.')
      }

      return linkDeploymentRelease(organizationId, deploymentId, releaseId)
    },
    onSuccess: () =>
      Promise.all([
        queryClient.invalidateQueries({ queryKey: ['deployments', organizationId] }),
        queryClient.invalidateQueries({ queryKey: ['deployment', organizationId] }),
      ]),
  })
}
