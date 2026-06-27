import { z } from 'zod'
import api from '../../lib/api'

const pullRequestRecordSchema = z.object({
  id: z.number().int().positive(),
  repository: z.object({
    id: z.number().int().positive(),
    name: z.string().min(1),
  }),
  number: z.number().int().positive(),
  title: z.string().min(1),
  author: z.string().nullable(),
  state: z.string().min(1),
  is_draft: z.boolean(),
  age_hours: z.number().int().nonnegative(),
  change_size: z.number().int().nonnegative(),
  review_status: z.enum(['DRAFT', 'REVIEWED', 'WAITING', 'UNREVIEWED']),
  attention_reasons: z.array(z.string().min(1)),
  external_url: z.string().nullable(),
})

const pullRequestExplorerResponseSchema = z.object({
  data: z.array(pullRequestRecordSchema),
  meta: z.object({
    current_page: z.number().int().positive(),
    last_page: z.number().int().positive(),
    per_page: z.number().int().positive(),
    total: z.number().int().nonnegative(),
    applied_filters: z.object({
      repository_ids: z.array(z.number().int().positive()),
      date_from: z.string(),
      date_to: z.string(),
      review_status: z.string().nullable(),
    }),
  }),
})

export type PullRequestRecord = z.infer<typeof pullRequestRecordSchema>
export type PullRequestExplorerResponse = z.infer<
  typeof pullRequestExplorerResponseSchema
>

export type PullRequestExplorerFilters = {
  repository_ids?: number[]
  date_from?: string
  date_to?: string
  review_status?: 'waiting'
  page?: number
  per_page?: number
}

export async function getPullRequests(
  organizationId: number,
  filters: PullRequestExplorerFilters,
): Promise<PullRequestExplorerResponse> {
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/pull-requests`,
    { params: filters },
  )

  return pullRequestExplorerResponseSchema.parse(response.data)
}
