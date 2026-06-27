import { createSlice, type PayloadAction } from '@reduxjs/toolkit'

export type AuthUser = {
  id: number
  name: string
  email: string
}

type AuthState = {
  user: AuthUser | null
  status: 'anonymous' | 'authenticated'
}

const initialState: AuthState = {
  user: null,
  status: 'anonymous',
}

const authSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    authenticated(state, action: PayloadAction<AuthUser>) {
      state.user = action.payload
      state.status = 'authenticated'
    },
    signedOut(state) {
      state.user = null
      state.status = 'anonymous'
    },
  },
})

export const { authenticated, signedOut } = authSlice.actions
export const authReducer = authSlice.reducer
