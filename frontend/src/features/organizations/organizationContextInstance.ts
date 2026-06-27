import { createContext } from 'react'

import type { CreateOrganizationValues } from './organizationSchemas'

export type OrganizationFeatureContextValue = {
  createWorkspace: (values: CreateOrganizationValues) => Promise<void>
  activateWorkspace: (organizationId: number) => Promise<void>
  clearError: () => void
  error: string | null
  isSubmitting: boolean
}

export const OrganizationFeatureContext =
  createContext<OrganizationFeatureContextValue | null>(null)
