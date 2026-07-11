import { describe, expect, it } from 'vitest'

import { deploymentDetailSchema, deploymentSchema } from './deploymentsApi'

describe('deployment schemas', () => {
  it('parses a deployment summary', () => {
    const deployment = deploymentSchema.parse({
      id: 1,
      repository_id: 2,
      repository_name: 'service',
      release_id: null,
      ref: 'main',
      sha: 'abc123',
      original_environment: 'Production',
      normalized_environment: 'production',
      is_production: true,
      status: 'success',
      original_status: 'success',
      description: null,
      created_at_github: '2026-07-01T00:00:00Z',
      updated_at_github: null,
    })

    expect(deployment.is_production).toBe(true)
  })

  it('parses a deployment detail with status events', () => {
    const deployment = deploymentDetailSchema.parse({
      id: 1,
      repository_id: 2,
      repository_name: 'service',
      release_id: null,
      ref: 'main',
      sha: 'abc123',
      original_environment: 'staging',
      normalized_environment: 'staging',
      is_production: false,
      status: 'success',
      original_status: 'success',
      description: null,
      created_at_github: '2026-07-01T00:00:00Z',
      updated_at_github: '2026-07-01T00:05:00Z',
      status_events: [
        {
          id: 1,
          status: 'success',
          original_status: 'success',
          description: 'Deployed',
          log_url: null,
          environment_url: null,
          occurred_at: '2026-07-01T00:05:00Z',
        },
      ],
    })

    expect(deployment.status_events).toHaveLength(1)
  })
})
