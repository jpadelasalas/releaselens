import FilterAltOutlinedIcon from '@mui/icons-material/FilterAltOutlined'
import RestartAltOutlinedIcon from '@mui/icons-material/RestartAltOutlined'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import type { AnalyticsFilters } from '../../../features/analytics/analyticsApi'
import type { OrganizationRepository } from '../../../features/repositories/repositoriesApi'

const dashboardFilterSchema = z
  .object({
    repository_id: z.string(),
    date_from: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
    date_to: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
  })
  .refine((values) => values.date_to >= values.date_from, {
    message: 'End date must be on or after the start date.',
    path: ['date_to'],
  })

type DashboardFilterValues = z.infer<typeof dashboardFilterSchema>

type DashboardFiltersProps = {
  repositories: OrganizationRepository[]
  initialFilters: AnalyticsFilters
  disabled?: boolean
  onApply: (filters: AnalyticsFilters) => void
  onClear: () => void
}

export function DashboardFilters({
  repositories,
  initialFilters,
  disabled = false,
  onApply,
  onClear,
}: DashboardFiltersProps) {
  const defaultValues = toFormValues(initialFilters)
  const {
    formState: { errors },
    handleSubmit,
    register,
    reset,
  } = useForm<DashboardFilterValues>({
    resolver: zodResolver(dashboardFilterSchema),
    defaultValues,
  })

  const apply = handleSubmit((values) => onApply(toAnalyticsFilters(values)))

  function clear() {
    reset(defaultValues)
    onClear()
  }

  return (
    <form
      className="mb-4 grid items-end gap-3 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4 md:grid-cols-[minmax(180px,1fr)_minmax(150px,0.65fr)_minmax(150px,0.65fr)_auto]"
      onSubmit={apply}
      aria-label="Dashboard filters"
    >
      <label className="grid gap-1 text-[13px] font-bold text-[var(--color-muted)]">
        Repository
        <select
          className="min-h-10 rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface-solid)] px-3 text-[var(--color-heading)]"
          disabled={disabled}
          {...register('repository_id')}
        >
          <option value="">All repositories</option>
          {repositories.map((repository) => (
            <option key={repository.id} value={repository.id}>
              {repository.name}
            </option>
          ))}
        </select>
      </label>

      <label className="grid gap-1 text-[13px] font-bold text-[var(--color-muted)]">
        From
        <input
          className="min-h-10 rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface-solid)] px-3 text-[var(--color-heading)]"
          type="date"
          disabled={disabled}
          {...register('date_from')}
        />
      </label>

      <label className="grid gap-1 text-[13px] font-bold text-[var(--color-muted)]">
        To
        <input
          className="min-h-10 rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface-solid)] px-3 text-[var(--color-heading)]"
          type="date"
          disabled={disabled}
          aria-invalid={errors.date_to ? 'true' : undefined}
          {...register('date_to')}
        />
        {errors.date_to && (
          <span className="text-xs text-red-700" role="alert">
            {errors.date_to.message}
          </span>
        )}
      </label>

      <div className="flex gap-2">
        <button
          className="inline-flex min-h-10 items-center gap-2 rounded-md bg-[var(--color-primary)] px-4 font-bold text-[var(--color-on-primary)] disabled:opacity-60"
          type="submit"
          disabled={disabled}
        >
          <FilterAltOutlinedIcon fontSize="small" />
          Apply
        </button>
        <button
          className="inline-grid min-h-10 min-w-10 place-items-center rounded-md border border-[var(--color-border-strong)] text-[var(--color-heading)] disabled:opacity-60"
          type="button"
          disabled={disabled}
          onClick={clear}
          aria-label="Clear dashboard filters"
          title="Clear filters"
        >
          <RestartAltOutlinedIcon fontSize="small" />
        </button>
      </div>
    </form>
  )
}

function toFormValues(filters: AnalyticsFilters): DashboardFilterValues {
  return {
    repository_id: filters.repository_ids?.[0]?.toString() ?? '',
    date_from: filters.date_from?.slice(0, 10) ?? '',
    date_to: filters.date_to?.slice(0, 10) ?? '',
  }
}

function toAnalyticsFilters(values: DashboardFilterValues): AnalyticsFilters {
  return {
    repository_ids: values.repository_id
      ? [Number(values.repository_id)]
      : [],
    date_from: `${values.date_from}T00:00:00Z`,
    date_to: `${values.date_to}T23:59:59Z`,
  }
}
