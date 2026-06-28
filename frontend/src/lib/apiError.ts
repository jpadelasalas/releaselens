import axios from 'axios'
import { ZodError } from 'zod'

export function getApiErrorMessage(
  error: unknown,
  fallback: string,
): string {
  if (error instanceof ZodError) {
    return 'The API returned data in an unexpected format.'
  }

  if (!axios.isAxiosError(error)) {
    return fallback
  }

  if (!error.response) {
    return 'Cannot reach the ReleaseLens API. Check that the backend is running and retry.'
  }

  const controlledMessage = error.response.data?.error?.message
  const requestId = error.response.headers['x-request-id']
  let message = typeof controlledMessage === 'string'
    ? controlledMessage
    : statusMessage(error.response.status, fallback)

  if (typeof requestId === 'string' && requestId !== '') {
    message += ` Request ID: ${requestId}`
  }

  return message
}

function statusMessage(status: number, fallback: string): string {
  if (status === 401) {
    return 'Your session has expired. Sign in again.'
  }

  if (status === 403) {
    return 'Your workspace role cannot access these analytics.'
  }

  if (status >= 500) {
    return 'The ReleaseLens API encountered an error while loading analytics.'
  }

  return fallback
}
