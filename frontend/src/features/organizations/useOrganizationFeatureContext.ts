import { useContext } from 'react'

import { OrganizationFeatureContext } from './organizationContextInstance'

export function useOrganizationFeatureContext() {
  const context = useContext(OrganizationFeatureContext)

  if (!context) {
    throw new Error(
      'Organization hooks must be used within OrganizationFeatureProvider.',
    )
  }

  return context
}
