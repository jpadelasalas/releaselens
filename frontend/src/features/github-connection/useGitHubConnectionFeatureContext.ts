import { useContext } from 'react'

import { GitHubConnectionFeatureContext } from './githubConnectionContextInstance'

export function useGitHubConnectionFeatureContext() {
  const context = useContext(GitHubConnectionFeatureContext)

  if (!context) {
    throw new Error(
      'GitHub connection hooks must be used within GitHubConnectionFeatureProvider.',
    )
  }

  return context
}
