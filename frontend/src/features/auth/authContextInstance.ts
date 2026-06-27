import { createContext } from 'react'

import type {
  AuthSession,
  RegisterValues,
  SignInValues,
} from './authSchemas'

export type AuthFeatureContextValue = {
  signIn: (values: SignInValues) => Promise<AuthSession>
  register: (values: RegisterValues) => Promise<AuthSession>
  signOut: () => Promise<void>
  clearError: () => void
  error: string | null
  isSubmitting: boolean
}

export const AuthFeatureContext = createContext<AuthFeatureContextValue | null>(
  null,
)
