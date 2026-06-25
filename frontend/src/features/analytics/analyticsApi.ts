import api from '../../lib/api'

type ApiResponse<T> = {
  data: T
}

export type AnalyticsFilters = {
  repository_ids?: number[]
  date_from?: string
  date_to?: string
}

export type AnalyticsSummary = {
  applied_filters: AnalyticsFilters
  selected_repository_count: number
  demo_freshness_at: string | null
  metrics: {
    median_first_review_hours: number | null
    median_first_review_sample_size: number
    median_merge_hours: number | null
    median_merge_sample_size: number
    waiting_for_first_review: number
    closed_without_merge: number
    attention_count: number
  }
}

export type AnalyticsTrends = {
  applied_filters: AnalyticsFilters
  selected_repository_count: number
  demo_freshness_at: string | null
  series: {
    opened_vs_merged_by_week: Array<{
      week: string
      opened: number
      merged: number
    }>
  }
}

export type AnalyticsDistributions = {
  applied_filters: AnalyticsFilters
  selected_repository_count: number
  demo_freshness_at: string | null
  buckets: {
    open_pr_age: AnalyticsBucket[]
    pr_size: AnalyticsBucket[]
  }
}

export type AnalyticsBucket = {
  key: string
  label: string
  count: number
}

export type AnalyticsAttention = {
  applied_filters: AnalyticsFilters
  selected_repository_count: number
  demo_freshness_at: string | null
  records: AnalyticsAttentionRecord[]
}

export type AnalyticsAttentionRecord = {
  pull_request_id: number
  repository: string
  number: number
  title: string
  author: string | null
  age_hours: number
  change_size: number
  reasons: string[]
}

export type DashboardAnalytics = {
  summary: AnalyticsSummary
  trends: AnalyticsTrends
  distributions: AnalyticsDistributions
  attention: AnalyticsAttention
}

export async function getDashboardAnalytics(
  organizationId: number,
  filters: AnalyticsFilters = {},
): Promise<DashboardAnalytics> {
  const query = { params: filters }
  const basePath = `/api/v1/organizations/${organizationId}/analytics`

  const [summary, trends, distributions, attention] = await Promise.all([
    api.get<ApiResponse<AnalyticsSummary>>(`${basePath}/summary`, query),
    api.get<ApiResponse<AnalyticsTrends>>(`${basePath}/trends`, query),
    api.get<ApiResponse<AnalyticsDistributions>>(`${basePath}/distributions`, query),
    api.get<ApiResponse<AnalyticsAttention>>(`${basePath}/attention`, query),
  ])

  return {
    summary: summary.data.data,
    trends: trends.data.data,
    distributions: distributions.data.data,
    attention: attention.data.data,
  }
}
