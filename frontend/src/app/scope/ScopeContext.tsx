import {
  type ReactNode,
  useCallback,
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
  const activateDemoSession = useCallback(
    (demoSession: Parameters<ScopeContextValue['activateDemoSession']>[0]) => {
      const demoScope = createDemoScope(demoSession)

      storage.write(demoScope)
      setScope(demoScope)
    },
    [storage],
  )
  const clearScope = useCallback(() => {
    storage.clear()
    setScope(anonymousScope)
  }, [storage])

  const value = useMemo<ScopeContextValue>(
    () => ({
      scope,
      activateDemoSession,
      clearScope,
    }),
    [activateDemoSession, clearScope, scope],
  )

  return <ScopeContext.Provider value={value}>{children}</ScopeContext.Provider>
}
