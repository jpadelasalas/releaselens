import type { ReactNode } from 'react'
import { ScopeProvider } from '../scope/ScopeContext'
import { ThemeProvider } from '../theme/ThemeProvider'

type AppProvidersProps = {
  children: ReactNode
}

export function AppProviders({ children }: AppProvidersProps) {
  return (
    <ThemeProvider>
      <ScopeProvider>{children}</ScopeProvider>
    </ThemeProvider>
  )
}
