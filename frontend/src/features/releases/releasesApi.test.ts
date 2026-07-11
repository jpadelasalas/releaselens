import { describe, expect, it } from 'vitest'

import { releaseDetailSchema, releaseSchema } from './releasesApi'

describe('release schemas', () => {
  it('parses a release summary', () => {
    const release = releaseSchema.parse({
      id: 1,
      organization_id: 5,
      title: 'July release',
      description: null,
      state: 'draft',
      target_release_at: null,
      released_at: null,
      closed_at: null,
      created_by_user_id: 9,
      created_at: '2026-07-01T00:00:00Z',
      updated_at: '2026-07-01T00:00:00Z',
    })

    expect(release.state).toBe('draft')
  })

  it('parses a release detail with nested collections', () => {
    const release = releaseDetailSchema.parse({
      id: 1,
      organization_id: 5,
      title: 'July release',
      description: null,
      state: 'in_review',
      target_release_at: null,
      released_at: null,
      closed_at: null,
      created_by_user_id: 9,
      created_at: '2026-07-01T00:00:00Z',
      updated_at: '2026-07-01T00:00:00Z',
      pull_requests: [
        {
          id: 1,
          number: 42,
          title: 'Add feature',
          html_url: null,
          merged_at: '2026-07-01T00:00:00Z',
          repository_id: 2,
          repository_name: 'service',
        },
      ],
      repositories: [{ id: 2, name: 'service', full_name: 'acme/service' }],
      checklist_items: [
        {
          id: 1,
          label: 'Smoke test',
          is_required: true,
          position: 0,
          completed_at: null,
          completed_by_user_id: null,
        },
      ],
      approvals: [],
      readiness_warnings: [{ code: 'no_pull_requests', message: 'No pull requests are included in this release.' }],
    })

    expect(release.pull_requests).toHaveLength(1)
    expect(release.checklist_items[0]?.is_required).toBe(true)
    expect(release.readiness_warnings[0]?.code).toBe('no_pull_requests')
  })
})
