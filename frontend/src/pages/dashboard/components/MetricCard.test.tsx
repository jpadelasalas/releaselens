import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { MetricCard } from './MetricCard'

describe('MetricCard', () => {
  it('renders its metric and supporting detail', () => {
    render(
      <MemoryRouter>
        <MetricCard
          label="Waiting for review"
          value="6"
          detail="Open pull requests without a qualifying review."
          to="/demo/pull-requests?review_status=waiting"
          definitionTo="/demo/metrics#waiting-for-review"
        />
      </MemoryRouter>,
    )

    expect(screen.getByText('Waiting for review')).toBeInTheDocument()
    expect(screen.getByText('6')).toBeInTheDocument()
    expect(
      screen.getByText('Open pull requests without a qualifying review.'),
    ).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /definition/i })).toHaveAttribute(
      'href',
      '/demo/metrics#waiting-for-review',
    )
    expect(
      screen.getByRole('link', { name: /view supporting records/i }),
    ).toHaveAttribute('href', '/demo/pull-requests?review_status=waiting')
  })
})
