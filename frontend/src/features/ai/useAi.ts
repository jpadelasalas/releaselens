import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { generateReleaseNotes, getAiGenerations } from './aiApi'

export function useAiGenerations(organizationId: number | null, releaseId: number | null) {
  return useQuery({
    queryKey: ['ai-generations', organizationId, releaseId],
    queryFn: () => {
      if (organizationId === null || releaseId === null) {
        throw new Error('Organization id and release id are required.')
      }

      return getAiGenerations(organizationId, releaseId)
    },
    enabled: organizationId !== null && releaseId !== null,
  })
}

export function useGenerateReleaseNotes(organizationId: number | null, releaseId: number | null) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => {
      if (organizationId === null || releaseId === null) {
        throw new Error('Organization id and release id are required.')
      }

      return generateReleaseNotes(organizationId, releaseId)
    },
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: ['ai-generations', organizationId, releaseId] }),
  })
}
