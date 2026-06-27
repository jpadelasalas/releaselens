import { useMutation } from '@tanstack/react-query'
import { useCallback, useMemo, type PropsWithChildren } from 'react'

import { getAuthenticationError } from '../auth/authApi'
import { useApplyAuthSession } from '../auth/useApplyAuthSession'
import {
  activateOrganization,
  createOrganization,
} from './organizationApi'
import { OrganizationFeatureContext } from './organizationContextInstance'
import type { CreateOrganizationValues } from './organizationSchemas'

export function OrganizationFeatureProvider({ children }: PropsWithChildren) {
  const applyAuthSession = useApplyAuthSession()
  const createMutation = useMutation({ mutationFn: createOrganization })
  const activateMutation = useMutation({ mutationFn: activateOrganization })

  const createWorkspace = useCallback(
    async (values: CreateOrganizationValues) => {
      const session = await createMutation.mutateAsync(values)
      applyAuthSession(session)
    },
    [applyAuthSession, createMutation],
  )
  const activateWorkspace = useCallback(
    async (organizationId: number) => {
      const session = await activateMutation.mutateAsync(organizationId)
      applyAuthSession(session)
    },
    [activateMutation, applyAuthSession],
  )
  const clearError = useCallback(() => {
    createMutation.reset()
    activateMutation.reset()
  }, [activateMutation, createMutation])
  const mutationError = createMutation.error ?? activateMutation.error
  const value = useMemo(
    () => ({
      createWorkspace,
      activateWorkspace,
      clearError,
      error: mutationError ? getAuthenticationError(mutationError) : null,
      isSubmitting: createMutation.isPending || activateMutation.isPending,
    }),
    [
      activateMutation.isPending,
      activateWorkspace,
      clearError,
      createMutation.isPending,
      createWorkspace,
      mutationError,
    ],
  )

  return (
    <OrganizationFeatureContext.Provider value={value}>
      {children}
    </OrganizationFeatureContext.Provider>
  )
}
