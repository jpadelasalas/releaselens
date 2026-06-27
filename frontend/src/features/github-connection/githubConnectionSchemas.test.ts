import { describe, expect, it } from 'vitest'

import { githubConnectionResponseSchema } from './githubConnectionSchemas'

describe('githubConnectionResponseSchema', () => {
  it('accepts an active read-only GitHub connection', () => {
    const result = githubConnectionResponseSchema.parse({
      data: {
        status: 'active',
        account: { login: 'acme-engineering', type: 'Organization' },
        repository_selection: 'selected',
        permissions: { metadata: 'read', pull_requests: 'read' },
        connected_at: '2026-06-27T10:00:00Z',
        suspended_at: null,
      },
    })

    expect(result.data?.account.login).toBe('acme-engineering')
    expect(result.data?.permissions.pull_requests).toBe('read')
  })

  it('accepts an unconnected workspace', () => {
    expect(githubConnectionResponseSchema.parse({ data: null }).data).toBeNull()
  })
})
