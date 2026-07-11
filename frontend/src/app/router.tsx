import { createBrowserRouter, Outlet } from 'react-router-dom'
import { DemoLayout } from './layouts/DemoLayout'
import { ConnectedRouteFallback } from './layouts/ConnectedRouteFallback'
import { AppProviders } from './providers/AppProviders'
import { DemoDashboardRoute } from '../pages/dashboard/DemoDashboardRoute'
import { LandingPage } from '../pages/landing/LandingPage'
import { MetricGlossaryRoute } from '../pages/metrics/MetricGlossaryRoute'
import { DemoPullRequestExplorerRoute } from '../pages/pull-requests/DemoPullRequestExplorerRoute'

export const router = createBrowserRouter([
  {
    HydrateFallback: ConnectedRouteFallback,
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

              return {
                Component: SignInPage,
                HydrateFallback: ConnectedRouteFallback,
              }
            },
          },
          {
            path: '/register',
            lazy: async () => {
              const { RegisterPage } = await import('../pages/auth/RegisterPage')

              return {
                Component: RegisterPage,
                HydrateFallback: ConnectedRouteFallback,
              }
            },
          },
          {
            lazy: async () => {
              const { ProtectedRoute } = await import(
                '../features/auth/ProtectedRoute'
              )

              return {
                Component: ProtectedRoute,
                HydrateFallback: ConnectedRouteFallback,
              }
            },
            children: [
              {
                path: '/app',
                lazy: async () => {
                  const { ConnectedWorkspaceRoute } = await import(
                    '../pages/workspace/ConnectedWorkspaceRoute'
                  )

                  return {
                    Component: ConnectedWorkspaceRoute,
                    HydrateFallback: ConnectedRouteFallback,
                  }
                },
              },
              {
                path: '/app/dashboard',
                lazy: async () => {
                  const { ConnectedDashboardRoute } = await import(
                    '../pages/dashboard/ConnectedDashboardRoute'
                  )

                  return {
                    Component: ConnectedDashboardRoute,
                    HydrateFallback: ConnectedRouteFallback,
                  }
                },
              },
              {
                path: '/app/pull-requests',
                lazy: async () => {
                  const { ConnectedPullRequestExplorerRoute } = await import(
                    '../pages/pull-requests/ConnectedPullRequestExplorerRoute'
                  )

                  return {
                    Component: ConnectedPullRequestExplorerRoute,
                    HydrateFallback: ConnectedRouteFallback,
                  }
                },
              },
              {
                path: '/app/metrics',
                element: <MetricGlossaryRoute />,
              },
              {
                path: '/app/releases',
                lazy: async () => {
                  const { ReleasesListPage } = await import(
                    '../pages/releases/ReleasesListPage'
                  )

                  return {
                    Component: ReleasesListPage,
                    HydrateFallback: ConnectedRouteFallback,
                  }
                },
              },
              {
                path: '/app/releases/:releaseId',
                lazy: async () => {
                  const { ReleaseDetailPage } = await import(
                    '../pages/releases/ReleaseDetailPage'
                  )

                  return {
                    Component: ReleaseDetailPage,
                    HydrateFallback: ConnectedRouteFallback,
                  }
                },
              },
              {
                path: '/app/deployments',
                lazy: async () => {
                  const { DeploymentsListPage } = await import(
                    '../pages/deployments/DeploymentsListPage'
                  )

                  return {
                    Component: DeploymentsListPage,
                    HydrateFallback: ConnectedRouteFallback,
                  }
                },
              },
              {
                path: '/app/notifications',
                lazy: async () => {
                  const { NotificationsPage } = await import(
                    '../pages/notifications/NotificationsPage'
                  )

                  return {
                    Component: NotificationsPage,
                    HydrateFallback: ConnectedRouteFallback,
                  }
                },
              },
              {
                path: '/app/incidents',
                lazy: async () => {
                  const { IncidentsListPage } = await import(
                    '../pages/incidents/IncidentsListPage'
                  )

                  return {
                    Component: IncidentsListPage,
                    HydrateFallback: ConnectedRouteFallback,
                  }
                },
              },
              {
                path: '/app/incidents/:incidentId',
                lazy: async () => {
                  const { IncidentDetailPage } = await import(
                    '../pages/incidents/IncidentDetailPage'
                  )

                  return {
                    Component: IncidentDetailPage,
                    HydrateFallback: ConnectedRouteFallback,
                  }
                },
              },
            ],
          },
        ],
      },
    ],
  },
])
