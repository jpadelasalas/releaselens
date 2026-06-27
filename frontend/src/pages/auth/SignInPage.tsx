import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { Link, useLocation, useNavigate } from 'react-router-dom'

import { signInSchema, type SignInValues } from '../../features/auth/authSchemas'
import { useAuthFeatureContext } from '../../features/auth/useAuthFeatureContext'
import { useAppSelector } from '../../app/store/hooks'
import { AuthPageLayout } from './AuthPageLayout'

export function SignInPage() {
  const navigate = useNavigate()
  const location = useLocation()
  const { signIn, clearError, error, isSubmitting } = useAuthFeatureContext()
  const isCheckingSession = useAppSelector(
    (state) => state.auth.status === 'checking',
  )
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<SignInValues>({
    resolver: zodResolver(signInSchema),
    defaultValues: { email: '', password: '' },
  })
  const destination =
    (location.state as { from?: string } | null)?.from ?? '/app'

  const onSubmit = handleSubmit(async (values) => {
    clearError()

    try {
      await signIn(values)
      navigate(destination, { replace: true })
    } catch {
      // Mutation state exposes the controlled API error.
    }
  })

  return (
    <AuthPageLayout
      eyebrow="Connected workspaces"
      title="Sign in to ReleaseLens"
      description="Access your private organizations and GitHub connections. The public demo remains available without an account."
    >
      <form
        className="grid gap-4 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-6"
        onSubmit={onSubmit}
        noValidate
      >
        <AuthField
          id="email"
          label="Email address"
          type="email"
          autoComplete="email"
          error={errors.email?.message}
          registration={register('email')}
        />
        <AuthField
          id="password"
          label="Password"
          type="password"
          autoComplete="current-password"
          error={errors.password?.message}
          registration={register('password')}
        />

        {error && (
          <div className="rounded-md border border-[var(--color-warning-border)] bg-[var(--color-warning-bg)] p-3 text-sm" role="alert">
            {error}
          </div>
        )}

        <button className="primary-action justify-center" type="submit" disabled={isSubmitting || isCheckingSession}>
          {isCheckingSession
            ? 'Checking session...'
            : isSubmitting
              ? 'Signing in...'
              : 'Sign In'}
        </button>
        <p className="text-center text-sm text-[var(--color-muted)]">
          New to ReleaseLens?{' '}
          <Link className="font-bold text-[var(--color-primary)]" to="/register">
            Create an account
          </Link>
        </p>
        <Link className="text-center text-sm text-[var(--color-muted)]" to="/">
          Return to public demo
        </Link>
      </form>
    </AuthPageLayout>
  )
}

function AuthField({
  id,
  label,
  type,
  autoComplete,
  error,
  registration,
}: {
  id: string
  label: string
  type: 'email' | 'password'
  autoComplete: string
  error?: string
  registration: ReturnType<ReturnType<typeof useForm<SignInValues>>['register']>
}) {
  return (
    <label className="grid gap-1 text-sm font-bold text-[var(--color-heading)]" htmlFor={id}>
      {label}
      <input
        {...registration}
        id={id}
        type={type}
        autoComplete={autoComplete}
        aria-invalid={Boolean(error)}
        className="min-h-11 rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface-solid)] px-3 font-normal text-[var(--color-text)]"
      />
      {error && <span className="font-normal text-red-700">{error}</span>}
    </label>
  )
}
