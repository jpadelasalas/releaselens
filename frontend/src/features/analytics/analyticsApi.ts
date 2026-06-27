import api from '../../lib/api'
import {
  type AnalyticsAttention,
  type AnalyticsDistributions,
  type AnalyticsFilters,
  type AnalyticsSummary,
  type AnalyticsTrends,
  analyticsAttentionResponseSchema,
  analyticsDistributionsResponseSchema,
  analyticsSummaryResponseSchema,
  analyticsTrendsResponseSchema,
} from './analyticsSchemas'

export type {
  AnalyticsAttention,
  AnalyticsAttentionRecord,
  AnalyticsBucket,
  AnalyticsDistributions,
  AnalyticsFilters,
  AnalyticsSummary,
  AnalyticsTrends,
} from './analyticsSchemas'

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
    api.get<unknown>(`${basePath}/summary`, query),
    api.get<unknown>(`${basePath}/trends`, query),
    api.get<unknown>(`${basePath}/distributions`, query),
    api.get<unknown>(`${basePath}/attention`, query),
  ])

  return {
    summary: analyticsSummaryResponseSchema.parse(summary.data).data,
    trends: analyticsTrendsResponseSchema.parse(trends.data).data,
    distributions: analyticsDistributionsResponseSchema.parse(distributions.data).data,
    attention: analyticsAttentionResponseSchema.parse(attention.data).data,
  }
}
