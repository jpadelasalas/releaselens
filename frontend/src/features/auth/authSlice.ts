import { createSlice, type PayloadAction } from '@reduxjs/toolkit'
import type { AuthSession, AuthUser } from './authSchemas'

type AuthState = {
  user: AuthUser | null
  memberships: AuthSession['memberships']
  activeOrganizationId: number | null
  status: 'checking' | 'anonymous' | 'authenticated'
}

const initialState: AuthState = {
  user: null,
  memberships: [],
  activeOrganizationId: null,
  status: 'checking',
}

const authSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    authenticated(state, action: PayloadAction<AuthSession>) {
      state.user = action.payload.user
      state.memberships = action.payload.memberships
      state.activeOrganizationId = action.payload.active_organization_id
      state.status = 'authenticated'
    },
    signedOut(state) {
      state.user = null
      state.memberships = []
      state.activeOrganizationId = null
      state.status = 'anonymous'
    },
  },
})

export const { authenticated, signedOut } = authSlice.actions
export const authReducer = authSlice.reducer
