import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { DashboardLoadingSkeleton } from './DashboardLoadingSkeleton'

describe('DashboardLoadingSkeleton', () => {
  it('announces that dashboard analytics are loading', () => {
    render(<DashboardLoadingSkeleton />)

    expect(screen.getByLabelText('Loading dashboard analytics')).toHaveAttribute(
      'aria-busy',
      'true',
    )
  })
})
