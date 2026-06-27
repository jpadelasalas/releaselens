import api from '../../lib/api'
import { prepareCsrfCookie } from '../auth/authApi'
import {
  authSessionResponseSchema,
  type AuthSession,
} from '../auth/authSchemas'
import type { CreateOrganizationValues } from './organizationSchemas'

export async function createOrganization(
  values: CreateOrganizationValues,
): Promise<AuthSession> {
  await prepareCsrfCookie()
  const response = await api.post<unknown>('/api/v1/organizations', values)

  return authSessionResponseSchema.parse(response.data).data
}

export async function activateOrganization(
  organizationId: number,
): Promise<AuthSession> {
  await prepareCsrfCookie()
  const response = await api.post<unknown>(
    `/api/v1/organizations/${organizationId}/activate`,
  )

  return authSessionResponseSchema.parse(response.data).data
}
