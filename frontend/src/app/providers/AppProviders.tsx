import { QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { Provider as ReduxProvider } from 'react-redux'
import { AuthSessionBootstrap } from '../../features/auth/AuthSessionBootstrap'
import { ScopeProvider } from '../scope/ScopeContext'
import { store } from '../store/store'
import { ThemeProvider } from '../theme/ThemeProvider'
import { queryClient } from './queryClient'

type AppProvidersProps = {
  children: ReactNode
}

export function AppProviders({ children }: AppProvidersProps) {
  return (
    <ReduxProvider store={store}>
      <QueryClientProvider client={queryClient}>
        <ThemeProvider>
          <ScopeProvider>
            <AuthSessionBootstrap>{children}</AuthSessionBootstrap>
          </ScopeProvider>
        </ThemeProvider>
      </QueryClientProvider>
    </ReduxProvider>
  )
}
