import { useMutation } from '@tanstack/react-query'
import { useCallback, useMemo, type PropsWithChildren } from 'react'

import { useScopeContext } from '../../app/scope/useScopeContext'
import { useAppDispatch } from '../../app/store/hooks'
import {
  getAuthenticationError,
  registerAccount,
  signIn as signInRequest,
  signOut as signOutRequest,
} from './authApi'
import { AuthFeatureContext } from './authContextInstance'
import type { RegisterValues, SignInValues } from './authSchemas'
import { authenticated, signedOut } from './authSlice'

export function AuthFeatureProvider({ children }: PropsWithChildren) {
  const dispatch = useAppDispatch()
  const { clearScope } = useScopeContext()
  const signInMutation = useMutation({ mutationFn: signInRequest })
  const registerMutation = useMutation({ mutationFn: registerAccount })
  const signOutMutation = useMutation({ mutationFn: signOutRequest })

  const signIn = useCallback(
    async (values: SignInValues) => {
      const session = await signInMutation.mutateAsync(values)
      clearScope()
      dispatch(authenticated(session))
      return session
    },
    [clearScope, dispatch, signInMutation],
  )
  const register = useCallback(
    async (values: RegisterValues) => {
      const session = await registerMutation.mutateAsync(values)
      clearScope()
      dispatch(authenticated(session))
      return session
    },
    [clearScope, dispatch, registerMutation],
  )
  const signOut = useCallback(async () => {
    await signOutMutation.mutateAsync()
    dispatch(signedOut())
  }, [dispatch, signOutMutation])
  const clearError = useCallback(() => {
    signInMutation.reset()
    registerMutation.reset()
    signOutMutation.reset()
  }, [registerMutation, signInMutation, signOutMutation])
  const mutationError =
    signInMutation.error ?? registerMutation.error ?? signOutMutation.error

  const value = useMemo(
    () => ({
      signIn,
      register,
      signOut,
      clearError,
      error: mutationError ? getAuthenticationError(mutationError) : null,
      isSubmitting:
        signInMutation.isPending ||
        registerMutation.isPending ||
        signOutMutation.isPending,
    }),
    [
      clearError,
      mutationError,
      register,
      registerMutation.isPending,
      signIn,
      signInMutation.isPending,
      signOut,
      signOutMutation.isPending,
    ],
  )

  return (
    <AuthFeatureContext.Provider value={value}>
      {children}
    </AuthFeatureContext.Provider>
  )
}
