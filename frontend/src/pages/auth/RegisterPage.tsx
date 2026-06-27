import { zodResolver } from '@hookform/resolvers/zod'
import { useForm, type UseFormRegisterReturn } from 'react-hook-form'
import { Link, useNavigate } from 'react-router-dom'

import {
  registerSchema,
  type RegisterValues,
} from '../../features/auth/authSchemas'
import { useAuthFeatureContext } from '../../features/auth/useAuthFeatureContext'
import { AuthPageLayout } from './AuthPageLayout'

export function RegisterPage() {
  const navigate = useNavigate()
  const { register: createAccount, clearError, error, isSubmitting } =
    useAuthFeatureContext()
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<RegisterValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: {
      name: '',
      email: '',
      password: '',
      password_confirmation: '',
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
    },
  })

  const onSubmit = handleSubmit(async (values) => {
    clearError()

    try {
      await createAccount(values)
      navigate('/app', { replace: true })
    } catch {
      // Mutation state exposes the controlled API error.
    }
  })

  return (
    <AuthPageLayout
      eyebrow="Connected workspaces"
      title="Create your account"
      description="Use a ReleaseLens account for private workspaces. GitHub access is connected separately."
    >
      <form
        className="grid gap-4 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-6"
        onSubmit={onSubmit}
        noValidate
      >
        <Field label="Name" id="name" autoComplete="name" registration={register('name')} error={errors.name?.message} />
        <Field label="Email address" id="email" type="email" autoComplete="email" registration={register('email')} error={errors.email?.message} />
        <Field label="Password" id="password" type="password" autoComplete="new-password" registration={register('password')} error={errors.password?.message} />
        <Field label="Confirm password" id="password_confirmation" type="password" autoComplete="new-password" registration={register('password_confirmation')} error={errors.password_confirmation?.message} />

        <label className="grid gap-1 text-sm font-bold text-[var(--color-heading)]" htmlFor="timezone">
          Timezone
          <select
            {...register('timezone')}
            id="timezone"
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

        <button className="primary-action justify-center" type="submit" disabled={isSubmitting}>
          {isSubmitting ? 'Creating account...' : 'Create Account'}
        </button>
        <p className="text-center text-sm text-[var(--color-muted)]">
          Already registered?{' '}
          <Link className="font-bold text-[var(--color-primary)]" to="/sign-in">
            Sign in
          </Link>
        </p>
      </form>
    </AuthPageLayout>
  )
}

function Field({
  label,
  id,
  type = 'text',
  autoComplete,
  registration,
  error,
}: {
  label: string
  id: string
  type?: 'text' | 'email' | 'password'
  autoComplete: string
  registration: UseFormRegisterReturn
  error?: string
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
