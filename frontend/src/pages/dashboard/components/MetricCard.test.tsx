import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { MetricCard } from './MetricCard'

describe('MetricCard', () => {
  it('renders its metric and supporting detail', () => {
    render(
      <MetricCard
        label="Waiting for review"
        value="6"
        detail="Open pull requests without a qualifying review."
      />,
    )

    expect(screen.getByText('Waiting for review')).toBeInTheDocument()
    expect(screen.getByText('6')).toBeInTheDocument()
    expect(
      screen.getByText('Open pull requests without a qualifying review.'),
    ).toBeInTheDocument()
  })
})
