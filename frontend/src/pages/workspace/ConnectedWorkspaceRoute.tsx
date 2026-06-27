import { OrganizationFeatureProvider } from '../../features/organizations/OrganizationFeatureContext'
import { ConnectedWorkspacePage } from './ConnectedWorkspacePage'

export function ConnectedWorkspaceRoute() {
  return (
    <OrganizationFeatureProvider>
      <ConnectedWorkspacePage />
    </OrganizationFeatureProvider>
  )
}
