import { z } from 'zod'
import axios from 'axios'

import api from '../../lib/api'
import { prepareCsrfCookie } from '../../lib/csrf'

export const repositorySchema = z.object({
  id: z.number().int().positive(),
  github_repository_id: z.number().int().positive(),
  name: z.string().min(1),
  full_name: z.string().min(1),
  description: z.string().nullable(),
  visibility: z.string().min(1),
  default_branch: z.string().nullable(),
  html_url: z.string().nullable(),
  is_archived: z.boolean(),
  sync_enabled: z.boolean(),
  sync_status: z.string().min(1),
  last_sync_at: z.string().nullable(),
  last_successful_sync_at: z.string().nullable(),
})

const repositoriesResponseSchema = z.object({
  data: z.array(repositorySchema),
})

export type OrganizationRepository = z.infer<typeof repositorySchema>

export const availableGitHubRepositorySchema = z.object({
  github_repository_id: z.number().int().positive(),
  name: z.string().min(1),
  full_name: z.string().min(1),
  description: z.string().nullable(),
  visibility: z.string().min(1),
  default_branch: z.string().nullable(),
  html_url: z.string().nullable(),
  is_archived: z.boolean(),
  is_monitored: z.boolean(),
})

const availableRepositoriesResponseSchema = z.object({
  data: z.array(availableGitHubRepositorySchema),
})

const repositoryResponseSchema = z.object({ data: repositorySchema })

export type AvailableGitHubRepository = z.infer<
  typeof availableGitHubRepositorySchema
>

export async function getOrganizationRepositories(
  organizationId: number,
): Promise<OrganizationRepository[]> {
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/repositories`,
  )

  return repositoriesResponseSchema.parse(response.data).data
}

export async function getAvailableGitHubRepositories(
  organizationId: number,
): Promise<AvailableGitHubRepository[]> {
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/github/available-repositories`,
  )

  return availableRepositoriesResponseSchema.parse(response.data).data
}

export async function importOrganizationRepositories(
  organizationId: number,
  repositoryIds: number[],
): Promise<OrganizationRepository[]> {
  await prepareCsrfCookie()
  const response = await api.post<unknown>(
    `/api/v1/organizations/${organizationId}/repositories/import`,
    { repository_ids: repositoryIds },
  )

  return repositoriesResponseSchema.parse(response.data).data
}

export async function updateRepositoryMonitoring(
  organizationId: number,
  repositoryId: number,
  syncEnabled: boolean,
): Promise<OrganizationRepository> {
  await prepareCsrfCookie()
  const response = await api.patch<unknown>(
    `/api/v1/organizations/${organizationId}/repositories/${repositoryId}`,
    { sync_enabled: syncEnabled },
  )

  return repositoryResponseSchema.parse(response.data).data
}

export function getRepositoryError(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const message = error.response?.data?.error?.message

    if (typeof message === 'string') {
      return message
    }
  }

  return 'Repositories are unavailable. Please try again.'
}
