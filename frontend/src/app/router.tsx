import { createBrowserRouter, Outlet } from 'react-router-dom'
import { DemoLayout } from './layouts/DemoLayout'
import { AppProviders } from './providers/AppProviders'
import { DemoDashboardRoute } from '../pages/dashboard/DemoDashboardRoute'
import { LandingPage } from '../pages/landing/LandingPage'
import { MetricGlossaryRoute } from '../pages/metrics/MetricGlossaryRoute'
import { DemoPullRequestExplorerRoute } from '../pages/pull-requests/DemoPullRequestExplorerRoute'

export const router = createBrowserRouter([
  {
    element: (
      <AppProviders>
        <Outlet />
      </AppProviders>
    ),
    children: [
      {
        path: '/',
        element: <LandingPage />,
      },
      {
        path: '/demo',
        element: <DemoLayout />,
        children: [
          {
            path: 'dashboard',
            element: <DemoDashboardRoute />,
          },
          {
            path: 'pull-requests',
            element: <DemoPullRequestExplorerRoute />,
          },
          {
            path: 'metrics',
            element: <MetricGlossaryRoute />,
          },
        ],
      },
      {
        lazy: async () => {
          const { AuthFeatureRoute, AuthRouteFallback } = await import(
            '../features/auth/AuthFeatureRoute'
          )

          return {
            Component: AuthFeatureRoute,
            HydrateFallback: AuthRouteFallback,
          }
        },
        children: [
          {
            path: '/sign-in',
            lazy: async () => {
              const { SignInPage } = await import('../pages/auth/SignInPage')

              return { Component: SignInPage }
            },
          },
          {
            path: '/register',
            lazy: async () => {
              const { RegisterPage } = await import('../pages/auth/RegisterPage')

              return { Component: RegisterPage }
            },
          },
          {
            lazy: async () => {
              const { ProtectedRoute } = await import(
                '../features/auth/ProtectedRoute'
              )

              return { Component: ProtectedRoute }
            },
            children: [
              {
                path: '/app',
                lazy: async () => {
                  const { ConnectedWorkspacePage } = await import(
                    '../pages/workspace/ConnectedWorkspacePage'
                  )

                  return { Component: ConnectedWorkspacePage }
                },
              },
            ],
          },
        ],
      },
    ],
  },
])
