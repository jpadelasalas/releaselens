import { BrandLink } from '../../../components/navigation/BrandLink'

const demoNavItems = [
  'Dashboard',
  'Pull Requests',
  'Repositories',
  'Sync Runs',
  'Metric Glossary',
]

export function DashboardNav() {
  return (
    <aside className="dashboard-nav">
      <BrandLink />
      {demoNavItems.map((item) => (
        <span
          key={item}
          className={item === 'Dashboard' ? 'nav-current' : undefined}
        >
          {item}
        </span>
      ))}
    </aside>
  )
}
