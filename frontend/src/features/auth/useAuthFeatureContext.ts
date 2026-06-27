import { useContext } from 'react'

import { AuthFeatureContext } from './authContextInstance'

export function useAuthFeatureContext() {
  const context = useContext(AuthFeatureContext)

  if (!context) {
    throw new Error('Auth hooks must be used within AuthFeatureProvider.')
  }

  return context
}
