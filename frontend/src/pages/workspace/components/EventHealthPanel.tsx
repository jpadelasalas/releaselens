import { useState } from 'react'
import RefreshOutlinedIcon from '@mui/icons-material/RefreshOutlined'

import { useAppSelector } from '../../../app/store/hooks'
import { getApiErrorMessage } from '../../../lib/apiError'
import {
  useReplayWebhookDelivery,
  useWebhookDelivery,
  useWebhookDeliveries,
} from '../../../features/event-health/useEventHealth'

const REPLAYABLE_STATUSES = new Set(['retryable_failed', 'dead_lettered'])

export function EventHealthPanel() {
  const organizationId = useAppSelector(
    (state) => state.auth.activeOrganizationId,
  )
  const [statusFilter, setStatusFilter] = useState('')
  const [selectedDeliveryId, setSelectedDeliveryId] = useState<number | null>(
    null,
  )
  const { data, isLoading, isError } = useWebhookDeliveries(organizationId, {
    status: statusFilter || undefined,
  })
  const { data: detail } = useWebhookDelivery(
    organizationId,
    selectedDeliveryId,
  )
  const replay = useReplayWebhookDelivery(organizationId)

  async function handleReplay(deliveryId: number) {
    try {
      await replay.mutateAsync(deliveryId)
    } catch {
      // Mutation state exposes the controlled API error.
    }
  }

  if (organizationId === null || isError) {
    return null
  }

  return (
    <section className="mt-8" aria-labelledby="event-health-title">
      <div>
        <h2 id="event-health-title" className="text-xl text-[var(--color-heading)]">
          Event &amp; sync health
        </h2>
        <p className="mt-1 text-sm text-[var(--color-muted)]">
          Recent GitHub webhook deliveries and processing status. Retryable and
          dead-lettered deliveries can be replayed without losing their
          original identity.
        </p>
      </div>

      <div className="mt-4 flex flex-wrap items-center gap-3">
        <label
          className="text-sm font-bold text-[var(--color-heading)]"
          htmlFor="event-health-status"
        >
          Status
          <select
            id="event-health-status"
            className="ml-2 min-h-9 rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface-solid)] px-2 text-sm font-normal text-[var(--color-text)]"
            value={statusFilter}
            onChange={(event) => setStatusFilter(event.target.value)}
          >
            <option value="">All statuses</option>
            <option value="processed">Processed</option>
            <option value="ignored">Ignored</option>
            <option value="retryable_failed">Retryable failed</option>
            <option value="dead_lettered">Dead lettered</option>
          </select>
        </label>
      </div>

      {replay.isError && (
        <div
          className="mt-4 rounded-md border border-[var(--color-warning-border)] bg-[var(--color-warning-bg)] p-3 text-sm"
          role="alert"
        >
          {getApiErrorMessage(replay.error, 'Unable to replay this delivery.')}
        </div>
      )}

      {isLoading ? (
        <div className="mt-4 h-24 animate-pulse rounded-md bg-[var(--color-page-alt)]" />
      ) : data && data.data.length > 0 ? (
        <div className="mt-4 divide-y divide-[var(--color-border)] rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]">
          {data.data.map((delivery) => (
            <div
              key={delivery.id}
              className="flex flex-col justify-between gap-2 px-4 py-3 sm:flex-row sm:items-center"
            >
              <button
                type="button"
                className="min-w-0 flex-1 text-left"
                onClick={() => setSelectedDeliveryId(delivery.id)}
              >
                <strong className="block truncate text-sm text-[var(--color-heading)]">
                  {delivery.event_name}
                  {delivery.action_name ? `.${delivery.action_name}` : ''}
                </strong>
                <span className="block text-xs capitalize text-[var(--color-muted)]">
                  {delivery.status.replaceAll('_', ' ')}
                  {delivery.error_summary ? ` - ${delivery.error_summary}` : ''}
                </span>
              </button>
              {REPLAYABLE_STATUSES.has(delivery.status) && (
                <button
                  className="inline-flex min-h-9 items-center gap-2 rounded-md border border-[var(--color-border-strong)] px-3 text-xs font-bold text-[var(--color-heading)]"
                  type="button"
                  disabled={replay.isPending}
                  onClick={() => void handleReplay(delivery.id)}
                >
                  <RefreshOutlinedIcon fontSize="small" />
                  Replay
                </button>
              )}
            </div>
          ))}
        </div>
      ) : (
        <p className="mt-4 text-sm text-[var(--color-muted)]">
          No webhook deliveries yet.
        </p>
      )}

      {selectedDeliveryId !== null && detail && (
        <div className="mt-5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]">
          <div className="flex items-center justify-between border-b border-[var(--color-border)] px-4 py-3">
            <h3 className="text-base text-[var(--color-heading)]">
              Delivery attempts
            </h3>
            <button
              className="min-h-9 rounded-md border border-[var(--color-border-strong)] px-3 text-xs font-bold text-[var(--color-heading)]"
              type="button"
              onClick={() => setSelectedDeliveryId(null)}
            >
              Close
            </button>
          </div>
          {detail.attempts.length === 0 ? (
            <p className="p-5 text-sm text-[var(--color-muted)]">
              No processing attempts yet.
            </p>
          ) : (
            <div className="divide-y divide-[var(--color-border)]">
              {detail.attempts.map((attempt) => (
                <div
                  key={attempt.attempt_number}
                  className="grid gap-1 px-4 py-3 text-sm sm:grid-cols-[80px_1fr_auto]"
                >
                  <strong className="text-[var(--color-heading)]">
                    #{attempt.attempt_number}
                  </strong>
                  <span className="text-[var(--color-muted)]">
                    {attempt.status}
                    {attempt.error_summary ? ` - ${attempt.error_summary}` : ''}
                  </span>
                  <span className="text-xs text-[var(--color-subtle)]">
                    {new Date(attempt.started_at).toLocaleString()}
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
