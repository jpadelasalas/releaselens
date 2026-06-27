import { useEffect, type PropsWithChildren } from 'react'

import { useScopeContext } from '../../app/scope/useScopeContext'
import { useAppDispatch } from '../../app/store/hooks'
import { authenticated, signedOut } from './authSlice'

export function AuthSessionBootstrap({ children }: PropsWithChildren) {
  const dispatch = useAppDispatch()
  const { clearScope } = useScopeContext()

  useEffect(() => {
    let active = true

    void import('./authApi')
      .then(({ getCurrentSession }) => getCurrentSession())
      .then((session) => {
        if (active) {
          clearScope()
          dispatch(authenticated(session))
        }
      })
      .catch(() => {
        if (active) {
          dispatch(signedOut())
        }
      })

    return () => {
      active = false
    }
  }, [clearScope, dispatch])

  return children
}
