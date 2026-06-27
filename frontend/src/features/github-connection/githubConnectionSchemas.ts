import { z } from 'zod'

export const githubConnectionSchema = z.object({
  status: z.enum(['active', 'action_required', 'disconnected']),
  verification_status: z.enum(['verified', 'unavailable']),
  account: z.object({
    login: z.string().nullable(),
    type: z.string().nullable(),
  }),
  repository_selection: z.string().nullable(),
  permissions: z.record(z.string(), z.string()),
  connected_at: z.string().nullable(),
  suspended_at: z.string().nullable(),
})

export const githubConnectionResponseSchema = z.object({
  data: githubConnectionSchema.nullable(),
})

export const githubConnectResponseSchema = z.object({
  data: z.object({ url: z.url() }),
})

export type GitHubConnection = z.infer<typeof githubConnectionSchema>
