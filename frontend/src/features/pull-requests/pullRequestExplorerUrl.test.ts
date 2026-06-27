import { describe, expect, it } from 'vitest'
import {
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
})
