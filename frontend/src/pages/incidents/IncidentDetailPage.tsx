import { useState } from 'react'
import { useParams } from 'react-router-dom'

import { useScopeContext } from '../../app/scope/useScopeContext'
import {
  getIncidentError,
  type IncidentState,
} from '../../features/incidents/incidentsApi'
import { useIncident, useIncidentMutations } from '../../features/incidents/useIncidents'

const allowedTransitions: Record<IncidentState, IncidentState[]> = {
  investigating: ['identified'],
  identified: ['monitoring', 'investigating'],
  monitoring: ['resolved', 'identified'],
  resolved: ['closed', 'monitoring'],
  closed: [],
}

export function IncidentDetailPage() {
  const { incidentId } = useParams<{ incidentId: string }>()
  const id = incidentId ? Number(incidentId) : null
  const { scope } = useScopeContext()
  const organizationId = scope.kind === 'connected' ? scope.organization.id : null
  const canManage = scope.kind === 'connected' && (scope.role === 'owner' || scope.role === 'manager')
  const incidentQuery = useIncident(organizationId, id)
  const mutations = useIncidentMutations(organizationId, id ?? undefined)
  const [actionItemDescription, setActionItemDescription] = useState('')
  const [summary, setSummary] = useState('')
  const [rootCause, setRootCause] = useState('')
  const [impact, setImpact] = useState('')

  const error = mutations.transition.error ?? mutations.addItem.error ?? mutations.saveDraft.error ?? mutations.publish.error

  if (incidentQuery.isLoading || !incidentQuery.data) {
    return <main className="p-6">Loading incident...</main>
  }

  const incident = incidentQuery.data

  return (
    <main className="p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-xl text-[var(--color-heading)]">{incident.title}</h1>
        <span className="rounded-md border border-[var(--color-border-strong)] px-3 py-1 text-xs font-bold uppercase text-[var(--color-heading)]">
          {incident.severity} - {incident.state}
        </span>
      </div>
      {incident.summary && <p className="mt-2 text-sm text-[var(--color-muted)]">{incident.summary}</p>}

      {error && (
        <div className="mt-3 rounded-md border border-[var(--color-warning-border)] bg-[var(--color-warning-bg)] p-3 text-sm" role="alert">
          {getIncidentError(error)}
        </div>
      )}

      {canManage && (
        <div className="mt-4 flex flex-wrap gap-2">
          {allowedTransitions[incident.state].map((next) => (
            <button
              key={next}
              type="button"
              className="min-h-9 rounded-md border border-[var(--color-border-strong)] px-3 text-xs font-bold capitalize text-[var(--color-heading)]"
              disabled={mutations.transition.isPending}
              onClick={() => void mutations.transition.mutateAsync(next)}
            >
              Move to {next}
            </button>
          ))}
        </div>
      )}

      <section className="mt-6">
        <h2 className="text-base text-[var(--color-heading)]">Action items</h2>
        <div className="mt-2 divide-y divide-[var(--color-border)] rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]">
          {incident.action_items.length === 0 ? (
            <p className="p-4 text-sm text-[var(--color-muted)]">No action items.</p>
          ) : (
            incident.action_items.map((item) => (
              <label key={item.id} className="flex items-center gap-2 px-4 py-2 text-sm text-[var(--color-heading)]">
                <input
                  type="checkbox"
                  className="size-4 accent-[var(--color-primary)]"
                  checked={item.is_completed}
                  disabled={!canManage || mutations.toggleItem.isPending}
                  onChange={(event) =>
                    void mutations.toggleItem.mutateAsync({ itemId: item.id, completed: event.target.checked })
                  }
                />
                {item.description}
              </label>
            ))
          )}
        </div>
        {canManage && (
          <div className="mt-2 flex gap-2">
            <input
              className="min-h-9 flex-1 rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface-solid)] px-3 text-sm text-[var(--color-text)]"
              placeholder="New action item"
              value={actionItemDescription}
              onChange={(event) => setActionItemDescription(event.target.value)}
            />
            <button
              type="button"
              className="min-h-9 rounded-md border border-[var(--color-border-strong)] px-3 text-xs font-bold text-[var(--color-heading)]"
              disabled={actionItemDescription.trim() === '' || mutations.addItem.isPending}
              onClick={async () => {
                await mutations.addItem.mutateAsync(actionItemDescription.trim())
                setActionItemDescription('')
              }}
            >
              Add
            </button>
          </div>
        )}
      </section>

      <section className="mt-6">
        <h2 className="text-base text-[var(--color-heading)]">Postmortem</h2>
        {incident.postmortem?.is_published ? (
          <div className="mt-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4 text-sm text-[var(--color-heading)]">
            <p className="font-bold">Published</p>
            <p className="mt-2 whitespace-pre-wrap">{incident.postmortem.summary}</p>
          </div>
        ) : canManage ? (
          <div className="mt-2 flex flex-col gap-2">
            <textarea
              className="min-h-24 rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface-solid)] p-3 text-sm text-[var(--color-text)]"
              placeholder="Summary"
              defaultValue={incident.postmortem?.summary ?? ''}
              onChange={(event) => setSummary(event.target.value)}
            />
            <textarea
              className="min-h-16 rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface-solid)] p-3 text-sm text-[var(--color-text)]"
              placeholder="Root cause"
              defaultValue={incident.postmortem?.root_cause ?? ''}
              onChange={(event) => setRootCause(event.target.value)}
            />
            <textarea
              className="min-h-16 rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface-solid)] p-3 text-sm text-[var(--color-text)]"
              placeholder="Impact"
              defaultValue={incident.postmortem?.impact ?? ''}
              onChange={(event) => setImpact(event.target.value)}
            />
            <div className="flex gap-2">
              <button
                type="button"
                className="min-h-9 rounded-md border border-[var(--color-border-strong)] px-3 text-xs font-bold text-[var(--color-heading)]"
                disabled={mutations.saveDraft.isPending}
                onClick={() =>
                  void mutations.saveDraft.mutateAsync({
                    summary: summary || incident.postmortem?.summary || '',
                    root_cause: rootCause || incident.postmortem?.root_cause || undefined,
                    impact: impact || incident.postmortem?.impact || undefined,
                  })
                }
              >
                Save draft
              </button>
              {incident.postmortem && (
                <button
                  type="button"
                  className="min-h-9 rounded-md bg-[var(--color-primary)] px-3 text-xs font-bold text-[var(--color-on-primary)]"
                  disabled={mutations.publish.isPending}
                  onClick={() => void mutations.publish.mutateAsync()}
                >
                  Publish
                </button>
              )}
            </div>
          </div>
        ) : (
          <p className="mt-2 text-sm text-[var(--color-muted)]">No postmortem yet.</p>
        )}
      </section>

      <section className="mt-6">
        <h2 className="text-base text-[var(--color-heading)]">Timeline</h2>
        <div className="mt-2 divide-y divide-[var(--color-border)] rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]">
          {incident.timeline.map((entry) => (
            <div key={entry.id} className="px-4 py-2 text-sm text-[var(--color-heading)]">
              <span className="text-xs text-[var(--color-muted)]">{new Date(entry.occurred_at).toLocaleString()}</span>
              <p>{entry.message}</p>
            </div>
          ))}
        </div>
      </section>
    </main>
  )
}
