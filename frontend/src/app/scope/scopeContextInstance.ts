import { createContext } from 'react'
import type { DemoSession } from '../../features/demo-session/demoSessionApi'
import type { AppScope } from './scopeTypes'

export type ScopeContextValue = {
  scope: AppScope
  activateDemoSession: (demoSession: DemoSession) => void
  clearScope: () => void
}

export const ScopeContext = createContext<ScopeContextValue | null>(null)
