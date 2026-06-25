import { Outlet } from 'react-router-dom'
import { useScopeContext } from '../scope/useScopeContext'
import { DemoBanner } from '../../components/demo/DemoBanner'

export function DemoLayout() {
  const { scope } = useScopeContext()

  return (
    <div className="demo-route-shell">
      {scope.kind === 'demo' && <DemoBanner />}
      <Outlet />
    </div>
  )
}
