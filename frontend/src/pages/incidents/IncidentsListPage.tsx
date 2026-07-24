import { useState } from 'react'
import { Link } from 'react-router-dom'

import { useScopeContext } from '../../app/scope/useScopeContext'
import { getIncidentError } from '../../features/incidents/incidentsApi'
import { useIncidentMutations, useIncidents } from '../../features/incidents/useIncidents'
import { DashboardNav } from '../dashboard/components/DashboardNav'

const stateFilters = ['all', 'investigating', 'identified', 'monitoring', 'resolved', 'closed'] as const

export function IncidentsListPage() {
  const { scope } = useScopeContext()
  const organizationId = scope.kind === 'connected' ? scope.organization.id : null
  const canManage = scope.kind === 'connected' && (scope.role === 'owner' || scope.role === 'manager')
  const [state, setState] = useState<(typeof stateFilters)[number]>('all')
  const [title, setTitle] = useState('')
  const incidentsQuery = useIncidents(organizationId, state === 'all' ? undefined : { state })
  const { create } = useIncidentMutations(organizationId)

  async function handleCreate() {
    if (title.trim() === '') return

    try {
      await create.mutateAsync({ title: title.trim(), severity: 'sev3' })
      setTitle('')
    } catch {
      // Mutation state exposes the controlled API error.
    }
  }

  return (
    <main className="dashboard-shell">
      <DashboardNav activeItem="Incidents" />

      <section className="dashboard-main min-w-0 p-6">
      <h1 className="text-xl text-[var(--color-heading)]">Incidents</h1>

      <div className="mt-4 flex flex-wrap gap-2">
        {stateFilters.map((option) => (
          <button
            key={option}
            type="button"
            className={`min-h-9 rounded-md border px-3 text-xs font-bold capitalize ${
              state === option
                ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-[var(--color-on-primary)]'
                : 'border-[var(--color-border-strong)] text-[var(--color-heading)]'
            }`}
            onClick={() => setState(option)}
          >
            {option}
          </button>
        ))}
      </div>

      {canManage && (
        <div className="mt-4 flex gap-2">
          <input
            className="min-h-11 flex-1 rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface-solid)] px-3 text-[var(--color-text)]"
            placeholder="New incident title (sev3)"
            value={title}
            onChange={(event) => setTitle(event.target.value)}
          />
          <button
            type="button"
            className="primary-action justify-center"
            disabled={title.trim() === '' || create.isPending}
            onClick={() => void handleCreate()}
          >
            {create.isPending ? 'Creating...' : 'Open incident'}
          </button>
        </div>
      )}

      {create.error && (
        <div className="mt-3 rounded-md border border-[var(--color-warning-border)] bg-[var(--color-warning-bg)] p-3 text-sm" role="alert">
          {getIncidentError(create.error)}
        </div>
      )}

      <div className="mt-5 divide-y divide-[var(--color-border)] rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]">
        {incidentsQuery.isLoading ? (
          <div className="h-14 animate-pulse bg-[var(--color-page-alt)]" />
        ) : (incidentsQuery.data ?? []).length === 0 ? (
          <p className="p-6 text-sm text-[var(--color-muted)]">No incidents.</p>
        ) : (
          (incidentsQuery.data ?? []).map((incident) => (
            <Link
              key={incident.id}
              to={`/app/incidents/${incident.id}`}
              className="flex items-center justify-between gap-3 px-4 py-3 hover:bg-[var(--color-page-alt)]"
            >
              <span>
                <strong className="text-sm text-[var(--color-heading)]">{incident.title}</strong>
                <span className="ml-2 rounded-full border border-[var(--color-border-strong)] px-2 py-0.5 text-xs font-bold uppercase text-[var(--color-muted)]">
                  {incident.severity}
                </span>
              </span>
              <span className="text-xs font-bold capitalize text-[var(--color-muted)]">{incident.state}</span>
            </Link>
          ))
        )}
      </div>
      </section>
    </main>
  )
}
