import GitHubIcon from '@mui/icons-material/GitHub'
import LinkOutlinedIcon from '@mui/icons-material/LinkOutlined'
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
    isSubmitting,
    error,
    connect,
    disconnect,
    clearError,
  } = useGitHubConnectionFeatureContext()
  const callbackStatus = searchParams.get('github')
  const callbackMessage = callbackStatus
    ? callbackMessages[callbackStatus]
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
          <span className="rounded-full bg-[var(--color-primary-soft)] px-3 py-1 text-xs font-bold uppercase text-[var(--color-primary-strong)]">
            {connection.status === 'active' ? 'Active' : 'Action required'}
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

      <div className="mt-4 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-6">
        {isLoading ? (
          <div className="h-24 animate-pulse rounded-md bg-[var(--color-page-alt)]" aria-label="Loading GitHub connection" />
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
                  {connection.account.type ?? 'Account'} · {connection.repository_selection ?? 'selected'} repositories
                </p>
                <p className="mt-1 text-xs text-[var(--color-subtle)]">
                  Connected {connection.connected_at ? new Date(connection.connected_at).toLocaleString() : 'recently'}
                </p>
              </div>
            </div>
            {canDisconnect && (
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
