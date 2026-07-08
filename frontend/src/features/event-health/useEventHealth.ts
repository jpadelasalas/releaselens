import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  getSyncHealth,
  getWebhookDelivery,
  getWebhookDeliveries,
  replayWebhookDelivery,
  type WebhookDeliveryFilters,
} from './eventHealthApi'

export function useWebhookDeliveries(
  organizationId: number | null,
  filters: WebhookDeliveryFilters,
) {
  return useQuery({
    queryKey: ['webhook-deliveries', organizationId, filters],
    queryFn: () => {
      if (organizationId === null) {
        throw new Error('Organization id is required to load webhook deliveries.')
      }

      return getWebhookDeliveries(organizationId, filters)
    },
    enabled: organizationId !== null,
  })
}

export function useWebhookDelivery(
  organizationId: number | null,
  deliveryId: number | null,
) {
  return useQuery({
    queryKey: ['webhook-delivery', organizationId, deliveryId],
    queryFn: () => {
      if (organizationId === null || deliveryId === null) {
        throw new Error('Organization id and delivery id are required.')
      }

      return getWebhookDelivery(organizationId, deliveryId)
    },
    enabled: organizationId !== null && deliveryId !== null,
  })
}

export function useSyncHealth(organizationId: number | null) {
  return useQuery({
    queryKey: ['sync-health', organizationId],
    queryFn: () => {
      if (organizationId === null) {
        throw new Error('Organization id is required to load sync health.')
      }

      return getSyncHealth(organizationId)
    },
    enabled: organizationId !== null,
  })
}

export function useReplayWebhookDelivery(organizationId: number | null) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (deliveryId: number) => {
      if (organizationId === null) {
        throw new Error('Organization id is required to replay a delivery.')
      }

      return replayWebhookDelivery(organizationId, deliveryId)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({
        queryKey: ['webhook-deliveries', organizationId],
      })
      void queryClient.invalidateQueries({
        queryKey: ['webhook-delivery', organizationId],
      })
    },
  })
}
