import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  addChecklistItem,
  addReleasePullRequest,
  approveRelease,
  createRelease,
  getRelease,
  getReleases,
  removeChecklistItem,
  removeReleasePullRequest,
  transitionRelease,
  updateChecklistItem,
  updateRelease,
  type ReleaseState,
} from './releasesApi'

export function useReleases(organizationId: number | null, state?: string) {
  return useQuery({
    queryKey: ['releases', organizationId, state],
    queryFn: () => {
      if (organizationId === null) {
        throw new Error('Organization id is required to load releases.')
      }

      return getReleases(organizationId, state)
    },
    enabled: organizationId !== null,
  })
}

export function useRelease(organizationId: number | null, releaseId: number | null) {
  return useQuery({
    queryKey: ['release', organizationId, releaseId],
    queryFn: () => {
      if (organizationId === null || releaseId === null) {
        throw new Error('Organization id and release id are required.')
      }

      return getRelease(organizationId, releaseId)
    },
    enabled: organizationId !== null && releaseId !== null,
  })
}

export function useReleaseMutations(organizationId: number | null, releaseId?: number) {
  const queryClient = useQueryClient()

  function invalidate() {
    return Promise.all([
      queryClient.invalidateQueries({ queryKey: ['releases', organizationId] }),
      queryClient.invalidateQueries({ queryKey: ['release', organizationId, releaseId] }),
    ])
  }

  function requireOrganization(): number {
    if (organizationId === null) {
      throw new Error('An active organization is required.')
    }

    return organizationId
  }

  function requireRelease(): number {
    if (releaseId === undefined) {
      throw new Error('A release id is required.')
    }

    return releaseId
  }

  const create = useMutation({
    mutationFn: (data: { title: string; description?: string; target_release_at?: string }) =>
      createRelease(requireOrganization(), data),
    onSuccess: invalidate,
  })

  const update = useMutation({
    mutationFn: (data: { title?: string; description?: string; target_release_at?: string }) =>
      updateRelease(requireOrganization(), requireRelease(), data),
    onSuccess: invalidate,
  })

  const transition = useMutation({
    mutationFn: (to: ReleaseState) => transitionRelease(requireOrganization(), requireRelease(), to),
    onSuccess: invalidate,
  })

  const addPullRequest = useMutation({
    mutationFn: (pullRequestId: number) =>
      addReleasePullRequest(requireOrganization(), requireRelease(), pullRequestId),
    onSuccess: invalidate,
  })

  const removePullRequest = useMutation({
    mutationFn: (pullRequestId: number) =>
      removeReleasePullRequest(requireOrganization(), requireRelease(), pullRequestId),
    onSuccess: invalidate,
  })

  const addItem = useMutation({
    mutationFn: ({ label, isRequired }: { label: string; isRequired: boolean }) =>
      addChecklistItem(requireOrganization(), requireRelease(), label, isRequired),
    onSuccess: invalidate,
  })

  const toggleItem = useMutation({
    mutationFn: ({ itemId, completed }: { itemId: number; completed: boolean }) =>
      updateChecklistItem(requireOrganization(), requireRelease(), itemId, completed),
    onSuccess: invalidate,
  })

  const removeItem = useMutation({
    mutationFn: (itemId: number) => removeChecklistItem(requireOrganization(), requireRelease(), itemId),
    onSuccess: invalidate,
  })

  const approve = useMutation({
    mutationFn: () => approveRelease(requireOrganization(), requireRelease()),
    onSuccess: invalidate,
  })

  return {
    create,
    update,
    transition,
    addPullRequest,
    removePullRequest,
    addItem,
    toggleItem,
    removeItem,
    approve,
  }
}
