import { createContext } from 'react'
import type { DemoScope } from '../../app/scope/scopeTypes'
import type {
  AnalyticsAttentionRecord,
  AnalyticsBucket,
  AnalyticsFilters,
  DashboardAnalytics,
} from '../analytics/analyticsApi'
import type { OrganizationRepository } from '../repositories/repositoriesApi'

export type DashboardMetric = {
  id: string
  label: string
  value: string
  detail: string
  to?: string
  actionLabel?: string
  definitionTo: string
}

export type DashboardWorkspaceState = {
  workspace: DemoScope | null
  isLoading: boolean
  isError: boolean
  isSuccess: boolean
}

export type DashboardControlsState = {
  repositories: OrganizationRepository[]
  initialFilters: AnalyticsFilters
  filtersDisabled: boolean
  applyFilters: (filters: AnalyticsFilters) => void
  clearFilters: () => void
  retry: () => void
}

export type DashboardContentState = {
  analytics: DashboardAnalytics | undefined
  freshnessLabel: string
  repositoryCount: number
  metrics: DashboardMetric[]
  ageBuckets: AnalyticsBucket[]
  sizeBuckets: AnalyticsBucket[]
  attentionRecords: AnalyticsAttentionRecord[]
  weeklySeries: DashboardAnalytics['trends']['series']['opened_vs_merged_by_week']
  getAgeBucketUrl: (bucket: AnalyticsBucket) => string
  getSizeBucketUrl: (bucket: AnalyticsBucket) => string
  getWeeklyPointUrl: (event: 'opened' | 'merged', week: string) => string
}

export type DashboardFeatureContextValue = {
  workspaceState: DashboardWorkspaceState
  controls: DashboardControlsState
  content: DashboardContentState
}

export const DashboardFeatureContext =
  createContext<DashboardFeatureContextValue | null>(null)
