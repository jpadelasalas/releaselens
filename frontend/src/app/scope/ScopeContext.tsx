import {
  type ReactNode,
  useMemo,
  useState,
} from 'react'
import { browserScopeStorage } from './scopeStorage'
import {
  ScopeContext,
  type ScopeContextValue,
} from './scopeContextInstance'
import {
  type AppScope,
  type ScopeStorage,
  anonymousScope,
  createDemoScope,
} from './scopeTypes'

type ScopeProviderProps = {
  children: ReactNode
  initialScope?: AppScope
  storage?: ScopeStorage
}

export function ScopeProvider({
  children,
  initialScope,
  storage = browserScopeStorage,
}: ScopeProviderProps) {
  const [scope, setScope] = useState<AppScope>(
    () => initialScope ?? storage.read() ?? anonymousScope,
  )

  const value = useMemo<ScopeContextValue>(
    () => ({
      scope,
      activateDemoSession(demoSession) {
        const demoScope = createDemoScope(demoSession)

        storage.write(demoScope)
        setScope(demoScope)
      },
      clearScope() {
        storage.clear()
        setScope(anonymousScope)
      },
    }),
    [scope, storage],
  )

  return <ScopeContext.Provider value={value}>{children}</ScopeContext.Provider>
}
