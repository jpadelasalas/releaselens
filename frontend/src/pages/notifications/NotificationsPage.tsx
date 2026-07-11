import { useScopeContext } from '../../app/scope/useScopeContext'
import { getNotificationError } from '../../features/notifications/notificationsApi'
import {
  useNotificationMutations,
  useNotificationPreferences,
  useNotifications,
} from '../../features/notifications/useNotifications'

export function NotificationsPage() {
  const { scope } = useScopeContext()
  const organizationId = scope.kind === 'connected' ? scope.organization.id : null
  const notificationsQuery = useNotifications(organizationId)
  const preferencesQuery = useNotificationPreferences(organizationId)
  const { markRead, markAllRead, updatePreference } = useNotificationMutations(organizationId)

  const error = markRead.error ?? markAllRead.error ?? updatePreference.error

  return (
    <main className="p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-xl text-[var(--color-heading)]">Notifications</h1>
        <button
          type="button"
          className="min-h-9 rounded-md border border-[var(--color-border-strong)] px-3 text-xs font-bold text-[var(--color-heading)]"
          disabled={(notificationsQuery.data?.unreadCount ?? 0) === 0 || markAllRead.isPending}
          onClick={() => void markAllRead.mutateAsync()}
        >
          Mark all read
        </button>
      </div>

      {error && (
        <div className="mt-3 rounded-md border border-[var(--color-warning-border)] bg-[var(--color-warning-bg)] p-3 text-sm" role="alert">
          {getNotificationError(error)}
        </div>
      )}

      <div className="mt-4 divide-y divide-[var(--color-border)] rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]">
        {notificationsQuery.isLoading ? (
          <div className="h-14 animate-pulse bg-[var(--color-page-alt)]" />
        ) : (notificationsQuery.data?.notifications ?? []).length === 0 ? (
          <p className="p-6 text-sm text-[var(--color-muted)]">No notifications yet.</p>
        ) : (
          notificationsQuery.data?.notifications.map((notification) => (
            <div key={notification.id} className="flex items-center justify-between gap-3 px-4 py-3">
              <div>
                <strong className="text-sm text-[var(--color-heading)]">{notification.title}</strong>
                {notification.read_at === null && (
                  <span className="ml-2 rounded-full bg-[var(--color-primary)] px-2 py-0.5 text-xs font-bold text-[var(--color-on-primary)]">
                    New
                  </span>
                )}
              </div>
              {notification.read_at === null && (
                <button
                  type="button"
                  className="text-xs font-bold text-[var(--color-muted)]"
                  onClick={() => void markRead.mutateAsync(notification.id)}
                >
                  Mark read
                </button>
              )}
            </div>
          ))
        )}
      </div>

      <section className="mt-6">
        <h2 className="text-base text-[var(--color-heading)]">Preferences</h2>
        <div className="mt-2 divide-y divide-[var(--color-border)] rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]">
          {(preferencesQuery.data ?? []).map((preference) => (
            <label key={preference.type} className="flex items-center justify-between gap-3 px-4 py-3">
              <span className="text-sm capitalize text-[var(--color-heading)]">
                {preference.type.replaceAll(/[._]/g, ' ')}
              </span>
              <input
                type="checkbox"
                className="size-4 accent-[var(--color-primary)]"
                checked={preference.enabled}
                disabled={updatePreference.isPending}
                onChange={(event) =>
                  void updatePreference.mutateAsync({ type: preference.type, enabled: event.target.checked })
                }
              />
            </label>
          ))}
        </div>
      </section>
    </main>
  )
}
