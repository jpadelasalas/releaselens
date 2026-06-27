import axios from 'axios'

import api from '../../lib/api'
import { prepareCsrfCookie } from '../auth/authApi'
import {
  githubConnectionResponseSchema,
  githubConnectResponseSchema,
  type GitHubConnection,
} from './githubConnectionSchemas'

export async function getGitHubConnection(
  organizationId: number,
): Promise<GitHubConnection | null> {
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/github/connection`,
  )

  return githubConnectionResponseSchema.parse(response.data).data
}

export async function startGitHubConnection(
  organizationId: number,
): Promise<string> {
  await prepareCsrfCookie()
  const response = await api.post<unknown>(
    `/api/v1/organizations/${organizationId}/github/connect`,
  )

  return githubConnectResponseSchema.parse(response.data).data.url
}

export async function disconnectGitHubConnection(
  organizationId: number,
): Promise<void> {
  await prepareCsrfCookie()
  await api.delete(
    `/api/v1/organizations/${organizationId}/github/connection`,
  )
}

export function getGitHubConnectionError(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const message = error.response?.data?.error?.message

    if (typeof message === 'string') {
      return message
    }
  }

  return 'The GitHub connection is unavailable. Please try again.'
}
