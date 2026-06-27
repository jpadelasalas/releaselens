import { useContext } from 'react'

import { RepositoryManagementContext } from './repositoryManagementContextInstance'

export function useRepositoryManagementContext() {
  const context = useContext(RepositoryManagementContext)

  if (!context) {
    throw new Error(
      'Repository management hooks must be used within RepositoryManagementProvider.',
    )
  }

  return context
}
