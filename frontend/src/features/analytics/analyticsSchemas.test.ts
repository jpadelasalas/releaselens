import { describe, expect, it } from 'vitest'
import { analyticsSummaryResponseSchema } from './analyticsSchemas'

const validSummaryResponse = {
  data: {
    applied_filters: {
      repository_ids: [],
      date_from: null,
      date_to: null,
    },
    selected_repository_count: 4,
    demo_freshness_at: '2026-06-19T12:00:00Z',
    metrics: {
      median_first_review_hours: 9.5,
      median_first_review_sample_size: 20,
      median_merge_hours: 34,
      median_merge_sample_size: 18,
      waiting_for_first_review: 6,
      closed_without_merge: 3,
      attention_count: 8,
    },
  },
}

describe('analyticsSummaryResponseSchema', () => {
  it('accepts a valid analytics response', () => {
    const result = analyticsSummaryResponseSchema.parse(validSummaryResponse)

    expect(result.data.metrics.waiting_for_first_review).toBe(6)
  })

  it('rejects invalid metric types', () => {
    const invalidResponse = structuredClone(validSummaryResponse)
    invalidResponse.data.metrics.waiting_for_first_review = 'six' as never

    expect(() => analyticsSummaryResponseSchema.parse(invalidResponse)).toThrow()
  })
})
