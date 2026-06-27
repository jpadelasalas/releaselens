import { describe, expect, it } from 'vitest'
import { authReducer, authenticated, signedOut } from './authSlice'

describe('authSlice', () => {
  it('stores and clears the authenticated user', () => {
    const session = {
      user: {
        id: 1,
        name: 'Alex Rivera',
        email: 'alex@example.com',
        timezone: 'UTC',
      },
      memberships: [],
      active_organization_id: null,
    }

    const authenticatedState = authReducer(undefined, authenticated(session))

    expect(authenticatedState).toEqual({
      user: session.user,
      memberships: [],
      activeOrganizationId: null,
      status: 'authenticated',
    })

    expect(authReducer(authenticatedState, signedOut())).toEqual({
      user: null,
      memberships: [],
      activeOrganizationId: null,
      status: 'anonymous',
    })
  })
})
