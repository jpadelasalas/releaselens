import DashboardOutlinedIcon from '@mui/icons-material/DashboardOutlined'
import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined'
import MenuBookOutlinedIcon from '@mui/icons-material/MenuBookOutlined'
import MergeOutlinedIcon from '@mui/icons-material/MergeOutlined'
import NotificationsOutlinedIcon from '@mui/icons-material/NotificationsOutlined'
import ReportProblemOutlinedIcon from '@mui/icons-material/ReportProblemOutlined'
import RocketLaunchOutlinedIcon from '@mui/icons-material/RocketLaunchOutlined'
import SourceOutlinedIcon from '@mui/icons-material/SourceOutlined'
import SyncOutlinedIcon from '@mui/icons-material/SyncOutlined'
import { Link } from 'react-router-dom'
import { useScopeContext } from '../../../app/scope/useScopeContext'
import { BrandLink } from '../../../components/navigation/BrandLink'

type DashboardNavProps = {
  activeItem?:
    | 'Dashboard'
    | 'Pull Requests'
    | 'Releases'
    | 'Deployments'
    | 'Notifications'
    | 'Incidents'
    | 'Metric Glossary'
}

export function DashboardNav({ activeItem = 'Dashboard' }: DashboardNavProps) {
  const { scope } = useScopeContext()
  const basePath = scope.kind === 'connected' ? '/app' : '/demo'
  const isConnected = scope.kind === 'connected'
  const navItems = [
    { label: 'Dashboard', icon: DashboardOutlinedIcon, to: `${basePath}/dashboard` },
    { label: 'Pull Requests', icon: MergeOutlinedIcon, to: `${basePath}/pull-requests` },
    { label: 'Repositories', icon: SourceOutlinedIcon, to: scope.kind === 'connected' ? '/app' : undefined },
    { label: 'Sync Runs', icon: SyncOutlinedIcon, to: scope.kind === 'connected' ? '/app' : undefined },
    { label: 'Releases', icon: RocketLaunchOutlinedIcon, to: isConnected ? '/app/releases' : undefined },
    { label: 'Deployments', icon: LocalShippingOutlinedIcon, to: isConnected ? '/app/deployments' : undefined },
    { label: 'Notifications', icon: NotificationsOutlinedIcon, to: isConnected ? '/app/notifications' : undefined },
    { label: 'Incidents', icon: ReportProblemOutlinedIcon, to: isConnected ? '/app/incidents' : undefined },
    { label: 'Metric Glossary', icon: MenuBookOutlinedIcon, to: `${basePath}/metrics` },
  ]

  return (
    <aside className="dashboard-nav">
      <BrandLink />
      {navItems.map(({ label, icon: Icon, to }) => {
        const className = `dashboard-nav-item${
          label === activeItem ? ' nav-current' : ''
        }`

        return to ? (
          <Link key={label} className={className} to={to}>
            <Icon aria-hidden="true" fontSize="small" />
            {label}
          </Link>
        ) : (
          <span key={label} className={className}>
            <Icon aria-hidden="true" fontSize="small" />
            {label}
          </span>
        )
      })}
    </aside>
  )
}
