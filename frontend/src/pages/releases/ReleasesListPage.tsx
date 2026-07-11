import { useState } from 'react'
import { Link } from 'react-router-dom'

import { useScopeContext } from '../../app/scope/useScopeContext'
import { getReleaseError } from '../../features/releases/releasesApi'
import { useReleaseMutations, useReleases } from '../../features/releases/useReleases'

const stateFilters = ['all', 'draft', 'in_review', 'approved', 'released', 'closed', 'cancelled'] as const

export function ReleasesListPage() {
  const { scope } = useScopeContext()
  const organizationId = scope.kind === 'connected' ? scope.organization.id : null
  const canManage = scope.kind === 'connected' && (scope.role === 'owner' || scope.role === 'manager')
  const [state, setState] = useState<(typeof stateFilters)[number]>('all')
  const [title, setTitle] = useState('')
  const releasesQuery = useReleases(organizationId, state === 'all' ? undefined : state)
  const { create } = useReleaseMutations(organizationId)

  async function handleCreate() {
    if (title.trim() === '') return

    try {
      await create.mutateAsync({ title: title.trim() })
      setTitle('')
    } catch {
      // Mutation state exposes the controlled API error.
    }
  }

  return (
    <main className="p-6">
      <h1 className="text-xl text-[var(--color-heading)]">Releases</h1>

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
            {option.replaceAll('_', ' ')}
          </button>
        ))}
      </div>

      {canManage && (
        <div className="mt-4 flex gap-2">
          <input
            className="min-h-11 flex-1 rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface-solid)] px-3 text-[var(--color-text)]"
            placeholder="New release title"
            value={title}
            onChange={(event) => setTitle(event.target.value)}
          />
          <button
            type="button"
            className="primary-action justify-center"
            disabled={title.trim() === '' || create.isPending}
            onClick={() => void handleCreate()}
          >
            {create.isPending ? 'Creating...' : 'Create release'}
          </button>
        </div>
      )}

      {create.error && (
        <div className="mt-3 rounded-md border border-[var(--color-warning-border)] bg-[var(--color-warning-bg)] p-3 text-sm" role="alert">
          {getReleaseError(create.error)}
        </div>
      )}

      <div className="mt-5 divide-y divide-[var(--color-border)] rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]">
        {releasesQuery.isLoading ? (
          <div className="h-14 animate-pulse bg-[var(--color-page-alt)]" />
        ) : (releasesQuery.data ?? []).length === 0 ? (
          <p className="p-6 text-sm text-[var(--color-muted)]">No releases yet.</p>
        ) : (
          (releasesQuery.data ?? []).map((release) => (
            <Link
              key={release.id}
              to={`/app/releases/${release.id}`}
              className="flex items-center justify-between gap-3 px-4 py-3 hover:bg-[var(--color-page-alt)]"
            >
              <strong className="text-sm text-[var(--color-heading)]">{release.title}</strong>
              <span className="text-xs font-bold capitalize text-[var(--color-muted)]">
                {release.state.replaceAll('_', ' ')}
              </span>
            </Link>
          ))
        )}
      </div>
    </main>
  )
}
