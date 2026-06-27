import { createContext } from 'react'

import type {
  AddOrganizationMemberValues,
  CreateOrganizationValues,
  OrganizationMember,
  OrganizationRole,
} from './organizationSchemas'

export type OrganizationFeatureContextValue = {
  createWorkspace: (values: CreateOrganizationValues) => Promise<void>
  activateWorkspace: (organizationId: number) => Promise<void>
  addMember: (values: AddOrganizationMemberValues) => Promise<void>
  changeMemberRole: (
    membershipId: number,
    role: OrganizationRole,
  ) => Promise<void>
  removeMember: (membershipId: number) => Promise<void>
  clearError: () => void
  clearMemberError: () => void
  error: string | null
  memberError: string | null
  members: OrganizationMember[]
  canManageMembers: boolean
  isLoadingMembers: boolean
  isSubmitting: boolean
}

export const OrganizationFeatureContext =
  createContext<OrganizationFeatureContextValue | null>(null)
