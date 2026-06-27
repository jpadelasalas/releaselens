import { describe, expect, it } from 'vitest'

import {
  availableGitHubRepositorySchema,
  repositorySchema,
} from './repositoriesApi'

describe('repository schemas', () => {
  it('parses available GitHub repository metadata', () => {
    const repository = availableGitHubRepositorySchema.parse({
      github_repository_id: 101,
      name: 'api',
      full_name: 'acme/api',
      description: 'API service',
      visibility: 'private',
      default_branch: 'main',
      html_url: 'https://github.com/acme/api',
      is_archived: false,
      is_monitored: true,
    })

    expect(repository.full_name).toBe('acme/api')
    expect(repository.is_monitored).toBe(true)
  })

  it('parses imported repository monitoring state', () => {
    const repository = repositorySchema.parse({
      id: 1,
      github_repository_id: 101,
      name: 'api',
      full_name: 'acme/api',
      description: null,
      visibility: 'private',
      default_branch: 'main',
      html_url: 'https://github.com/acme/api',
      is_archived: false,
      is_accessible: true,
      access_error: null,
      sync_enabled: true,
      sync_status: 'never_synced',
      last_sync_at: null,
      last_successful_sync_at: null,
    })

    expect(repository.sync_enabled).toBe(true)
  })
})
