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
      attention: z.boolean(),
      state: z.string().nullable(),
      age_bucket: z.string().nullable(),
      size_bucket: z.string().nullable(),
      event: z.string().nullable(),
      week: z.string().nullable(),
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
  attention?: boolean
  state?: 'closed_without_merge'
  age_bucket?: 'under_1_day' | '1_to_3_days' | '3_to_7_days' | 'over_7_days'
  size_bucket?: 'xs' | 'small' | 'medium' | 'large'
  event?: 'opened' | 'merged'
  week?: string
  page?: number
  per_page?: number
}

export async function getPullRequests(
  organizationId: number,
  filters: PullRequestExplorerFilters,
): Promise<PullRequestExplorerResponse> {
  const params = {
    ...filters,
    attention: filters.attention ? 1 : undefined,
  }
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/pull-requests`,
    { params },
  )

  return pullRequestExplorerResponseSchema.parse(response.data)
}
