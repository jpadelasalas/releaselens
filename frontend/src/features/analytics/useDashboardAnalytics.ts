import { useQuery } from '@tanstack/react-query'
import {
  type AnalyticsFilters,
  getDashboardAnalytics,
} from './analyticsApi'

export function useDashboardAnalytics(
  organizationId: number | null,
  filters: AnalyticsFilters = {},
) {
  return useQuery({
    queryKey: ['dashboard-analytics', organizationId, filters],
    queryFn: () => {
      if (organizationId === null) {
        throw new Error('Organization id is required for dashboard analytics.')
      }

      return getDashboardAnalytics(organizationId, filters)
    },
    enabled: organizationId !== null,
  })
}
