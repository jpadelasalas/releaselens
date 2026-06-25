import { QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { ScopeProvider } from '../scope/ScopeContext'
import { ThemeProvider } from '../theme/ThemeProvider'
import { queryClient } from './queryClient'

type AppProvidersProps = {
  children: ReactNode
}

export function AppProviders({ children }: AppProvidersProps) {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
        <ScopeProvider>{children}</ScopeProvider>
      </ThemeProvider>
    </QueryClientProvider>
  )
}
