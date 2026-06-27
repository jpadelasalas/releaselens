import LogoutOutlinedIcon from '@mui/icons-material/LogoutOutlined'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'

import { useAppSelector } from '../../app/store/hooks'
import { BrandLink } from '../../components/navigation/BrandLink'
import { ThemeToggle } from '../../components/theme/ThemeToggle'
import { useAuthFeatureContext } from '../../features/auth/useAuthFeatureContext'
import {
  createOrganizationSchema,
  type CreateOrganizationValues,
} from '../../features/organizations/organizationSchemas'
import { useOrganizationFeatureContext } from '../../features/organizations/useOrganizationFeatureContext'
import { WorkspaceMembersPanel } from './components/WorkspaceMembersPanel'
import { GitHubConnectionPanel } from './components/GitHubConnectionPanel'

export function ConnectedWorkspacePage() {
  const navigate = useNavigate()
  const { user, memberships, activeOrganizationId } = useAppSelector(
    (state) => state.auth,
  )
  const { signOut, isSubmitting: isSigningOut } = useAuthFeatureContext()
  const {
    createWorkspace,
    activateWorkspace,
    clearError,
    error,
    isSubmitting,
  } = useOrganizationFeatureContext()
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<CreateOrganizationValues>({
    resolver: zodResolver(createOrganizationSchema),
    defaultValues: { name: '', timezone: 'UTC' },
  })
  const activeMembership = memberships.find(
    (membership) => membership.organization.id === activeOrganizationId,
  )
  const onCreateWorkspace = handleSubmit(async (values) => {
    clearError()

    try {
      await createWorkspace(values)
      reset({ name: '', timezone: values.timezone })
    } catch {
      // Mutation state exposes the controlled API error.
    }
  })

  async function handleSignOut() {
    await signOut()
    navigate('/sign-in', { replace: true })
  }

  async function handleActivateWorkspace(organizationId: number) {
    clearError()

    try {
      await activateWorkspace(organizationId)
    } catch {
      // Mutation state exposes the controlled API error.
    }
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
              disabled={isSigningOut}
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
        <p className="mt-2 text-[var(--color-muted)]">
          Create a private organization or switch between your existing memberships.
        </p>

        <div className="mt-8 grid items-start gap-6 lg:grid-cols-[minmax(280px,0.7fr)_minmax(0,1.3fr)]">
          <form
            className="grid gap-4 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-6"
            onSubmit={onCreateWorkspace}
            noValidate
          >
            <div>
              <h2 className="text-xl text-[var(--color-heading)]">Create workspace</h2>
              <p className="mt-1 text-sm text-[var(--color-muted)]">
                You will become the Owner.
              </p>
            </div>
            <label className="grid gap-1 text-sm font-bold text-[var(--color-heading)]" htmlFor="workspace-name">
              Workspace name
              <input
                {...register('name')}
                id="workspace-name"
                autoComplete="organization"
                aria-invalid={Boolean(errors.name)}
                className="min-h-11 rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface-solid)] px-3 font-normal text-[var(--color-text)]"
              />
              {errors.name && (
                <span className="font-normal text-red-700">{errors.name.message}</span>
              )}
            </label>
            <label className="grid gap-1 text-sm font-bold text-[var(--color-heading)]" htmlFor="workspace-timezone">
              Workspace timezone
              <select
                {...register('timezone')}
                id="workspace-timezone"
                className="min-h-11 rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface-solid)] px-3 font-normal text-[var(--color-text)]"
              >
                <option value="UTC">UTC</option>
                <option value="Asia/Manila">Asia/Manila</option>
                <option value="America/New_York">America/New York</option>
                <option value="Europe/London">Europe/London</option>
              </select>
            </label>

            {error && (
              <div className="rounded-md border border-[var(--color-warning-border)] bg-[var(--color-warning-bg)] p-3 text-sm" role="alert">
                {error}
              </div>
            )}

            <button
              className="primary-action justify-center"
              type="submit"
              disabled={isSubmitting}
            >
              {isSubmitting ? 'Saving workspace...' : 'Create Workspace'}
            </button>
          </form>

          <section aria-label="Your workspaces">
            <h2 className="text-xl text-[var(--color-heading)]">Your workspaces</h2>
            {memberships.length === 0 ? (
              <div className="mt-3 rounded-lg border border-dashed border-[var(--color-border-strong)] p-6 text-[var(--color-muted)]">
                No connected workspaces yet.
              </div>
            ) : (
              <div className="mt-3 grid gap-3 sm:grid-cols-2">
                {memberships.map((membership) => {
                  const isActive =
                    membership.organization.id === activeOrganizationId

                  return (
                    <article
                      key={membership.organization.id}
                      className="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-5"
                    >
                      <h3 className="text-lg text-[var(--color-heading)]">
                        {membership.organization.name}
                      </h3>
                      <p className="mt-1 text-sm capitalize text-[var(--color-muted)]">
                        {membership.role} - {membership.organization.timezone}
                      </p>
                      <button
                        className="mt-4 min-h-10 rounded-md border border-[var(--color-border-strong)] px-3 text-sm font-bold text-[var(--color-heading)] disabled:bg-[var(--color-primary-soft)]"
                        type="button"
                        disabled={isActive || isSubmitting}
                        onClick={() =>
                          void handleActivateWorkspace(
                            membership.organization.id,
                          )
                        }
                      >
                        {isActive ? 'Active workspace' : 'Open workspace'}
                      </button>
                    </article>
                  )
                })}
              </div>
            )}
          </section>
        </div>
        {activeMembership && <GitHubConnectionPanel />}
        <WorkspaceMembersPanel />
      </section>
    </main>
  )
}
