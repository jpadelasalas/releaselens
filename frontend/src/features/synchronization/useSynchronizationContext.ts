import { useContext } from 'react'

import { SynchronizationContext } from './synchronizationContextInstance'

export function useSynchronizationContext() {
  const context = useContext(SynchronizationContext)

  if (!context) {
    throw new Error(
      'Synchronization hooks must be used within SynchronizationProvider.',
    )
  }

  return context
}
