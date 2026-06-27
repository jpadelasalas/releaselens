import axios from 'axios'

import api from '../../lib/api'
import { prepareCsrfCookie } from '../../lib/csrf'
import {
  authSessionResponseSchema,
  type AuthSession,
  type RegisterValues,
  type SignInValues,
} from './authSchemas'

export async function signIn(values: SignInValues): Promise<AuthSession> {
  await prepareCsrfCookie()
  const response = await api.post<unknown>('/api/v1/auth/login', values)

  return authSessionResponseSchema.parse(response.data).data
}

export async function registerAccount(
  values: RegisterValues,
): Promise<AuthSession> {
  await prepareCsrfCookie()
  const response = await api.post<unknown>('/api/v1/auth/register', values)

  return authSessionResponseSchema.parse(response.data).data
}

export async function getCurrentSession(): Promise<AuthSession> {
  const response = await api.get<unknown>('/api/v1/me')

  return authSessionResponseSchema.parse(response.data).data
}

export async function signOut(): Promise<void> {
  await prepareCsrfCookie()
  await api.post('/api/v1/auth/logout')
}

export function getAuthenticationError(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const message = error.response?.data?.error?.message

    if (typeof message === 'string') {
      return message
    }
  }

  return 'Authentication is unavailable. Please try again.'
}
