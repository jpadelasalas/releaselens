import { describe, expect, it } from 'vitest'

import { incidentDetailSchema, incidentSchema } from './incidentsApi'

describe('incident schemas', () => {
  it('parses an incident summary', () => {
    const incident = incidentSchema.parse({
      id: 1,
      organization_id: 5,
      title: 'API latency spike',
      summary: null,
      severity: 'sev2',
      state: 'investigating',
      started_at: '2026-07-01T00:00:00Z',
      resolved_at: null,
      closed_at: null,
      created_by_user_id: 9,
      created_at: '2026-07-01T00:00:00Z',
      updated_at: '2026-07-01T00:00:00Z',
    })

    expect(incident.state).toBe('investigating')
  })

  it('parses an incident detail with nested collections', () => {
    const incident = incidentDetailSchema.parse({
      id: 1,
      organization_id: 5,
      title: 'Outage',
      summary: null,
      severity: 'sev1',
      state: 'resolved',
      started_at: '2026-07-01T00:00:00Z',
      resolved_at: '2026-07-01T01:00:00Z',
      closed_at: null,
      created_by_user_id: 9,
      created_at: '2026-07-01T00:00:00Z',
      updated_at: '2026-07-01T00:00:00Z',
      timeline: [
        { id: 1, actor_user_id: 9, entry_type: 'created', message: 'Incident opened.', occurred_at: '2026-07-01T00:00:00Z' },
      ],
      action_items: [
        { id: 1, description: 'Add alerting', assigned_to_user_id: null, is_completed: false, completed_at: null, completed_by_user_id: null },
      ],
      links: [{ id: 1, linkable_type: 'deployment', linkable_id: 5 }],
      postmortem: {
        summary: 'Root cause and remediation.',
        root_cause: null,
        impact: null,
        is_published: true,
        published_at: '2026-07-01T02:00:00Z',
      },
    })

    expect(incident.timeline).toHaveLength(1)
    expect(incident.postmortem?.is_published).toBe(true)
  })
})
