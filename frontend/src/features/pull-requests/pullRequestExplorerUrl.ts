import type { AnalyticsFilters } from '../analytics/analyticsApi'
import type { PullRequestExplorerFilters } from './pullRequestApi'

type AgeBucket = NonNullable<PullRequestExplorerFilters['age_bucket']>
type SizeBucket = NonNullable<PullRequestExplorerFilters['size_bucket']>
type WeeklyEvent = NonNullable<PullRequestExplorerFilters['event']>

export function buildWaitingForReviewUrl(filters: AnalyticsFilters, basePath?: string): string {
  return buildExplorerUrl(filters, { review_status: 'waiting' }, basePath)
}

export function buildAttentionUrl(filters: AnalyticsFilters, basePath?: string): string {
  return buildExplorerUrl(filters, { attention: true }, basePath)
}

export function buildClosedWithoutMergeUrl(filters: AnalyticsFilters, basePath?: string): string {
  return buildExplorerUrl(filters, { state: 'closed_without_merge' }, basePath)
}

export function buildAgeBucketUrl(
  filters: AnalyticsFilters,
  ageBucket: AgeBucket,
  basePath?: string,
): string {
  return buildExplorerUrl(filters, { age_bucket: ageBucket }, basePath)
}

export function buildSizeBucketUrl(
  filters: AnalyticsFilters,
  sizeBucket: SizeBucket,
  basePath?: string,
): string {
  return buildExplorerUrl(filters, { size_bucket: sizeBucket }, basePath)
}

export function buildWeeklyPointUrl(
  filters: AnalyticsFilters,
  event: WeeklyEvent,
  week: string,
  basePath?: string,
): string {
  return buildExplorerUrl(filters, { event, week }, basePath)
}

function buildExplorerUrl(
  filters: AnalyticsFilters,
  drillDown: PullRequestExplorerFilters,
  basePath = '/demo/pull-requests',
): string {
  const searchParams = new URLSearchParams()

  for (const repositoryId of filters.repository_ids ?? []) {
    searchParams.append('repository_ids', String(repositoryId))
  }

  if (filters.date_from) {
    searchParams.set('date_from', filters.date_from)
  }

  if (filters.date_to) {
    searchParams.set('date_to', filters.date_to)
  }

  for (const [key, value] of Object.entries(drillDown)) {
    if (value !== undefined) {
      searchParams.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
    }
  }

  return `${basePath}?${searchParams.toString()}`
}

export function parsePullRequestExplorerFilters(
  searchParams: URLSearchParams,
): PullRequestExplorerFilters {
  const repositoryIds = searchParams
    .getAll('repository_ids')
    .map(Number)
    .filter((id) => Number.isInteger(id) && id > 0)
  const page = Number(searchParams.get('page') ?? 1)
  const ageBucket = parseEnum(searchParams.get('age_bucket'), [
    'under_1_day',
    '1_to_3_days',
    '3_to_7_days',
    'over_7_days',
  ] as const)
  const sizeBucket = parseEnum(searchParams.get('size_bucket'), [
    'xs',
    'small',
    'medium',
    'large',
  ] as const)
  const event = parseEnum(searchParams.get('event'), [
    'opened',
    'merged',
  ] as const)

  return {
    repository_ids: repositoryIds,
    date_from: searchParams.get('date_from') ?? undefined,
    date_to: searchParams.get('date_to') ?? undefined,
    review_status:
      searchParams.get('review_status') === 'waiting' ? 'waiting' : undefined,
    attention: searchParams.get('attention') === '1' || undefined,
    state:
      searchParams.get('state') === 'closed_without_merge'
        ? 'closed_without_merge'
        : undefined,
    age_bucket: ageBucket,
    size_bucket: sizeBucket,
    event,
    week: event ? searchParams.get('week') ?? undefined : undefined,
    page: Number.isInteger(page) && page > 0 ? page : 1,
    per_page: 25,
  }
}

export function getExplorerTitle(filters: PullRequestExplorerFilters): string {
  if (filters.review_status === 'waiting') {
    return 'Pull requests waiting for review'
  }

  if (filters.attention) {
    return 'Pull requests requiring attention'
  }

  if (filters.state === 'closed_without_merge') {
    return 'Pull requests closed without merge'
  }

  if (filters.age_bucket) {
    return `Open pull requests aged ${formatFilter(filters.age_bucket)}`
  }

  if (filters.size_bucket) {
    return `${formatFilter(filters.size_bucket)} pull requests`
  }

  if (filters.event && filters.week) {
    return `${formatFilter(filters.event)} pull requests for ${filters.week}`
  }

  return 'Pull requests'
}

function parseEnum<const T extends readonly string[]>(
  value: string | null,
  allowed: T,
): T[number] | undefined {
  return value !== null && (allowed as readonly string[]).includes(value)
    ? value
    : undefined
}

function formatFilter(value: string): string {
  return value.replaceAll('_', ' ')
}
