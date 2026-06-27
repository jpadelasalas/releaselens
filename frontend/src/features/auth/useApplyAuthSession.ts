import { useCallback } from 'react'

import { useScopeContext } from '../../app/scope/useScopeContext'
import { useAppDispatch } from '../../app/store/hooks'
import type { AuthSession } from './authSchemas'
import { authenticated } from './authSlice'

export function useApplyAuthSession() {
  const dispatch = useAppDispatch()
  const { activateConnectedWorkspace, clearScope } = useScopeContext()

  return useCallback(
    (session: AuthSession) => {
      dispatch(authenticated(session))

      const activeMembership = session.memberships.find(
        (membership) =>
          membership.organization.id === session.active_organization_id,
      )

      if (activeMembership) {
        activateConnectedWorkspace(activeMembership)
      } else {
        clearScope()
      }
    },
    [activateConnectedWorkspace, clearScope, dispatch],
  )
}
