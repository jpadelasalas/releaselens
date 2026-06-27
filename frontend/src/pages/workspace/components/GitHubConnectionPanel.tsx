import GitHubIcon from '@mui/icons-material/GitHub'
import LinkOutlinedIcon from '@mui/icons-material/LinkOutlined'
import RefreshOutlinedIcon from '@mui/icons-material/RefreshOutlined'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'

import { ConfirmationDialog } from '../../../components/feedback/ConfirmationDialog'
import { useGitHubConnectionFeatureContext } from '../../../features/github-connection/useGitHubConnectionFeatureContext'

const callbackMessages: Record<string, string> = {
  connected: 'GitHub connected successfully.',
  cancelled: 'GitHub connection was cancelled.',
  github_state_invalid: 'The GitHub connection request expired. Please try again.',
  github_installation_in_use:
    'That GitHub installation is already linked to another workspace.',
  github_installation_unavailable:
    'GitHub could not verify the installation. Please try again.',
  github_permissions_invalid:
    'The GitHub App must use read-only pull-request permissions.',
  github_already_connected: 'This workspace already has a GitHub connection.',
  github_app_not_configured:
    'GitHub connection is not configured for this environment.',
}

export function GitHubConnectionPanel() {
  const [confirmingDisconnect, setConfirmingDisconnect] = useState(false)
  const [searchParams] = useSearchParams()
  const {
    connection,
    canConnect,
    canDisconnect,
    isLoading,
    isRefreshing,
    isSubmitting,
    error,
    connect,
    disconnect,
    refresh,
    clearError,
  } = useGitHubConnectionFeatureContext()
  const callbackStatus = searchParams.get('github')
  const callbackMessage =
    callbackStatus === 'connected' && connection?.status !== 'active'
      ? null
      : callbackStatus
        ? callbackMessages[callbackStatus]
        : null
  const isDisconnected = connection?.status === 'disconnected'
  const statusLabel = connection
    ? {
        active: 'Active',
        action_required: 'Action required',
        disconnected: 'Disconnected',
      }[connection.status]
    : null

  async function handleConnect() {
    clearError()

    try {
      await connect()
    } catch {
      // Mutation state exposes the controlled API error.
    }
  }

  async function handleDisconnect() {
    clearError()

    try {
      await disconnect()
      setConfirmingDisconnect(false)
    } catch {
      // Keep the dialog open so the API error remains in context.
    }
  }

  async function handleRefresh() {
    clearError()

    try {
      await refresh()
    } catch {
      // Query state exposes the controlled API error.
    }
  }

  return (
    <section className="mt-8" aria-labelledby="github-connection-title">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2
            id="github-connection-title"
            className="text-xl text-[var(--color-heading)]"
          >
            GitHub connection
          </h2>
          <p className="mt-1 text-sm text-[var(--color-muted)]">
            Read-only metadata and pull-request access through a GitHub App.
          </p>
        </div>
        {connection && (
          <span
            className={`rounded-full px-3 py-1 text-xs font-bold uppercase ${
              connection.status === 'active'
                ? 'bg-[var(--color-primary-soft)] text-[var(--color-primary-strong)]'
                : 'bg-[var(--color-warning-bg)] text-[var(--color-heading)]'
            }`}
          >
            {statusLabel}
          </span>
        )}
      </div>

      {callbackMessage && (
        <div
          className="mt-4 rounded-md border border-[var(--color-border)] bg-[var(--color-page-alt)] p-3 text-sm text-[var(--color-text)]"
          role="status"
        >
          {callbackMessage}
        </div>
      )}

      {error && (
        <div
          className="mt-4 rounded-md border border-[var(--color-warning-border)] bg-[var(--color-warning-bg)] p-3 text-sm"
          role="alert"
        >
          {error}
        </div>
      )}

      {connection?.verification_status === 'unavailable' && (
        <div
          className="mt-4 rounded-md border border-[var(--color-warning-border)] bg-[var(--color-warning-bg)] p-3 text-sm"
          role="status"
        >
          GitHub could not be reached. Showing the last known connection state.
        </div>
      )}

      <div className="mt-4 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-6">
        {isLoading ? (
          <div
            className="h-24 animate-pulse rounded-md bg-[var(--color-page-alt)]"
            aria-label="Loading GitHub connection"
          />
        ) : connection ? (
          <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
            <div className="flex items-start gap-4">
              <span className="grid size-11 shrink-0 place-items-center rounded-md bg-[var(--color-primary-soft)] text-[var(--color-primary-strong)]">
                <GitHubIcon />
              </span>
              <div>
                <strong className="text-[var(--color-heading)]">
                  {connection.account.login ?? 'GitHub account'}
                </strong>
                <p className="mt-1 text-sm text-[var(--color-muted)]">
                  {connection.account.type ?? 'Account'} -{' '}
                  {connection.repository_selection === 'all'
                    ? 'all repositories'
                    : 'selected repositories'}
                </p>
                <p className="mt-1 text-xs text-[var(--color-subtle)]">
                  {isDisconnected ? 'Previously connected' : 'Connected'}{' '}
                  {connection.connected_at
                    ? new Date(connection.connected_at).toLocaleString()
                    : 'recently'}
                </p>
                {connection.status === 'action_required' && (
                  <p className="mt-2 text-sm text-[var(--color-muted)]">
                    Review the installation in GitHub, then refresh this status.
                  </p>
                )}
              </div>
            </div>
            <div className="flex flex-wrap gap-2">
              <button
                className="min-h-10 rounded-md border border-[var(--color-border-strong)] px-4 text-sm font-bold text-[var(--color-heading)]"
                type="button"
                disabled={isRefreshing || isSubmitting}
                onClick={() => void handleRefresh()}
              >
                <span className="inline-flex items-center gap-2">
                  <RefreshOutlinedIcon fontSize="small" />
                  {isRefreshing ? 'Refreshing...' : 'Refresh'}
                </span>
              </button>
              {isDisconnected && canConnect && (
                <button
                  className="primary-action justify-center"
                  type="button"
                  disabled={isSubmitting}
                  onClick={() => void handleConnect()}
                >
                  <GitHubIcon fontSize="small" />
                  Reconnect
                </button>
              )}
              {!isDisconnected && canDisconnect && (
                <button
                  className="min-h-10 rounded-md border border-[var(--color-border-strong)] px-4 text-sm font-bold text-[var(--color-heading)]"
                  type="button"
                  disabled={isSubmitting}
                  onClick={() => setConfirmingDisconnect(true)}
                >
                  Disconnect
                </button>
              )}
            </div>
          </div>
        ) : (
          <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
            <div className="flex items-start gap-4">
              <span className="grid size-11 shrink-0 place-items-center rounded-md bg-[var(--color-page-alt)] text-[var(--color-muted)]">
                <LinkOutlinedIcon />
              </span>
              <div>
                <strong className="text-[var(--color-heading)]">No GitHub account connected</strong>
                <p className="mt-1 text-sm text-[var(--color-muted)]">
                  Connect an installation before selecting repositories.
                </p>
              </div>
            </div>
            {canConnect && (
              <button
                className="primary-action justify-center"
                type="button"
                disabled={isSubmitting}
                onClick={() => void handleConnect()}
              >
                <GitHubIcon fontSize="small" />
                {isSubmitting ? 'Opening GitHub...' : 'Connect GitHub'}
              </button>
            )}
          </div>
        )}
      </div>

      <ConfirmationDialog
        open={confirmingDisconnect}
        title="Disconnect GitHub?"
        description="Scheduled polling will stop. Existing imported analytics will remain available."
        confirmLabel="Disconnect"
        tone="danger"
        isPending={isSubmitting}
        onConfirm={() => void handleDisconnect()}
        onCancel={() => setConfirmingDisconnect(false)}
      />
    </section>
  )
}
