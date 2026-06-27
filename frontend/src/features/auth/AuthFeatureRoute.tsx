import { Outlet } from 'react-router-dom'

import { AuthFeatureProvider } from './AuthFeatureContext'

export function AuthFeatureRoute() {
  return (
    <AuthFeatureProvider>
      <Outlet />
    </AuthFeatureProvider>
  )
}

export function AuthRouteFallback() {
  return (
    <main className="centered-page" aria-busy="true">
      <p className="eyebrow">Connected workspace</p>
      <h1>Loading account access...</h1>
    </main>
  )
}
