import api from '../../lib/api'
import { prepareCsrfCookie } from '../../lib/csrf'
import {
  authSessionResponseSchema,
  type AuthSession,
} from '../auth/authSchemas'
import type { CreateOrganizationValues } from './organizationSchemas'
import {
  organizationMemberResponseSchema,
  organizationMembersResponseSchema,
  type AddOrganizationMemberValues,
  type OrganizationMember,
  type OrganizationRole,
} from './organizationSchemas'

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

export async function getOrganizationMembers(
  organizationId: number,
): Promise<OrganizationMember[]> {
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/members`,
  )

  return organizationMembersResponseSchema.parse(response.data).data
}

export async function addOrganizationMember(
  organizationId: number,
  values: AddOrganizationMemberValues,
): Promise<OrganizationMember> {
  await prepareCsrfCookie()
  const response = await api.post<unknown>(
    `/api/v1/organizations/${organizationId}/members`,
    values,
  )

  return organizationMemberResponseSchema.parse(response.data).data
}

export async function updateOrganizationMemberRole(
  organizationId: number,
  membershipId: number,
  role: OrganizationRole,
): Promise<OrganizationMember> {
  await prepareCsrfCookie()
  const response = await api.patch<unknown>(
    `/api/v1/organizations/${organizationId}/members/${membershipId}`,
    { role },
  )

  return organizationMemberResponseSchema.parse(response.data).data
}

export async function removeOrganizationMember(
  organizationId: number,
  membershipId: number,
): Promise<void> {
  await prepareCsrfCookie()
  await api.delete(
    `/api/v1/organizations/${organizationId}/members/${membershipId}`,
  )
}
