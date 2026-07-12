import { describe, expect, it } from 'vitest'

import { aiGenerationSchema } from './aiApi'

describe('ai generation schema', () => {
  it('parses a succeeded generation', () => {
    const generation = aiGenerationSchema.parse({
      id: 1,
      provider: 'stub',
      status: 'succeeded',
      input_fields: ['title', 'description', 'pull_request_titles'],
      output: '# July release',
      error_message: null,
      created_at: '2026-07-01T00:00:00Z',
    })

    expect(generation.status).toBe('succeeded')
    expect(generation.output).toBe('# July release')
  })
})
