import FolderOutlinedIcon from '@mui/icons-material/FolderOutlined'
import RefreshOutlinedIcon from '@mui/icons-material/RefreshOutlined'
import SearchOutlinedIcon from '@mui/icons-material/SearchOutlined'

import { useRepositoryManagementContext } from '../../../features/repositories/useRepositoryManagementContext'
import { useSynchronizationContext } from '../../../features/synchronization/useSynchronizationContext'

export function RepositoryManagementPanel() {
  const {
    repositories,
    availableRepositories,
    filteredRepositories,
    selectedRepositoryIds,
    search,
    canManage,
    hasActiveConnection,
    isLoading,
    isSaving,
    error,
    setSearch,
    toggleSelection,
    saveSelection,
    changeMonitoring,
    refreshAvailable,
    clearError,
  } = useRepositoryManagementContext()
  const {
    activeRepositoryId,
    runs,
    canSync,
    isLoadingHistory,
    isRequesting,
    error: syncError,
    requestSync,
    showHistory,
    closeHistory,
    refreshHistory,
    clearError: clearSyncError,
  } = useSynchronizationContext()

  async function handleSave() {
    clearError()

    try {
      await saveSelection()
    } catch {
      // Mutation state exposes the controlled API error.
    }
  }

  async function handleMonitoring(repositoryId: number, enabled: boolean) {
    clearError()

    try {
      await changeMonitoring(repositoryId, enabled)
    } catch {
      // Mutation state exposes the controlled API error.
    }
  }

  async function handleRefresh() {
    clearError()

    try {
      await refreshAvailable()
    } catch {
      // Query state exposes the controlled API error.
    }
  }

  async function handleSync(repositoryId: number) {
    clearSyncError()

    try {
      await requestSync(repositoryId)
    } catch {
      // Mutation state exposes the controlled API error.
    }
  }

  return (
    <section className="mt-8" aria-labelledby="repository-management-title">
      <div>
        <h2
          id="repository-management-title"
          className="text-xl text-[var(--color-heading)]"
        >
          Repositories
        </h2>
        <p className="mt-1 text-sm text-[var(--color-muted)]">
          Select repositories to monitor and control polling independently.
        </p>
      </div>

      {error && (
        <div
          className="mt-4 rounded-md border border-[var(--color-warning-border)] bg-[var(--color-warning-bg)] p-3 text-sm"
          role="alert"
        >
          {error}
        </div>
      )}

      {syncError && (
        <div
          className="mt-4 rounded-md border border-[var(--color-warning-border)] bg-[var(--color-warning-bg)] p-3 text-sm"
          role="alert"
        >
          {syncError}
        </div>
      )}

      {!hasActiveConnection && (
        <div className="mt-4 rounded-md border border-[var(--color-warning-border)] bg-[var(--color-warning-bg)] p-4 text-sm">
          Connect GitHub to discover repositories. Previously imported data remains available.
        </div>
      )}

      {canManage && hasActiveConnection && (
        <div className="mt-4 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]">
          <div className="flex flex-col gap-3 border-b border-[var(--color-border)] p-4 sm:flex-row">
            <label className="relative min-w-0 flex-1" htmlFor="repository-search">
              <span className="sr-only">Search available repositories</span>
              <SearchOutlinedIcon
                className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-muted)]"
                fontSize="small"
              />
              <input
                id="repository-search"
                className="min-h-11 w-full rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface-solid)] pl-10 pr-3 text-[var(--color-text)]"
                type="search"
                value={search}
                placeholder="Search repositories"
                onChange={(event) => setSearch(event.target.value)}
              />
            </label>
            <button
              className="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-[var(--color-border-strong)] px-4 text-sm font-bold text-[var(--color-heading)]"
              type="button"
              disabled={isLoading || isSaving}
              onClick={() => void handleRefresh()}
            >
              <RefreshOutlinedIcon fontSize="small" />
              Refresh list
            </button>
          </div>

          {isLoading ? (
            <div className="grid gap-2 p-4" aria-label="Loading repositories">
              <div className="h-14 animate-pulse rounded-md bg-[var(--color-page-alt)]" />
              <div className="h-14 animate-pulse rounded-md bg-[var(--color-page-alt)]" />
            </div>
          ) : filteredRepositories.length === 0 ? (
            <p className="p-6 text-sm text-[var(--color-muted)]">
              {availableRepositories.length === 0
                ? 'No repositories are available to this installation.'
                : 'No repositories match your search.'}
            </p>
          ) : (
            <div className="max-h-80 divide-y divide-[var(--color-border)] overflow-y-auto">
              {filteredRepositories.map((repository) => (
                <label
                  key={repository.github_repository_id}
                  className="flex cursor-pointer items-start gap-3 px-4 py-3"
                >
                  <input
                    className="mt-1 size-4 accent-[var(--color-primary)]"
                    type="checkbox"
                    checked={selectedRepositoryIds.includes(
                      repository.github_repository_id,
                    )}
                    onChange={() =>
                      toggleSelection(repository.github_repository_id)
                    }
                  />
                  <span className="min-w-0 flex-1">
                    <strong className="block truncate text-sm text-[var(--color-heading)]">
                      {repository.full_name}
                    </strong>
                    <span className="mt-1 block text-xs capitalize text-[var(--color-muted)]">
                      {repository.visibility} - {repository.default_branch ?? 'No default branch'}
                      {repository.is_archived ? ' - Archived' : ''}
                    </span>
                  </span>
                </label>
              ))}
            </div>
          )}

          <div className="flex flex-wrap items-center justify-between gap-3 border-t border-[var(--color-border)] px-4 py-3">
            <span className="text-sm text-[var(--color-muted)]">
              {selectedRepositoryIds.length} selected
            </span>
            <button
              className="primary-action justify-center"
              type="button"
              disabled={selectedRepositoryIds.length === 0 || isSaving}
              onClick={() => void handleSave()}
            >
              {isSaving ? 'Saving...' : 'Save selection'}
            </button>
          </div>
        </div>
      )}

      <div className="mt-5">
        <h3 className="text-base text-[var(--color-heading)]">
          Imported repositories
        </h3>
        {repositories.length === 0 ? (
          <div className="mt-2 rounded-md border border-dashed border-[var(--color-border-strong)] p-5 text-sm text-[var(--color-muted)]">
            No repositories have been imported.
          </div>
        ) : (
          <div className="mt-2 divide-y divide-[var(--color-border)] rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]">
            {repositories.map((repository) => (
              <div
                key={repository.id}
                className="flex flex-col justify-between gap-3 px-4 py-3 sm:flex-row sm:items-center"
              >
                <div className="flex min-w-0 items-start gap-3">
                  <FolderOutlinedIcon
                    className="mt-0.5 shrink-0 text-[var(--color-muted)]"
                    fontSize="small"
                  />
                  <div className="min-w-0">
                    <strong className="block truncate text-sm text-[var(--color-heading)]">
                      {repository.full_name}
                    </strong>
                    <span className="text-xs capitalize text-[var(--color-muted)]">
                      {repository.visibility} - {repository.sync_status.replaceAll('_', ' ')}
                      {!repository.is_accessible ? ' - Action required' : ''}
                    </span>
                  </div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  <label className="inline-flex items-center gap-2 text-sm font-bold text-[var(--color-heading)]">
                    <input
                      className="size-4 accent-[var(--color-primary)]"
                      type="checkbox"
                      checked={repository.sync_enabled}
                      disabled={!canManage || isSaving}
                      onChange={(event) =>
                        void handleMonitoring(repository.id, event.target.checked)
                      }
                    />
                    Monitoring
                  </label>
                  <button
                    className="min-h-9 rounded-md border border-[var(--color-border-strong)] px-3 text-xs font-bold text-[var(--color-heading)]"
                    type="button"
                    onClick={() => showHistory(repository.id)}
                  >
                    History
                  </button>
                  {canSync && (
                    <button
                      className="min-h-9 rounded-md bg-[var(--color-primary)] px-3 text-xs font-bold text-[var(--color-on-primary)]"
                      type="button"
                      disabled={
                        !hasActiveConnection ||
                        !repository.sync_enabled ||
                        !repository.is_accessible ||
                        isRequesting
                      }
                      onClick={() => void handleSync(repository.id)}
                    >
                      {isRequesting && activeRepositoryId === repository.id
                        ? 'Queueing...'
                        : 'Sync now'}
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {activeRepositoryId !== null && (
        <div className="mt-5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]">
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--color-border)] px-4 py-3">
            <h3 className="text-base text-[var(--color-heading)]">Sync history</h3>
            <div className="flex gap-2">
              <button
                className="min-h-9 rounded-md border border-[var(--color-border-strong)] px-3 text-xs font-bold text-[var(--color-heading)]"
                type="button"
                onClick={() => void refreshHistory()}
              >
                Refresh
              </button>
              <button
                className="min-h-9 rounded-md border border-[var(--color-border-strong)] px-3 text-xs font-bold text-[var(--color-heading)]"
                type="button"
                onClick={closeHistory}
              >
                Close
              </button>
            </div>
          </div>
          {isLoadingHistory ? (
            <div className="h-20 animate-pulse bg-[var(--color-page-alt)]" />
          ) : runs.length === 0 ? (
            <p className="p-5 text-sm text-[var(--color-muted)]">
              No synchronization runs yet.
            </p>
          ) : (
            <div className="divide-y divide-[var(--color-border)]">
              {runs.map((run) => (
                <div
                  key={run.id}
                  className="grid gap-1 px-4 py-3 text-sm sm:grid-cols-[120px_1fr_auto] sm:items-center"
                >
                  <strong className="capitalize text-[var(--color-heading)]">
                    {run.status.replaceAll('_', ' ')}
                  </strong>
                  <span className="text-[var(--color-muted)]">
                    {run.created_count} created, {run.updated_count} updated
                    {run.error_summary ? ` - ${run.error_summary}` : ''}
                  </span>
                  <span className="text-xs text-[var(--color-subtle)]">
                    {run.started_at
                      ? new Date(run.started_at).toLocaleString()
                      : 'Queued'}
                  </span>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </section>
  )
}
