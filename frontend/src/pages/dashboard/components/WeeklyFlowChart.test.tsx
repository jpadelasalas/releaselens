import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { WeeklyFlowChart } from './WeeklyFlowChart'

describe('WeeklyFlowChart', () => {
  it('shows an empty state when no weekly activity matches', () => {
    render(
      <MemoryRouter>
        <WeeklyFlowChart series={[]} />
      </MemoryRouter>,
    )

    expect(
      screen.getByText('No weekly activity matches the active filters.'),
    ).toBeInTheDocument()
  })

  it('shows a loading state while analytics are pending', () => {
    render(
      <MemoryRouter>
        <WeeklyFlowChart series={[]} isLoading />
      </MemoryRouter>,
    )

    expect(
      screen.getByLabelText('Loading opened versus merged chart'),
    ).toBeInTheDocument()
  })
})
