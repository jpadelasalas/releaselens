import { describe, expect, it } from 'vitest'

import {
  syncHealthSchema,
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

  it('parses a sync health summary', () => {
    const health = syncHealthSchema.parse({
      last_delivery_received_at: '2026-07-01T00:00:00Z',
      dead_letter_count: 1,
      failure_rate: 0.5,
      failure_rate_sample_size: 2,
      average_processing_lag_seconds: 3,
      last_successful_reconciliation_at: null,
      reconciliation_corrections: 0,
      repositories: [
        {
          repository_id: 1,
          full_name: 'acme/widgets',
          last_delivery_received_at: null,
          dead_letter_count: 0,
          status: 'unknown',
        },
      ],
    })

    expect(health.repositories[0]?.status).toBe('unknown')
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
