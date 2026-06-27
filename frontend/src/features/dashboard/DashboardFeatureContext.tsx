import { type ReactNode, useCallback, useMemo } from 'react'
import { useScopeContext } from '../../app/scope/useScopeContext'
import { useDashboardAnalytics } from '../analytics/useDashboardAnalytics'
import { useDashboardFilters } from '../analytics/useDashboardFilters'
import {
  buildAgeBucketUrl,
  buildAttentionUrl,
  buildClosedWithoutMergeUrl,
  buildSizeBucketUrl,
  buildWaitingForReviewUrl,
  buildWeeklyPointUrl,
} from '../pull-requests/pullRequestExplorerUrl'
import { useOrganizationRepositories } from '../repositories/useOrganizationRepositories'
import {
  DashboardFeatureContext,
  type DashboardMetric,
} from './dashboardContextInstance'

type DashboardFeatureProviderProps = {
  children: ReactNode
}

export function DashboardFeatureProvider({
  children,
}: DashboardFeatureProviderProps) {
  const { scope } = useScopeContext()
  const workspace = scope.kind === 'demo' ? scope : null
  const organizationId = workspace?.organization.id ?? null
  const dashboardFilters = useDashboardFilters(
    workspace?.demo.anchor_date ?? null,
  )
  const dashboardQuery = useDashboardAnalytics(
    organizationId,
    dashboardFilters.filters,
  )
  const repositoriesQuery = useOrganizationRepositories(organizationId)
  const analytics = dashboardQuery.data
  const rawMetrics = analytics?.summary.metrics
  const largePrBucket = analytics?.distributions.buckets.pr_size.find(
    (bucket) => bucket.key === 'large',
  )

  const retry = useCallback(() => {
    void dashboardQuery.refetch()
  }, [dashboardQuery])
  const getAgeBucketUrl = useCallback(
    (bucket: { key: string }) =>
      buildAgeBucketUrl(
        dashboardFilters.filters,
        bucket.key as 'under_1_day' | '1_to_3_days' | '3_to_7_days' | 'over_7_days',
      ),
    [dashboardFilters.filters],
  )
  const getSizeBucketUrl = useCallback(
    (bucket: { key: string }) =>
      buildSizeBucketUrl(
        dashboardFilters.filters,
        bucket.key as 'xs' | 'small' | 'medium' | 'large',
      ),
    [dashboardFilters.filters],
  )
  const getWeeklyPointUrl = useCallback(
    (event: 'opened' | 'merged', week: string) =>
      buildWeeklyPointUrl(dashboardFilters.filters, event, week),
    [dashboardFilters.filters],
  )

  const metrics = useMemo<DashboardMetric[]>(
    () => [
      {
        id: 'waiting-for-review',
        label: 'Waiting for review',
        value: rawMetrics ? String(rawMetrics.waiting_for_first_review) : '...',
        detail: 'Open, non-draft PRs without a qualifying human review.',
        to: buildWaitingForReviewUrl(dashboardFilters.filters),
        actionLabel: 'View waiting pull requests',
      },
      {
        id: 'median-first-review',
        label: 'Median first review',
        value: rawMetrics
          ? formatHours(rawMetrics.median_first_review_hours)
          : '...',
        detail: rawMetrics
          ? `${rawMetrics.median_first_review_sample_size} qualifying reviewed PRs.`
          : 'Submitted human review excluding self, bot, pending, and dismissed reviews.',
      },
      {
        id: 'median-merge-time',
        label: 'Median merge time',
        value: rawMetrics ? formatHours(rawMetrics.median_merge_hours) : '...',
        detail: rawMetrics
          ? `${rawMetrics.median_merge_sample_size} merged PRs in the active filters.`
          : 'Merged pull requests in the seeded demo window.',
      },
      {
        id: 'large-prs',
        label: 'Large PRs',
        value: largePrBucket ? String(largePrBucket.count) : '...',
        detail: 'Pull requests above 500 changed lines.',
        to: buildSizeBucketUrl(dashboardFilters.filters, 'large'),
        actionLabel: 'View large pull requests',
      },
      {
        id: 'closed-without-merge',
        label: 'Closed without merge',
        value: rawMetrics ? String(rawMetrics.closed_without_merge) : '...',
        detail: 'Closed pull requests that were not merged.',
        to: buildClosedWithoutMergeUrl(dashboardFilters.filters),
        actionLabel: 'View closed pull requests',
      },
      {
        id: 'attention-count',
        label: 'Attention count',
        value: rawMetrics ? String(rawMetrics.attention_count) : '...',
        detail: 'Open pull requests matching explicit attention rules.',
        to: buildAttentionUrl(dashboardFilters.filters),
        actionLabel: 'View attention records',
      },
    ],
    [dashboardFilters.filters, largePrBucket, rawMetrics],
  )

  const value = useMemo(
    () => ({
      workspaceState: {
        workspace,
        isLoading: dashboardQuery.isLoading,
        isError: dashboardQuery.isError,
        isSuccess: dashboardQuery.isSuccess,
      },
      controls: {
        repositories: repositoriesQuery.data ?? [],
        initialFilters: dashboardFilters.defaults,
        filtersDisabled:
          repositoriesQuery.isLoading || dashboardQuery.isFetching,
        applyFilters: dashboardFilters.applyFilters,
        clearFilters: dashboardFilters.clearFilters,
        retry,
      },
      content: {
        analytics,
        freshnessLabel: analytics?.summary.demo_freshness_at
          ? `Fresh ${formatDateTime(analytics.summary.demo_freshness_at)}`
          : 'Freshness pending',
        repositoryCount: analytics?.summary.selected_repository_count ?? 0,
        metrics,
        ageBuckets: analytics?.distributions.buckets.open_pr_age ?? [],
        sizeBuckets: analytics?.distributions.buckets.pr_size ?? [],
        attentionRecords: analytics?.attention.records.slice(0, 5) ?? [],
        weeklySeries:
          analytics?.trends.series.opened_vs_merged_by_week ?? [],
        getAgeBucketUrl,
        getSizeBucketUrl,
        getWeeklyPointUrl,
      },
    }),
    [
      analytics,
      dashboardFilters.applyFilters,
      dashboardFilters.clearFilters,
      dashboardFilters.defaults,
      dashboardQuery.isError,
      dashboardQuery.isFetching,
      dashboardQuery.isLoading,
      dashboardQuery.isSuccess,
      getAgeBucketUrl,
      getSizeBucketUrl,
      getWeeklyPointUrl,
      metrics,
      repositoriesQuery.data,
      repositoriesQuery.isLoading,
      retry,
      workspace,
    ],
  )

  return (
    <DashboardFeatureContext.Provider value={value}>
      {children}
    </DashboardFeatureContext.Provider>
  )
}

function formatHours(hours: number | null): string {
  if (hours === null) {
    return 'N/A'
  }

  if (hours < 24) {
    return `${round(hours)}h`
  }

  return `${round(hours / 24)}d`
}

function formatDateTime(value: string): string {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function round(value: number): string {
  return Number.isInteger(value) ? String(value) : value.toFixed(1)
}
