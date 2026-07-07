import { describe, expect, it } from 'vitest'

import {
  webhookDeliveryDetailSchema,
  webhookDeliverySchema,
} from './eventHealthApi'

describe('event health schemas', () => {
  it('parses a webhook delivery record', () => {
    const delivery = webhookDeliverySchema.parse({
      id: 1,
      github_delivery_id: 'delivery-1',
      event_name: 'pull_request',
      action_name: 'opened',
      status: 'processed',
      repository_id: 2,
      error_category: null,
      error_summary: null,
      received_at: '2026-07-01T00:00:00Z',
      queued_at: '2026-07-01T00:00:01Z',
      processed_at: '2026-07-01T00:00:02Z',
    })

    expect(delivery.status).toBe('processed')
    expect(delivery.repository_id).toBe(2)
  })

  it('parses a webhook delivery detail record including attempts', () => {
    const detail = webhookDeliveryDetailSchema.parse({
      id: 1,
      github_delivery_id: 'delivery-1',
      event_name: 'pull_request',
      action_name: 'opened',
      status: 'dead_lettered',
      repository_id: null,
      error_category: 'validation',
      error_summary: 'bad payload',
      received_at: '2026-07-01T00:00:00Z',
      queued_at: null,
      processed_at: null,
      attempts: [
        {
          attempt_number: 1,
          status: 'failed',
          started_at: '2026-07-01T00:00:00Z',
          completed_at: '2026-07-01T00:00:01Z',
          next_retry_at: null,
          error_category: 'validation',
          error_summary: 'bad payload',
        },
      ],
    })

    expect(detail.attempts).toHaveLength(1)
    expect(detail.attempts[0]?.status).toBe('failed')
  })

  it('rejects an unknown status value', () => {
    expect(() =>
      webhookDeliverySchema.parse({
        id: 1,
        github_delivery_id: 'delivery-1',
        event_name: 'pull_request',
        action_name: null,
        status: 'not_a_real_status',
        repository_id: null,
        error_category: null,
        error_summary: null,
        received_at: '2026-07-01T00:00:00Z',
        queued_at: null,
        processed_at: null,
      }),
    ).toThrow()
  })
})
