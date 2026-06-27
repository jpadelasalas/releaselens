import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { DashboardFilters } from './DashboardFilters'

describe('DashboardFilters', () => {
  it('submits repository and date filters in API format', async () => {
    const user = userEvent.setup()
    const onApply = vi.fn()

    render(
      <DashboardFilters
        repositories={[
          {
            id: 7,
            name: 'billing-api',
            full_name: 'northstar/billing-api',
            last_successful_sync_at: null,
          },
        ]}
        initialFilters={{
          repository_ids: [],
          date_from: '2026-05-21T00:00:00Z',
          date_to: '2026-06-19T23:59:59Z',
        }}
        onApply={onApply}
        onClear={vi.fn()}
      />,
    )

    await user.selectOptions(screen.getByLabelText('Repository'), '7')
    await user.click(screen.getByRole('button', { name: 'Apply' }))

    expect(onApply).toHaveBeenCalledWith({
      repository_ids: [7],
      date_from: '2026-05-21T00:00:00Z',
      date_to: '2026-06-19T23:59:59Z',
    })
  })
})
