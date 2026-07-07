import { z } from 'zod'
import api from '../../lib/api'

export const webhookDeliveryStatusSchema = z.enum([
  'received',
  'queued',
  'processing',
  'processed',
  'ignored',
  'retryable_failed',
  'dead_lettered',
  'purged',
])

export const webhookDeliverySchema = z.object({
  id: z.number().int().positive(),
  github_delivery_id: z.string().min(1),
  event_name: z.string().min(1),
  action_name: z.string().nullable(),
  status: webhookDeliveryStatusSchema,
  repository_id: z.number().int().positive().nullable(),
  error_category: z.string().nullable(),
  error_summary: z.string().nullable(),
  received_at: z.string(),
  queued_at: z.string().nullable(),
  processed_at: z.string().nullable(),
})

export const webhookProcessingAttemptSchema = z.object({
  attempt_number: z.number().int().positive(),
  status: z.enum(['processing', 'succeeded', 'failed']),
  started_at: z.string(),
  completed_at: z.string().nullable(),
  next_retry_at: z.string().nullable(),
  error_category: z.string().nullable(),
  error_summary: z.string().nullable(),
})

export const webhookDeliveryDetailSchema = webhookDeliverySchema.extend({
  attempts: z.array(webhookProcessingAttemptSchema),
})

const webhookDeliveryListResponseSchema = z.object({
  data: z.array(webhookDeliverySchema),
  meta: z.object({
    current_page: z.number().int().positive(),
    last_page: z.number().int().positive(),
    per_page: z.number().int().positive(),
    total: z.number().int().nonnegative(),
  }),
})

const webhookDeliveryDetailResponseSchema = z.object({
  data: webhookDeliveryDetailSchema,
})

export type WebhookDelivery = z.infer<typeof webhookDeliverySchema>
export type WebhookDeliveryDetail = z.infer<typeof webhookDeliveryDetailSchema>
export type WebhookDeliveryListResponse = z.infer<
  typeof webhookDeliveryListResponseSchema
>

export type WebhookDeliveryFilters = {
  status?: string
  event_name?: string
  page?: number
  per_page?: number
}

export async function getWebhookDeliveries(
  organizationId: number,
  filters: WebhookDeliveryFilters = {},
): Promise<WebhookDeliveryListResponse> {
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/webhook-deliveries`,
    { params: filters },
  )

  return webhookDeliveryListResponseSchema.parse(response.data)
}

export async function getWebhookDelivery(
  organizationId: number,
  deliveryId: number,
): Promise<WebhookDeliveryDetail> {
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/webhook-deliveries/${deliveryId}`,
  )

  return webhookDeliveryDetailResponseSchema.parse(response.data).data
}

export async function replayWebhookDelivery(
  organizationId: number,
  deliveryId: number,
): Promise<void> {
  await api.post(
    `/api/v1/organizations/${organizationId}/webhook-deliveries/${deliveryId}/replay`,
  )
}
