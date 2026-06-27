import { z } from 'zod'
import api from '../../lib/api'

const repositorySchema = z.object({
  id: z.number().int().positive(),
  name: z.string().min(1),
  full_name: z.string().min(1),
  last_successful_sync_at: z.string().nullable(),
})

const repositoriesResponseSchema = z.object({
  data: z.array(repositorySchema),
})

export type OrganizationRepository = z.infer<typeof repositorySchema>

export async function getOrganizationRepositories(
  organizationId: number,
): Promise<OrganizationRepository[]> {
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/repositories`,
  )

  return repositoriesResponseSchema.parse(response.data).data
}
