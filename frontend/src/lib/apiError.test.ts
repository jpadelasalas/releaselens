import { AxiosError, AxiosHeaders } from 'axios'
import { describe, expect, it } from 'vitest'
import { z } from 'zod'

import { getApiErrorMessage } from './apiError'

describe('getApiErrorMessage', () => {
  it('returns controlled API details with the request identifier', () => {
    const error = new AxiosError('Request failed', 'ERR_BAD_RESPONSE', undefined, undefined, {
      data: { error: { message: 'Analytics are temporarily unavailable.' } },
      status: 503,
      statusText: 'Service Unavailable',
      headers: new AxiosHeaders({ 'x-request-id': 'request-1234' }),
      config: { headers: new AxiosHeaders() },
    })

    expect(getApiErrorMessage(error, 'Dashboard failed.')).toBe(
      'Analytics are temporarily unavailable. Request ID: request-1234',
    )
  })

  it('distinguishes network and response-schema failures', () => {
    expect(
      getApiErrorMessage(new AxiosError('Network Error'), 'Dashboard failed.'),
    ).toContain('Cannot reach the ReleaseLens API')

    const schemaResult = z.object({ data: z.string() }).safeParse({ data: 42 })
    expect(schemaResult.success).toBe(false)

    if (!schemaResult.success) {
      expect(getApiErrorMessage(schemaResult.error, 'Dashboard failed.')).toBe(
        'The API returned data in an unexpected format.',
      )
    }
  })
})
