import { useContext } from 'react'
import {
  ScopeContext,
  type ScopeContextValue,
} from './scopeContextInstance'

export function useScopeContext(): ScopeContextValue {
  const context = useContext(ScopeContext)

  if (context === null) {
    throw new Error('useScopeContext must be used within ScopeProvider.')
  }

  return context
}
