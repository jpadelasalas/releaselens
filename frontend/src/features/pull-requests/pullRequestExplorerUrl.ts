import type { AnalyticsFilters } from '../analytics/analyticsApi'
import type { PullRequestExplorerFilters } from './pullRequestApi'

export function buildWaitingForReviewUrl(filters: AnalyticsFilters): string {
  const searchParams = new URLSearchParams({
    review_status: 'waiting',
  })

  for (const repositoryId of filters.repository_ids ?? []) {
    searchParams.append('repository_ids', String(repositoryId))
  }

  if (filters.date_from) {
    searchParams.set('date_from', filters.date_from)
  }

  if (filters.date_to) {
    searchParams.set('date_to', filters.date_to)
  }

  return `/demo/pull-requests?${searchParams.toString()}`
}

export function parsePullRequestExplorerFilters(
  searchParams: URLSearchParams,
): PullRequestExplorerFilters {
  const repositoryIds = searchParams
    .getAll('repository_ids')
    .map(Number)
    .filter((id) => Number.isInteger(id) && id > 0)
  const page = Number(searchParams.get('page') ?? 1)

  return {
    repository_ids: repositoryIds,
    date_from: searchParams.get('date_from') ?? undefined,
    date_to: searchParams.get('date_to') ?? undefined,
    review_status:
      searchParams.get('review_status') === 'waiting' ? 'waiting' : undefined,
    page: Number.isInteger(page) && page > 0 ? page : 1,
    per_page: 25,
  }
}
