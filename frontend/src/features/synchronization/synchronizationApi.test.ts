import { describe, expect, it } from 'vitest'

import { syncRunSchema } from './synchronizationApi'

describe('syncRunSchema', () => {
  it('parses queued and completed run diagnostics', () => {
    const run = syncRunSchema.parse({
      id: 1,
      repository_id: 2,
      trigger_type: 'manual',
      status: 'success',
      started_at: '2026-06-28T01:00:00Z',
      completed_at: '2026-06-28T01:01:00Z',
      created_count: 3,
      updated_count: 2,
      unchanged_count: 0,
      failed_count: 0,
      rate_limit_remaining: 4990,
      rate_limit_reset_at: '2026-06-28T02:00:00Z',
      error_category: null,
      error_summary: null,
    })

    expect(run.status).toBe('success')
    expect(run.created_count).toBe(3)
  })
})
