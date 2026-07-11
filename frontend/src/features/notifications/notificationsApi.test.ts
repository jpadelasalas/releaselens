import { describe, expect, it } from 'vitest'

import { notificationPreferenceSchema, notificationSchema } from './notificationsApi'

describe('notification schemas', () => {
  it('parses a notification', () => {
    const notification = notificationSchema.parse({
      id: 1,
      type: 'release.released',
      title: '"July release" was released',
      body: null,
      subject_type: 'release',
      subject_id: 5,
      read_at: null,
      created_at: '2026-07-01T00:00:00Z',
    })

    expect(notification.read_at).toBeNull()
  })

  it('parses a notification preference', () => {
    const preference = notificationPreferenceSchema.parse({
      type: 'deployment.failed',
      enabled: false,
    })

    expect(preference.enabled).toBe(false)
  })
})
