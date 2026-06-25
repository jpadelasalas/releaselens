import { createBrowserRouter, Outlet } from 'react-router-dom'
import { AppProviders } from './providers/AppProviders'
import { SignInPage } from '../pages/auth/SignInPage'
import { DemoDashboardPage } from '../pages/dashboard/DemoDashboardPage'
import { LandingPage } from '../pages/landing/LandingPage'

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
        path: '/demo/dashboard',
        element: <DemoDashboardPage />,
      },
      {
        path: '/sign-in',
        element: <SignInPage />,
      },
    ],
  },
])
