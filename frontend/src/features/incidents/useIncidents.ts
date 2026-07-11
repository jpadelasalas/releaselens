import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  addActionItem,
  createIncident,
  getIncident,
  getIncidents,
  publishPostmortem,
  savePostmortem,
  transitionIncident,
  updateActionItem,
  type IncidentState,
} from './incidentsApi'

export function useIncidents(organizationId: number | null, filters?: { state?: string; severity?: string }) {
  return useQuery({
    queryKey: ['incidents', organizationId, filters],
    queryFn: () => {
      if (organizationId === null) {
        throw new Error('Organization id is required to load incidents.')
      }

      return getIncidents(organizationId, filters)
    },
    enabled: organizationId !== null,
  })
}

export function useIncident(organizationId: number | null, incidentId: number | null) {
  return useQuery({
    queryKey: ['incident', organizationId, incidentId],
    queryFn: () => {
      if (organizationId === null || incidentId === null) {
        throw new Error('Organization id and incident id are required.')
      }

      return getIncident(organizationId, incidentId)
    },
    enabled: organizationId !== null && incidentId !== null,
  })
}

export function useIncidentMutations(organizationId: number | null, incidentId?: number) {
  const queryClient = useQueryClient()

  function invalidate() {
    return Promise.all([
      queryClient.invalidateQueries({ queryKey: ['incidents', organizationId] }),
      queryClient.invalidateQueries({ queryKey: ['incident', organizationId, incidentId] }),
    ])
  }

  function requireOrganization(): number {
    if (organizationId === null) {
      throw new Error('An active organization is required.')
    }

    return organizationId
  }

  function requireIncident(): number {
    if (incidentId === undefined) {
      throw new Error('An incident id is required.')
    }

    return incidentId
  }

  const create = useMutation({
    mutationFn: (data: Parameters<typeof createIncident>[1]) => createIncident(requireOrganization(), data),
    onSuccess: invalidate,
  })

  const transition = useMutation({
    mutationFn: (to: IncidentState) => transitionIncident(requireOrganization(), requireIncident(), to),
    onSuccess: invalidate,
  })

  const addItem = useMutation({
    mutationFn: (description: string) => addActionItem(requireOrganization(), requireIncident(), description),
    onSuccess: invalidate,
  })

  const toggleItem = useMutation({
    mutationFn: ({ itemId, completed }: { itemId: number; completed: boolean }) =>
      updateActionItem(requireOrganization(), requireIncident(), itemId, completed),
    onSuccess: invalidate,
  })

  const saveDraft = useMutation({
    mutationFn: (data: Parameters<typeof savePostmortem>[2]) => savePostmortem(requireOrganization(), requireIncident(), data),
    onSuccess: invalidate,
  })

  const publish = useMutation({
    mutationFn: () => publishPostmortem(requireOrganization(), requireIncident()),
    onSuccess: invalidate,
  })

  return { create, transition, addItem, toggleItem, saveDraft, publish }
}
