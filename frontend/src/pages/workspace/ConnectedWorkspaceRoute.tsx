import { OrganizationFeatureProvider } from '../../features/organizations/OrganizationFeatureContext'
import { GitHubConnectionFeatureProvider } from '../../features/github-connection/GitHubConnectionFeatureContext'
import { ConnectedWorkspacePage } from './ConnectedWorkspacePage'

export function ConnectedWorkspaceRoute() {
  return (
    <OrganizationFeatureProvider>
      <GitHubConnectionFeatureProvider>
        <ConnectedWorkspacePage />
      </GitHubConnectionFeatureProvider>
    </OrganizationFeatureProvider>
  )
}
