import { configureStore } from '@reduxjs/toolkit'
import { HttpResponse, http } from 'msw'
import { Provider as ReduxProvider } from 'react-redux'
import { useScopeContext } from '../../app/scope/useScopeContext'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { describe, expect, it } from 'vitest'

import { ScopeProvider } from '../../app/scope/ScopeContext'
import { createMemoryScopeStorage } from '../../app/scope/scopeStorage'
import { ThemeProvider } from '../../app/theme/ThemeProvider'
import { authReducer } from '../../features/auth/authSlice'
import { server } from '../../test/server'
import { LandingPage } from './LandingPage'

function createAuthStore(status: 'checking' | 'anonymous' | 'authenticated' = 'anonymous') {
  return configureStore({
    reducer: { auth: authReducer },
    preloadedState: {
      auth: { user: null, memberships: [], activeOrganizationId: null, status },
    },
  })
}

describe('LandingPage demo entry', () => {
  it('opens the populated demo route in one action without authentication', async () => {
    const user = userEvent.setup()

    server.use(
      http.post('*/api/v1/demo/session', () =>
        HttpResponse.json({
          data: {
            session: {
              type: 'demo',
              id: 'demo-session-id',
              read_only: true,
            },
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
          },
        }),
      ),
    )

    render(
      <ReduxProvider store={createAuthStore()}>
        <MemoryRouter initialEntries={['/']}>
          <ThemeProvider initialThemeMode="light">
            <ScopeProvider storage={createMemoryScopeStorage()}>
              <Routes>
                <Route path="/" element={<LandingPage />} />
                <Route path="/demo/dashboard" element={<DemoDestination />} />
              </Routes>
            </ScopeProvider>
          </ThemeProvider>
        </MemoryRouter>
      </ReduxProvider>,
    )

    await user.click(
      screen.getByRole('button', { name: 'View Demo Workspace' }),
    )

    expect(
      await screen.findByRole('heading', { name: 'Demo dashboard reached' }),
    ).toBeInTheDocument()
    expect(screen.getByText('Northstar Engineering')).toBeInTheDocument()
    expect(screen.getByText('Read-only session')).toBeInTheDocument()
  })

  it('redirects an authenticated visitor straight to the connected dashboard', () => {
    render(
      <ReduxProvider store={createAuthStore('authenticated')}>
        <MemoryRouter initialEntries={['/']}>
          <ThemeProvider initialThemeMode="light">
            <ScopeProvider storage={createMemoryScopeStorage()}>
              <Routes>
                <Route path="/" element={<LandingPage />} />
                <Route path="/app/dashboard" element={<h1>Connected dashboard reached</h1>} />
              </Routes>
            </ScopeProvider>
          </ThemeProvider>
        </MemoryRouter>
      </ReduxProvider>,
    )

    expect(
      screen.getByRole('heading', { name: 'Connected dashboard reached' }),
    ).toBeInTheDocument()
  })
})

function DemoDestination() {
  const { scope } = useScopeContext()

  return (
    <main>
      <h1>Demo dashboard reached</h1>
      <p>{scope.kind === 'demo' ? scope.organization.name : 'No workspace'}</p>
      <p>{scope.kind === 'demo' && scope.readOnly ? 'Read-only session' : 'Mutable'}</p>
    </main>
  )
}
