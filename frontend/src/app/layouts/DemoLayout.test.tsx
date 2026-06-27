import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { describe, expect, it } from 'vitest'

import { ScopeProvider } from '../scope/ScopeContext'
import { anonymousScope, type DemoScope } from '../scope/scopeTypes'
import { DemoLayout } from './DemoLayout'

const demoScope: DemoScope = {
  kind: 'demo',
  sessionId: 'demo-session-id',
  readOnly: true,
  organization: {
    id: 1,
    name: 'Northstar Engineering',
    slug: 'northstar-engineering',
    timezone: 'Asia/Manila',
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

describe('DemoLayout', () => {
  it.each([
    '/demo/dashboard',
    '/demo/pull-requests',
    '/demo/metrics',
  ])('keeps the synthetic read-only banner on %s', (route) => {
    renderDemoRoute(route, demoScope)

    expect(
      screen.getByRole('complementary', { name: 'Demo workspace notice' }),
    ).toHaveTextContent(
      'All data is synthetic. This workspace is read-only, and no real organization or developer is represented.',
    )
  })

  it('does not show the banner outside an active demo scope', () => {
    renderDemoRoute('/demo/dashboard', anonymousScope)

    expect(
      screen.queryByRole('complementary', { name: 'Demo workspace notice' }),
    ).not.toBeInTheDocument()
  })
})

function renderDemoRoute(
  route: string,
  initialScope: DemoScope | typeof anonymousScope,
) {
  return render(
    <MemoryRouter initialEntries={[route]}>
      <ScopeProvider initialScope={initialScope}>
        <Routes>
          <Route path="/demo" element={<DemoLayout />}>
            <Route path="dashboard" element={<div>Dashboard</div>} />
            <Route path="pull-requests" element={<div>Pull requests</div>} />
            <Route path="metrics" element={<div>Metric glossary</div>} />
          </Route>
        </Routes>
      </ScopeProvider>
    </MemoryRouter>,
  )
}
