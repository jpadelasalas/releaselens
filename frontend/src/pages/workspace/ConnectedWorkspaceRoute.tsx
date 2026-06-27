import { OrganizationFeatureProvider } from '../../features/organizations/OrganizationFeatureContext'
import { GitHubConnectionFeatureProvider } from '../../features/github-connection/GitHubConnectionFeatureContext'
import { RepositoryManagementProvider } from '../../features/repositories/RepositoryManagementContext'
import { ConnectedWorkspacePage } from './ConnectedWorkspacePage'

export function ConnectedWorkspaceRoute() {
  return (
    <OrganizationFeatureProvider>
      <GitHubConnectionFeatureProvider>
        <RepositoryManagementProvider>
          <ConnectedWorkspacePage />
        </RepositoryManagementProvider>
      </GitHubConnectionFeatureProvider>
    </OrganizationFeatureProvider>
  )
}
