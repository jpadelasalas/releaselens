import { createBrowserRouter, Outlet } from 'react-router-dom'
import { DemoLayout } from './layouts/DemoLayout'
import { AppProviders } from './providers/AppProviders'
import { SignInPage } from '../pages/auth/SignInPage'
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
        path: '/sign-in',
        element: <SignInPage />,
      },
    ],
  },
])
