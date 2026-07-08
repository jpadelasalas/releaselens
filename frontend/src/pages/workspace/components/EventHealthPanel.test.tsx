import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { useAppSelector } from '../../../app/store/hooks'
import {
  useReplayWebhookDelivery,
  useSyncHealth,
  useWebhookDelivery,
  useWebhookDeliveries,
} from '../../../features/event-health/useEventHealth'
import { EventHealthPanel } from './EventHealthPanel'

vi.mock('../../../app/store/hooks', () => ({ useAppSelector: vi.fn() }))
vi.mock('../../../features/event-health/useEventHealth', () => ({
  useWebhookDeliveries: vi.fn(),
  useWebhookDelivery: vi.fn(),
  useSyncHealth: vi.fn(),
  useReplayWebhookDelivery: vi.fn(),
}))

const mockedSelector = vi.mocked(useAppSelector)
const mockedDeliveries = vi.mocked(useWebhookDeliveries)
const mockedDelivery = vi.mocked(useWebhookDelivery)
const mockedSyncHealth = vi.mocked(useSyncHealth)
const mockedReplay = vi.mocked(useReplayWebhookDelivery)
const mutateAsync = vi.fn()

describe('EventHealthPanel', () => {
  beforeEach(() => {
    mutateAsync.mockReset()
    mutateAsync.mockResolvedValue(undefined)
    mockedSelector.mockImplementation((selector) =>
      selector({ auth: { activeOrganizationId: 7 } } as never),
    )
    mockedDelivery.mockReturnValue({ data: undefined } as never)
    mockedSyncHealth.mockReturnValue({ data: undefined } as never)
    mockedReplay.mockReturnValue({
      mutateAsync,
      isPending: false,
      isError: false,
      error: null,
    } as never)
  })

  it('lists deliveries and offers replay only for retryable/dead-lettered ones', () => {
    mockedDeliveries.mockReturnValue({
      data: {
        data: [
          {
            id: 1,
            github_delivery_id: 'delivery-1',
            event_name: 'pull_request',
            action_name: 'opened',
            status: 'processed',
            repository_id: null,
            error_category: null,
            error_summary: null,
            received_at: '2026-07-01T00:00:00Z',
            queued_at: null,
            processed_at: '2026-07-01T00:00:01Z',
          },
          {
            id: 2,
            github_delivery_id: 'delivery-2',
            event_name: 'pull_request',
            action_name: 'opened',
            status: 'dead_lettered',
            repository_id: null,
            error_category: 'validation',
            error_summary: 'bad payload',
            received_at: '2026-07-01T00:00:00Z',
            queued_at: null,
            processed_at: null,
          },
        ],
        meta: { current_page: 1, last_page: 1, per_page: 25, total: 2 },
      },
      isLoading: false,
      isError: false,
    } as never)

    render(<EventHealthPanel />)

    expect(screen.getAllByText(/pull_request\.opened/)).toHaveLength(2)
    expect(screen.getAllByRole('button', { name: 'Replay' })).toHaveLength(1)
  })

  it('replays a dead-lettered delivery', async () => {
    mockedDeliveries.mockReturnValue({
      data: {
        data: [
          {
            id: 2,
            github_delivery_id: 'delivery-2',
            event_name: 'pull_request',
            action_name: 'opened',
            status: 'dead_lettered',
            repository_id: null,
            error_category: 'validation',
            error_summary: 'bad payload',
            received_at: '2026-07-01T00:00:00Z',
            queued_at: null,
            processed_at: null,
          },
        ],
        meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
      },
      isLoading: false,
      isError: false,
    } as never)

    const user = userEvent.setup()
    render(<EventHealthPanel />)

    await user.click(screen.getByRole('button', { name: 'Replay' }))

    expect(mutateAsync).toHaveBeenCalledWith(2)
  })

  it('shows sync health indicators and per-repository status', () => {
    mockedDeliveries.mockReturnValue({
      data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 } },
      isLoading: false,
      isError: false,
    } as never)
    mockedSyncHealth.mockReturnValue({
      data: {
        last_delivery_received_at: '2026-07-01T00:00:00Z',
        dead_letter_count: 2,
        failure_rate: 0.25,
        failure_rate_sample_size: 8,
        average_processing_lag_seconds: 5,
        last_successful_reconciliation_at: '2026-07-01T01:00:00Z',
        reconciliation_corrections: 3,
        repositories: [
          {
            repository_id: 1,
            full_name: 'acme/widgets',
            last_delivery_received_at: null,
            dead_letter_count: 0,
            status: 'unknown',
          },
        ],
      },
    } as never)

    render(<EventHealthPanel />)

    expect(screen.getByText('25%')).toBeInTheDocument()
    expect(screen.getByText('acme/widgets: Unknown')).toBeInTheDocument()
  })

  it('renders nothing when the query errors (feature disabled or unavailable)', () => {
    mockedDeliveries.mockReturnValue({
      data: undefined,
      isLoading: false,
      isError: true,
    } as never)

    const { container } = render(<EventHealthPanel />)

    expect(container).toBeEmptyDOMElement()
  })
})
