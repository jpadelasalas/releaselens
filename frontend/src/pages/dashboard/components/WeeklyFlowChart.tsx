import { BarChart } from '@mui/x-charts/BarChart'

export type WeeklyFlowPoint = {
  week: string
  opened: number
  merged: number
}

type WeeklyFlowChartProps = {
  series: WeeklyFlowPoint[]
  isLoading?: boolean
}

export function WeeklyFlowChart({
  series,
  isLoading = false,
}: WeeklyFlowChartProps) {
  const visibleSeries = series.slice(-12).map((point) => ({
    ...point,
    weekLabel: formatWeek(point.week),
  }))

  return (
    <article className="flow-panel">
      <div>
        <h2>Opened versus merged</h2>
        <p>Weekly pull-request volume from the active filters.</p>
      </div>

      {isLoading && (
        <div
          className="mt-5 h-[300px] animate-pulse rounded-md bg-[var(--color-primary-soft)]"
          aria-label="Loading opened versus merged chart"
        />
      )}

      {!isLoading && visibleSeries.length === 0 && (
        <div className="mt-5 grid min-h-[300px] place-items-center rounded-md border border-dashed border-[var(--color-border-strong)] text-[var(--color-muted)]">
          No weekly activity matches the active filters.
        </div>
      )}

      {!isLoading && visibleSeries.length > 0 && (
        <div className="mt-5 min-w-0" aria-label="Opened versus merged by week">
          <BarChart
            dataset={visibleSeries}
            xAxis={[
              {
                dataKey: 'weekLabel',
                scaleType: 'band',
                categoryGapRatio: 0.28,
                barGapRatio: 0.12,
              },
            ]}
            yAxis={[{ min: 0, width: 42 }]}
            series={[
              {
                dataKey: 'opened',
                label: 'Opened',
                color: 'var(--color-chart-start)',
              },
              {
                dataKey: 'merged',
                label: 'Merged',
                color: 'var(--color-primary)',
              },
            ]}
            height={300}
            margin={{ top: 20, right: 16, bottom: 12, left: 0 }}
            grid={{ horizontal: true }}
            sx={{
              '& .MuiChartsAxis-line, & .MuiChartsAxis-tick': {
                stroke: 'var(--color-border-strong)',
              },
              '& .MuiChartsAxis-tickLabel': {
                fill: 'var(--color-muted)',
              },
              '& .MuiChartsGrid-line': {
                stroke: 'var(--color-border)',
              },
            }}
          />
        </div>
      )}
    </article>
  )
}

function formatWeek(value: string): string {
  return new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    timeZone: 'UTC',
  }).format(new Date(`${value}T00:00:00Z`))
}
