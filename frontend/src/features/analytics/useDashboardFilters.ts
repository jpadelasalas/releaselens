import { useCallback, useMemo, useState } from 'react'
import type { AnalyticsFilters } from './analyticsApi'

export function useDashboardFilters(anchorDate: string | null) {
  const defaults = useMemo(() => createDefaultFilters(anchorDate), [anchorDate])
  const [filters, setFilters] = useState<AnalyticsFilters>(defaults)
  const clearFilters = useCallback(() => setFilters(defaults), [defaults])

  return useMemo(
    () => ({
      filters,
      defaults,
      applyFilters: setFilters,
      clearFilters,
    }),
    [clearFilters, defaults, filters],
  )
}

function createDefaultFilters(anchorDate: string | null): AnalyticsFilters {
  if (anchorDate === null) {
    return {}
  }

  const dateTo = new Date(anchorDate)
  const dateFrom = new Date(anchorDate)
  dateFrom.setUTCDate(dateFrom.getUTCDate() - 29)

  return {
    repository_ids: [],
    date_from: startOfUtcDay(dateFrom),
    date_to: endOfUtcDay(dateTo),
  }
}

function startOfUtcDay(value: Date): string {
  return `${value.toISOString().slice(0, 10)}T00:00:00Z`
}

function endOfUtcDay(value: Date): string {
  return `${value.toISOString().slice(0, 10)}T23:59:59Z`
}
