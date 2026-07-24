import { useState } from 'react'

import { useScopeContext } from '../../app/scope/useScopeContext'
import { useDeployments } from '../../features/deployments/useDeployments'
import { DashboardNav } from '../dashboard/components/DashboardNav'

const statusFilters = ['all', 'pending', 'queued', 'in_progress', 'success', 'failure', 'error', 'inactive'] as const

export function DeploymentsListPage() {
  const { scope } = useScopeContext()
  const organizationId = scope.kind === 'connected' ? scope.organization.id : null
  const [status, setStatus] = useState<(typeof statusFilters)[number]>('all')
  const deploymentsQuery = useDeployments(
    organizationId,
    status === 'all' ? undefined : { status },
  )

  return (
    <main className="dashboard-shell">
      <DashboardNav activeItem="Deployments" />

      <section className="dashboard-main min-w-0 p-6">
      <h1 className="text-xl text-[var(--color-heading)]">Deployments</h1>

      <div className="mt-4 flex flex-wrap gap-2">
        {statusFilters.map((option) => (
          <button
            key={option}
            type="button"
            className={`min-h-9 rounded-md border px-3 text-xs font-bold capitalize ${
              status === option
                ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-[var(--color-on-primary)]'
                : 'border-[var(--color-border-strong)] text-[var(--color-heading)]'
            }`}
            onClick={() => setStatus(option)}
          >
            {option.replaceAll('_', ' ')}
          </button>
        ))}
      </div>

      <div className="mt-5 divide-y divide-[var(--color-border)] rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]">
        {deploymentsQuery.isLoading ? (
          <div className="h-14 animate-pulse bg-[var(--color-page-alt)]" />
        ) : (deploymentsQuery.data ?? []).length === 0 ? (
          <p className="p-6 text-sm text-[var(--color-muted)]">No deployments yet.</p>
        ) : (
          (deploymentsQuery.data ?? []).map((deployment) => (
            <div key={deployment.id} className="flex items-center justify-between gap-3 px-4 py-3">
              <div>
                <strong className="text-sm text-[var(--color-heading)]">
                  {deployment.repository_name}
                </strong>
                <span className="ml-2 text-xs capitalize text-[var(--color-muted)]">
                  {deployment.normalized_environment}
                  {deployment.is_production ? ' - production' : ''}
                </span>
              </div>
              <span className="text-xs font-bold capitalize text-[var(--color-muted)]">
                {deployment.status.replaceAll('_', ' ')}
              </span>
            </div>
          ))
        )}
      </div>
      </section>
    </main>
  )
}
