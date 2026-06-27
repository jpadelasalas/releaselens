import { describe, expect, it } from 'vitest'
import { authReducer, authenticated, signedOut } from './authSlice'

describe('authSlice', () => {
  it('stores and clears the authenticated user', () => {
    const user = {
      id: 1,
      name: 'Alex Rivera',
      email: 'alex@example.com',
    }

    const authenticatedState = authReducer(undefined, authenticated(user))

    expect(authenticatedState).toEqual({
      user,
      status: 'authenticated',
    })

    expect(authReducer(authenticatedState, signedOut())).toEqual({
      user: null,
      status: 'anonymous',
    })
  })
})
