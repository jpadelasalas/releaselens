import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  getNotificationPreferences,
  getNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  updateNotificationPreference,
} from './notificationsApi'

export function useNotifications(organizationId: number | null, unreadOnly = false) {
  return useQuery({
    queryKey: ['notifications', organizationId, unreadOnly],
    queryFn: () => {
      if (organizationId === null) {
        throw new Error('Organization id is required to load notifications.')
      }

      return getNotifications(organizationId, unreadOnly)
    },
    enabled: organizationId !== null,
  })
}

export function useNotificationPreferences(organizationId: number | null) {
  return useQuery({
    queryKey: ['notification-preferences', organizationId],
    queryFn: () => {
      if (organizationId === null) {
        throw new Error('Organization id is required to load notification preferences.')
      }

      return getNotificationPreferences(organizationId)
    },
    enabled: organizationId !== null,
  })
}

export function useNotificationMutations(organizationId: number | null) {
  const queryClient = useQueryClient()

  function invalidate() {
    return queryClient.invalidateQueries({ queryKey: ['notifications', organizationId] })
  }

  function requireOrganization(): number {
    if (organizationId === null) {
      throw new Error('An active organization is required.')
    }

    return organizationId
  }

  const markRead = useMutation({
    mutationFn: (notificationId: number) => markNotificationRead(requireOrganization(), notificationId),
    onSuccess: invalidate,
  })

  const markAllRead = useMutation({
    mutationFn: () => markAllNotificationsRead(requireOrganization()),
    onSuccess: invalidate,
  })

  const updatePreference = useMutation({
    mutationFn: ({ type, enabled }: { type: string; enabled: boolean }) =>
      updateNotificationPreference(requireOrganization(), type, enabled),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: ['notification-preferences', organizationId] }),
  })

  return { markRead, markAllRead, updatePreference }
}
