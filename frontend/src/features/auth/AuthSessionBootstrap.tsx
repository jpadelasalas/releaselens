import { useEffect, type PropsWithChildren } from 'react'

import { useAppDispatch } from '../../app/store/hooks'
import { signedOut } from './authSlice'
import { useApplyAuthSession } from './useApplyAuthSession'

export function AuthSessionBootstrap({ children }: PropsWithChildren) {
  const dispatch = useAppDispatch()
  const applyAuthSession = useApplyAuthSession()

  useEffect(() => {
    let active = true

    void import('./authApi')
      .then(({ getCurrentSession }) => getCurrentSession())
      .then((session) => {
        if (active) {
          applyAuthSession(session)
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
  }, [applyAuthSession, dispatch])

  return children
}
