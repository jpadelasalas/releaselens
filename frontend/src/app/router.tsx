import { createBrowserRouter, Outlet } from 'react-router-dom'
import { ScopeProvider } from './scope/ScopeContext'
import { ThemeProvider } from './theme/ThemeProvider'
import { SignInPage } from '../pages/auth/SignInPage'
import { DemoDashboardPage } from '../pages/dashboard/DemoDashboardPage'
import { LandingPage } from '../pages/landing/LandingPage'

export const router = createBrowserRouter([
  {
    element: (
      <ThemeProvider>
        <ScopeProvider>
          <Outlet />
        </ScopeProvider>
      </ThemeProvider>
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
