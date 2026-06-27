import LogoutOutlinedIcon from '@mui/icons-material/LogoutOutlined'
import { useNavigate } from 'react-router-dom'

import { useAppSelector } from '../../app/store/hooks'
import { BrandLink } from '../../components/navigation/BrandLink'
import { ThemeToggle } from '../../components/theme/ThemeToggle'
import { useAuthFeatureContext } from '../../features/auth/useAuthFeatureContext'

export function ConnectedWorkspacePage() {
  const navigate = useNavigate()
  const { user, memberships, activeOrganizationId } = useAppSelector(
    (state) => state.auth,
  )
  const { signOut, isSubmitting } = useAuthFeatureContext()
  const activeMembership = memberships.find(
    (membership) => membership.organization.id === activeOrganizationId,
  )

  async function handleSignOut() {
    await signOut()
    navigate('/sign-in', { replace: true })
  }

  return (
    <main className="min-h-svh bg-[var(--color-dashboard-page)]">
      <header className="border-b border-[var(--color-border)] bg-[var(--color-surface)] px-6 py-4">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-4">
          <BrandLink />
          <div className="flex items-center gap-3">
            <div className="hidden text-right sm:block">
              <strong className="block text-sm text-[var(--color-heading)]">{user?.name}</strong>
              <span className="text-xs text-[var(--color-muted)]">{user?.email}</span>
            </div>
            <ThemeToggle />
            <button
              className="inline-flex min-h-10 items-center gap-2 rounded-md border border-[var(--color-border-strong)] px-3 text-sm font-bold text-[var(--color-heading)]"
              type="button"
              onClick={() => void handleSignOut()}
              disabled={isSubmitting}
            >
              <LogoutOutlinedIcon fontSize="small" />
              Sign Out
            </button>
          </div>
        </div>
      </header>

      <section className="mx-auto max-w-6xl px-6 py-10">
        <p className="eyebrow">Connected workspace</p>
        <h1 className="mt-2 text-4xl text-[var(--color-heading)]">
          {activeMembership?.organization.name ?? 'Choose your workspace'}
        </h1>
        {memberships.length === 0 ? (
          <div className="mt-8 max-w-2xl rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-6">
            <h2 className="text-xl text-[var(--color-heading)]">Create your first workspace</h2>
            <p className="mt-2 text-[var(--color-muted)]">
              Your account is ready. Organization creation and role-based membership are the next connected-workspace step.
            </p>
          </div>
        ) : (
          <div className="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {memberships.map((membership) => (
              <article
                key={membership.organization.id}
                className="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-5"
              >
                <h2 className="text-lg text-[var(--color-heading)]">{membership.organization.name}</h2>
                <p className="mt-1 text-sm capitalize text-[var(--color-muted)]">{membership.role}</p>
              </article>
            ))}
          </div>
        )}
      </section>
    </main>
  )
}
