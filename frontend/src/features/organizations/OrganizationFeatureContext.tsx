import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useCallback, useMemo, type PropsWithChildren } from 'react'

import { useScopeContext } from '../../app/scope/useScopeContext'
import { getAuthenticationError, getCurrentSession } from '../auth/authApi'
import { useApplyAuthSession } from '../auth/useApplyAuthSession'
import {
  activateOrganization,
  addOrganizationMember,
  createOrganization,
  removeOrganizationMember,
  updateOrganizationMemberRole,
} from './organizationApi'
import { OrganizationFeatureContext } from './organizationContextInstance'
import type {
  AddOrganizationMemberValues,
  CreateOrganizationValues,
  OrganizationRole,
} from './organizationSchemas'
import { useOrganizationMembers } from './useOrganizationMembers'

export function OrganizationFeatureProvider({ children }: PropsWithChildren) {
  const applyAuthSession = useApplyAuthSession()
  const queryClient = useQueryClient()
  const { scope } = useScopeContext()
  const organizationId = scope.kind === 'connected' ? scope.organization.id : null
  const canManageMembers = scope.kind === 'connected' && scope.role === 'owner'
  const membersQuery = useOrganizationMembers(
    organizationId,
    canManageMembers,
  )
  const createMutation = useMutation({ mutationFn: createOrganization })
  const activateMutation = useMutation({ mutationFn: activateOrganization })
  const addMemberMutation = useMutation({
    mutationFn: (values: AddOrganizationMemberValues) => {
      if (organizationId === null) {
        throw new Error('An active organization is required.')
      }

      return addOrganizationMember(organizationId, values)
    },
  })
  const changeRoleMutation = useMutation({
    mutationFn: ({
      membershipId,
      role,
    }: {
      membershipId: number
      role: OrganizationRole
    }) => {
      if (organizationId === null) {
        throw new Error('An active organization is required.')
      }

      return updateOrganizationMemberRole(organizationId, membershipId, role)
    },
  })
  const removeMemberMutation = useMutation({
    mutationFn: (membershipId: number) => {
      if (organizationId === null) {
        throw new Error('An active organization is required.')
      }

      return removeOrganizationMember(organizationId, membershipId)
    },
  })

  const refreshMembershipState = useCallback(async () => {
    await queryClient.invalidateQueries({
      queryKey: ['organization-members', organizationId],
    })
    applyAuthSession(await getCurrentSession())
  }, [applyAuthSession, organizationId, queryClient])

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
  const addMember = useCallback(
    async (values: AddOrganizationMemberValues) => {
      await addMemberMutation.mutateAsync(values)
      await refreshMembershipState()
    },
    [addMemberMutation, refreshMembershipState],
  )
  const changeMemberRole = useCallback(
    async (membershipId: number, role: OrganizationRole) => {
      await changeRoleMutation.mutateAsync({ membershipId, role })
      await refreshMembershipState()
    },
    [changeRoleMutation, refreshMembershipState],
  )
  const removeMember = useCallback(
    async (membershipId: number) => {
      await removeMemberMutation.mutateAsync(membershipId)
      await refreshMembershipState()
    },
    [refreshMembershipState, removeMemberMutation],
  )
  const clearMemberError = useCallback(() => {
    addMemberMutation.reset()
    changeRoleMutation.reset()
    removeMemberMutation.reset()
  }, [addMemberMutation, changeRoleMutation, removeMemberMutation])
  const mutationError = createMutation.error ?? activateMutation.error
  const memberMutationError =
    addMemberMutation.error ??
    changeRoleMutation.error ??
    removeMemberMutation.error
  const value = useMemo(
    () => ({
      createWorkspace,
      activateWorkspace,
      addMember,
      changeMemberRole,
      removeMember,
      clearError,
      clearMemberError,
      error: mutationError ? getAuthenticationError(mutationError) : null,
      memberError: memberMutationError
        ? getAuthenticationError(memberMutationError)
        : null,
      members: membersQuery.data ?? [],
      canManageMembers,
      isLoadingMembers: membersQuery.isLoading,
      isSubmitting:
        createMutation.isPending ||
        activateMutation.isPending ||
        addMemberMutation.isPending ||
        changeRoleMutation.isPending ||
        removeMemberMutation.isPending,
    }),
    [
      activateMutation.isPending,
      activateWorkspace,
      addMember,
      addMemberMutation.isPending,
      canManageMembers,
      changeMemberRole,
      changeRoleMutation.isPending,
      clearError,
      clearMemberError,
      createMutation.isPending,
      createWorkspace,
      memberMutationError,
      membersQuery.data,
      membersQuery.isLoading,
      mutationError,
      removeMember,
      removeMemberMutation.isPending,
    ],
  )

  return (
    <OrganizationFeatureContext.Provider value={value}>
      {children}
    </OrganizationFeatureContext.Provider>
  )
}
