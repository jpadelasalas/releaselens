import { createContext } from 'react'
import type { DemoSession } from '../../features/demo-session/demoSessionApi'
import type { AuthSession } from '../../features/auth/authSchemas'
import type { AppScope } from './scopeTypes'

export type ScopeContextValue = {
  scope: AppScope
  activateDemoSession: (demoSession: DemoSession) => void
  activateConnectedWorkspace: (
    membership: AuthSession['memberships'][number],
  ) => void
  clearScope: () => void
}

export const ScopeContext = createContext<ScopeContextValue | null>(null)
