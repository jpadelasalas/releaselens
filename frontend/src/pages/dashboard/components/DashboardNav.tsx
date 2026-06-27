import DashboardOutlinedIcon from '@mui/icons-material/DashboardOutlined'
import MenuBookOutlinedIcon from '@mui/icons-material/MenuBookOutlined'
import MergeOutlinedIcon from '@mui/icons-material/MergeOutlined'
import SourceOutlinedIcon from '@mui/icons-material/SourceOutlined'
import SyncOutlinedIcon from '@mui/icons-material/SyncOutlined'
import { Link } from 'react-router-dom'
import { BrandLink } from '../../../components/navigation/BrandLink'

const demoNavItems = [
  { label: 'Dashboard', icon: DashboardOutlinedIcon, to: '/demo/dashboard' },
  { label: 'Pull Requests', icon: MergeOutlinedIcon, to: '/demo/pull-requests' },
  { label: 'Repositories', icon: SourceOutlinedIcon },
  { label: 'Sync Runs', icon: SyncOutlinedIcon },
  { label: 'Metric Glossary', icon: MenuBookOutlinedIcon },
]

type DashboardNavProps = {
  activeItem?: 'Dashboard' | 'Pull Requests'
}

export function DashboardNav({ activeItem = 'Dashboard' }: DashboardNavProps) {
  return (
    <aside className="dashboard-nav">
      <BrandLink />
      {demoNavItems.map(({ label, icon: Icon, to }) => {
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
