import axios from 'axios'
import { z } from 'zod'

import api from '../../lib/api'
import { prepareCsrfCookie } from '../../lib/csrf'

export const syncRunSchema = z.object({
  id: z.number().int().positive(),
  repository_id: z.number().int().positive(),
  trigger_type: z.string(),
  status: z.enum(['queued', 'running', 'success', 'partial', 'deferred', 'failed']),
  started_at: z.string().nullable(),
  completed_at: z.string().nullable(),
  created_count: z.number().int().nonnegative(),
  updated_count: z.number().int().nonnegative(),
  unchanged_count: z.number().int().nonnegative(),
  failed_count: z.number().int().nonnegative(),
  rate_limit_remaining: z.number().int().nonnegative().nullable(),
  rate_limit_reset_at: z.string().nullable(),
  error_category: z.string().nullable(),
  error_summary: z.string().nullable(),
})

const syncRunResponseSchema = z.object({ data: syncRunSchema })
const syncRunsResponseSchema = z.object({ data: z.array(syncRunSchema) })

export type SyncRun = z.infer<typeof syncRunSchema>

export async function requestRepositorySync(
  organizationId: number,
  repositoryId: number,
): Promise<SyncRun> {
  await prepareCsrfCookie()
  const response = await api.post<unknown>(
    `/api/v1/organizations/${organizationId}/repositories/${repositoryId}/sync`,
  )

  return syncRunResponseSchema.parse(response.data).data
}

export async function getRepositorySyncRuns(
  organizationId: number,
  repositoryId: number,
): Promise<SyncRun[]> {
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/repositories/${repositoryId}/sync-runs`,
  )

  return syncRunsResponseSchema.parse(response.data).data
}

export function getSynchronizationError(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const message = error.response?.data?.error?.message

    if (typeof message === 'string') {
      return message
    }
  }

  return 'Synchronization is unavailable. Please try again.'
}
