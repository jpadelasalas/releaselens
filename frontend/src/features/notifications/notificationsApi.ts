import { z } from 'zod'
import axios from 'axios'

import api from '../../lib/api'
import { prepareCsrfCookie } from '../../lib/csrf'

export const notificationTypes = [
  'release.approval_required',
  'release.released',
  'deployment.failed',
  'deployment.rolled_back',
] as const

export const notificationSchema = z.object({
  id: z.number().int().positive(),
  type: z.string(),
  title: z.string(),
  body: z.string().nullable(),
  subject_type: z.string().nullable(),
  subject_id: z.number().int().positive().nullable(),
  read_at: z.string().nullable(),
  created_at: z.string(),
})
export type Notification = z.infer<typeof notificationSchema>

export const notificationPreferenceSchema = z.object({
  type: z.string(),
  enabled: z.boolean(),
})
export type NotificationPreference = z.infer<typeof notificationPreferenceSchema>

const notificationsResponseSchema = z.object({
  data: z.array(notificationSchema),
  meta: z.object({ unread_count: z.number().int() }),
})
const preferencesResponseSchema = z.object({ data: z.array(notificationPreferenceSchema) })
const preferenceResponseSchema = z.object({ data: notificationPreferenceSchema })

export async function getNotifications(
  organizationId: number,
  unreadOnly = false,
): Promise<{ notifications: Notification[]; unreadCount: number }> {
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/notifications`,
    { params: unreadOnly ? { unread_only: true } : undefined },
  )
  const parsed = notificationsResponseSchema.parse(response.data)

  return { notifications: parsed.data, unreadCount: parsed.meta.unread_count }
}

export async function markNotificationRead(
  organizationId: number,
  notificationId: number,
): Promise<void> {
  await prepareCsrfCookie()
  await api.post(`/api/v1/organizations/${organizationId}/notifications/${notificationId}/read`)
}

export async function markAllNotificationsRead(organizationId: number): Promise<void> {
  await prepareCsrfCookie()
  await api.post(`/api/v1/organizations/${organizationId}/notifications/read-all`)
}

export async function getNotificationPreferences(
  organizationId: number,
): Promise<NotificationPreference[]> {
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/notification-preferences`,
  )

  return preferencesResponseSchema.parse(response.data).data
}

export async function updateNotificationPreference(
  organizationId: number,
  type: string,
  enabled: boolean,
): Promise<NotificationPreference> {
  await prepareCsrfCookie()
  const response = await api.put<unknown>(
    `/api/v1/organizations/${organizationId}/notification-preferences`,
    { type, enabled },
  )

  return preferenceResponseSchema.parse(response.data).data
}

export function getNotificationError(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const message = error.response?.data?.error?.message

    if (typeof message === 'string') {
      return message
    }
  }

  return 'Notifications are unavailable. Please try again.'
}
