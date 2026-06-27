import { Navigate, Outlet, useLocation } from 'react-router-dom'

import { useAppSelector } from '../../app/store/hooks'

export function ProtectedRoute() {
  const status = useAppSelector((state) => state.auth.status)
  const location = useLocation()

  if (status === 'checking') {
    return (
      <main className="centered-page" aria-busy="true">
        <p className="eyebrow">Connected workspace</p>
        <h1>Restoring your session...</h1>
      </main>
    )
  }

  if (status === 'anonymous') {
    return <Navigate to="/sign-in" replace state={{ from: location.pathname }} />
  }

  return <Outlet />
}
