import { describe, expect, it } from 'vitest'
import {
  buildAgeBucketUrl,
  buildWaitingForReviewUrl,
  parsePullRequestExplorerFilters,
} from './pullRequestExplorerUrl'

describe('pull request explorer URL filters', () => {
  it('round-trips dashboard filters into explorer filters', () => {
    const url = buildWaitingForReviewUrl({
      repository_ids: [7],
      date_from: '2026-05-21T00:00:00Z',
      date_to: '2026-06-19T23:59:59Z',
    })
    const searchParams = new URL(url, 'https://releaselens.test').searchParams

    expect(parsePullRequestExplorerFilters(searchParams)).toEqual({
      repository_ids: [7],
      date_from: '2026-05-21T00:00:00Z',
      date_to: '2026-06-19T23:59:59Z',
      review_status: 'waiting',
      page: 1,
      per_page: 25,
    })
  })

  it('builds age bucket drill-down URLs', () => {
    const url = buildAgeBucketUrl({}, 'over_7_days')

    expect(url).toContain('age_bucket=over_7_days')
  })

  it('targets the connected explorer when a private route is provided', () => {
    const url = buildWaitingForReviewUrl({}, '/app/pull-requests')

    expect(url).toBe('/app/pull-requests?review_status=waiting')
  })
})
