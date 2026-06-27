import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it } from 'vitest'

import { ScopeProvider } from '../../app/scope/ScopeContext'
import { anonymousScope, type DemoScope } from '../../app/scope/scopeTypes'
import { ThemeProvider } from '../../app/theme/ThemeProvider'
import { MetricGlossaryPage } from './MetricGlossaryPage'

const demoScope: DemoScope = {
  kind: 'demo',
  sessionId: 'demo-session',
  readOnly: true,
  organization: {
    id: 1,
    name: 'Northstar Engineering',
    slug: 'northstar-engineering',
    timezone: 'UTC',
    is_demo: true,
  },
  capabilities: {
    can_read_analytics: true,
    can_mutate_demo: false,
    can_connect_github: false,
  },
  demo: {
    anchor_date: '2026-06-19T12:00:00Z',
  },
}

describe('MetricGlossaryPage', () => {
  it('renders explainable definitions for a demo workspace', () => {
    renderGlossary(demoScope)

    expect(
      screen.getByRole('heading', { level: 1, name: 'Metric glossary' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('heading', { name: 'Median time to first review' }),
    ).toBeInTheDocument()
    expect(
      screen.getByText(/should never be used to rank developers/i),
    ).toBeInTheDocument()
    expect(screen.getAllByText('Formula')).toHaveLength(8)
  })

  it('requires a demo session', () => {
    renderGlossary(anonymousScope)

    expect(
      screen.getByRole('heading', { name: 'Open the demo workspace first.' }),
    ).toBeInTheDocument()
  })
})

function renderGlossary(initialScope: DemoScope | typeof anonymousScope) {
  return render(
    <MemoryRouter initialEntries={['/demo/metrics']}>
      <ThemeProvider initialThemeMode="light">
        <ScopeProvider initialScope={initialScope}>
          <MetricGlossaryPage />
        </ScopeProvider>
      </ThemeProvider>
    </MemoryRouter>,
  )
}
