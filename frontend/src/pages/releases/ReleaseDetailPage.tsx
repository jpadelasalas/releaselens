import { useState } from 'react'
import { useParams } from 'react-router-dom'

import { useScopeContext } from '../../app/scope/useScopeContext'
import {
  exportReleaseMarkdown,
  getReleaseError,
  type ReleaseState,
} from '../../features/releases/releasesApi'
import { useRelease, useReleaseMutations } from '../../features/releases/useReleases'

const allowedTransitions: Record<ReleaseState, ReleaseState[]> = {
  draft: ['in_review', 'cancelled'],
  in_review: ['draft', 'approved', 'cancelled'],
  approved: ['in_review', 'released', 'cancelled'],
  released: ['closed'],
  closed: [],
  cancelled: [],
}

export function ReleaseDetailPage() {
  const { releaseId } = useParams<{ releaseId: string }>()
  const id = releaseId ? Number(releaseId) : null
  const { scope } = useScopeContext()
  const organizationId = scope.kind === 'connected' ? scope.organization.id : null
  const canManage = scope.kind === 'connected' && (scope.role === 'owner' || scope.role === 'manager')
  const releaseQuery = useRelease(organizationId, id)
  const mutations = useReleaseMutations(organizationId, id ?? undefined)
  const [pullRequestId, setPullRequestId] = useState('')
  const [checklistLabel, setChecklistLabel] = useState('')

  const error =
    mutations.transition.error ??
    mutations.addPullRequest.error ??
    mutations.addItem.error ??
    mutations.approve.error

  if (releaseQuery.isLoading || !releaseQuery.data) {
    return <main className="p-6">Loading release...</main>
  }

  async function handleExport(organizationId: number, releaseId: number, title: string) {
    const blob = await exportReleaseMarkdown(organizationId, releaseId)
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `${title.replaceAll(/\s+/g, '-').toLowerCase()}.md`
    link.click()
    URL.revokeObjectURL(url)
  }

  const release = releaseQuery.data

  return (
    <main className="p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-xl text-[var(--color-heading)]">{release.title}</h1>
        <div className="flex items-center gap-2">
          <span className="rounded-md border border-[var(--color-border-strong)] px-3 py-1 text-xs font-bold capitalize text-[var(--color-heading)]">
            {release.state.replaceAll('_', ' ')}
          </span>
          <button
            type="button"
            className="min-h-9 rounded-md border border-[var(--color-border-strong)] px-3 text-xs font-bold text-[var(--color-heading)]"
            onClick={() => void handleExport(release.organization_id, release.id, release.title)}
          >
            Export .md
          </button>
        </div>
      </div>
      {release.description && (
        <p className="mt-2 text-sm text-[var(--color-muted)]">{release.description}</p>
      )}

      {release.readiness_warnings.length > 0 && (
        <ul className="mt-3 rounded-md border border-[var(--color-warning-border)] bg-[var(--color-warning-bg)] p-3 text-sm">
          {release.readiness_warnings.map((warning) => (
            <li key={warning.code}>{warning.message}</li>
          ))}
        </ul>
      )}

      {error && (
        <div className="mt-3 rounded-md border border-[var(--color-warning-border)] bg-[var(--color-warning-bg)] p-3 text-sm" role="alert">
          {getReleaseError(error)}
        </div>
      )}

      {canManage && (
        <div className="mt-4 flex flex-wrap gap-2">
          {allowedTransitions[release.state].map((next) => (
            <button
              key={next}
              type="button"
              className="min-h-9 rounded-md border border-[var(--color-border-strong)] px-3 text-xs font-bold capitalize text-[var(--color-heading)]"
              disabled={mutations.transition.isPending}
              onClick={() => void mutations.transition.mutateAsync(next)}
            >
              Move to {next.replaceAll('_', ' ')}
            </button>
          ))}
          {release.state === 'in_review' && (
            <button
              type="button"
              className="min-h-9 rounded-md bg-[var(--color-primary)] px-3 text-xs font-bold text-[var(--color-on-primary)]"
              disabled={mutations.approve.isPending}
              onClick={() => void mutations.approve.mutateAsync()}
            >
              Approve
            </button>
          )}
        </div>
      )}

      <section className="mt-6">
        <h2 className="text-base text-[var(--color-heading)]">Checklist</h2>
        <div className="mt-2 divide-y divide-[var(--color-border)] rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]">
          {release.checklist_items.length === 0 ? (
            <p className="p-4 text-sm text-[var(--color-muted)]">No checklist items.</p>
          ) : (
            release.checklist_items.map((item) => (
              <div key={item.id} className="flex items-center justify-between gap-3 px-4 py-2">
                <label className="flex items-center gap-2 text-sm text-[var(--color-heading)]">
                  <input
                    type="checkbox"
                    className="size-4 accent-[var(--color-primary)]"
                    checked={item.completed_at !== null}
                    disabled={!canManage || mutations.toggleItem.isPending}
                    onChange={(event) =>
                      void mutations.toggleItem.mutateAsync({ itemId: item.id, completed: event.target.checked })
                    }
                  />
                  {item.label}
                  {item.is_required && <span className="text-xs text-[var(--color-muted)]">(required)</span>}
                </label>
                {canManage && (
                  <button
                    type="button"
                    className="text-xs font-bold text-[var(--color-muted)]"
                    onClick={() => void mutations.removeItem.mutateAsync(item.id)}
                  >
                    Remove
                  </button>
                )}
              </div>
            ))
          )}
        </div>
        {canManage && (
          <div className="mt-2 flex gap-2">
            <input
              className="min-h-9 flex-1 rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface-solid)] px-3 text-sm text-[var(--color-text)]"
              placeholder="New checklist item"
              value={checklistLabel}
              onChange={(event) => setChecklistLabel(event.target.value)}
            />
            <button
              type="button"
              className="min-h-9 rounded-md border border-[var(--color-border-strong)] px-3 text-xs font-bold text-[var(--color-heading)]"
              disabled={checklistLabel.trim() === '' || mutations.addItem.isPending}
              onClick={async () => {
                await mutations.addItem.mutateAsync({ label: checklistLabel.trim(), isRequired: true })
                setChecklistLabel('')
              }}
            >
              Add
            </button>
          </div>
        )}
      </section>

      <section className="mt-6">
        <h2 className="text-base text-[var(--color-heading)]">Pull requests</h2>
        <div className="mt-2 divide-y divide-[var(--color-border)] rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]">
          {release.pull_requests.length === 0 ? (
            <p className="p-4 text-sm text-[var(--color-muted)]">No pull requests included.</p>
          ) : (
            release.pull_requests.map((pr) => (
              <div key={pr.id} className="flex items-center justify-between gap-3 px-4 py-2">
                <span className="text-sm text-[var(--color-heading)]">
                  {pr.repository_name} #{pr.number} - {pr.title}
                </span>
                {canManage && (
                  <button
                    type="button"
                    className="text-xs font-bold text-[var(--color-muted)]"
                    onClick={() => void mutations.removePullRequest.mutateAsync(pr.id)}
                  >
                    Remove
                  </button>
                )}
              </div>
            ))
          )}
        </div>
        {canManage && (
          <div className="mt-2 flex gap-2">
            <input
              className="min-h-9 flex-1 rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface-solid)] px-3 text-sm text-[var(--color-text)]"
              placeholder="Pull request id"
              value={pullRequestId}
              onChange={(event) => setPullRequestId(event.target.value)}
            />
            <button
              type="button"
              className="min-h-9 rounded-md border border-[var(--color-border-strong)] px-3 text-xs font-bold text-[var(--color-heading)]"
              disabled={pullRequestId.trim() === '' || mutations.addPullRequest.isPending}
              onClick={async () => {
                await mutations.addPullRequest.mutateAsync(Number(pullRequestId))
                setPullRequestId('')
              }}
            >
              Add
            </button>
          </div>
        )}
      </section>

      <section className="mt-6">
        <h2 className="text-base text-[var(--color-heading)]">Approvals</h2>
        <div className="mt-2 divide-y divide-[var(--color-border)] rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]">
          {release.approvals.length === 0 ? (
            <p className="p-4 text-sm text-[var(--color-muted)]">No approvals recorded.</p>
          ) : (
            release.approvals.map((approval) => (
              <div key={approval.id} className="px-4 py-2 text-sm text-[var(--color-heading)]">
                Approved {new Date(approval.approved_at).toLocaleString()}
              </div>
            ))
          )}
        </div>
      </section>

      {release.deployments.length > 0 && (
        <section className="mt-6">
          <h2 className="text-base text-[var(--color-heading)]">Deployments</h2>
          <div className="mt-2 divide-y divide-[var(--color-border)] rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]">
            {release.deployments.map((deployment) => (
              <div key={deployment.id} className="flex items-center justify-between gap-3 px-4 py-2 text-sm">
                <span className="text-[var(--color-heading)]">
                  {deployment.repository_name}
                  <span className="ml-2 text-xs capitalize text-[var(--color-muted)]">
                    {deployment.normalized_environment}
                    {deployment.is_production ? ' - production' : ''}
                  </span>
                </span>
                <span className="text-xs font-bold capitalize text-[var(--color-muted)]">
                  {deployment.status.replaceAll('_', ' ')}
                </span>
              </div>
            ))}
          </div>
        </section>
      )}
    </main>
  )
}
