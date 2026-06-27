import DashboardOutlinedIcon from '@mui/icons-material/DashboardOutlined'
import MenuBookOutlinedIcon from '@mui/icons-material/MenuBookOutlined'
import MergeOutlinedIcon from '@mui/icons-material/MergeOutlined'
import SourceOutlinedIcon from '@mui/icons-material/SourceOutlined'
import SyncOutlinedIcon from '@mui/icons-material/SyncOutlined'
import { BrandLink } from '../../../components/navigation/BrandLink'

const demoNavItems = [
  { label: 'Dashboard', icon: DashboardOutlinedIcon },
  { label: 'Pull Requests', icon: MergeOutlinedIcon },
  { label: 'Repositories', icon: SourceOutlinedIcon },
  { label: 'Sync Runs', icon: SyncOutlinedIcon },
  { label: 'Metric Glossary', icon: MenuBookOutlinedIcon },
]

export function DashboardNav() {
  return (
    <aside className="dashboard-nav">
      <BrandLink />
      {demoNavItems.map(({ label, icon: Icon }) => (
        <span
          key={label}
          className={label === 'Dashboard' ? 'nav-current' : undefined}
        >
          <Icon aria-hidden="true" fontSize="small" />
          {label}
        </span>
      ))}
    </aside>
  )
}
