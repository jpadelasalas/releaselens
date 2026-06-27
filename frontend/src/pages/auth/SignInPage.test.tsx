import { configureStore } from '@reduxjs/toolkit'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { HttpResponse, http } from 'msw'
import { Provider as ReduxProvider } from 'react-redux'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { describe, expect, it } from 'vitest'

import { ScopeProvider } from '../../app/scope/ScopeContext'
import { createMemoryScopeStorage } from '../../app/scope/scopeStorage'
import { ThemeProvider } from '../../app/theme/ThemeProvider'
import { AuthFeatureProvider } from '../../features/auth/AuthFeatureContext'
import { authReducer } from '../../features/auth/authSlice'
import { server } from '../../test/server'
import { SignInPage } from './SignInPage'

describe('SignInPage', () => {
  it('creates a session and enters the connected workspace', async () => {
    const user = userEvent.setup()
    const store = configureStore({
      reducer: { auth: authReducer },
      preloadedState: {
        auth: {
          user: null,
          memberships: [],
          activeOrganizationId: null,
          status: 'anonymous' as const,
        },
      },
    })
    const queryClient = new QueryClient({
      defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
    })

    server.use(
      http.get('*/api/v1/auth/csrf-cookie', () =>
        new HttpResponse(null, { status: 204 }),
      ),
      http.post('*/api/v1/auth/login', () =>
        HttpResponse.json({
          data: {
            user: {
              id: 1,
              name: 'Alex Rivera',
              email: 'alex@example.com',
              timezone: 'UTC',
            },
            memberships: [],
            active_organization_id: null,
          },
        }),
      ),
    )

    render(
      <ReduxProvider store={store}>
        <QueryClientProvider client={queryClient}>
          <ThemeProvider initialThemeMode="light">
            <ScopeProvider storage={createMemoryScopeStorage()}>
              <AuthFeatureProvider>
                <MemoryRouter initialEntries={['/sign-in']}>
                  <Routes>
                    <Route path="/sign-in" element={<SignInPage />} />
                    <Route path="/app" element={<h1>Connected workspace</h1>} />
                  </Routes>
                </MemoryRouter>
              </AuthFeatureProvider>
            </ScopeProvider>
          </ThemeProvider>
        </QueryClientProvider>
      </ReduxProvider>,
    )

    await user.type(screen.getByLabelText('Email address'), 'alex@example.com')
    await user.type(screen.getByLabelText('Password'), 'release-lens-2026')
    await user.click(screen.getByRole('button', { name: 'Sign In' }))

    expect(
      await screen.findByRole('heading', { name: 'Connected workspace' }),
    ).toBeInTheDocument()
    expect(store.getState().auth).toMatchObject({
      status: 'authenticated',
      user: { email: 'alex@example.com' },
    })
  })
})
