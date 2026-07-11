import { z } from 'zod'
import axios from 'axios'

import api from '../../lib/api'
import { prepareCsrfCookie } from '../../lib/csrf'

export const releaseStates = [
  'draft',
  'in_review',
  'approved',
  'released',
  'closed',
  'cancelled',
] as const
export type ReleaseState = (typeof releaseStates)[number]

export const releaseSchema = z.object({
  id: z.number().int().positive(),
  organization_id: z.number().int().positive(),
  title: z.string().min(1),
  description: z.string().nullable(),
  state: z.enum(releaseStates),
  target_release_at: z.string().nullable(),
  released_at: z.string().nullable(),
  closed_at: z.string().nullable(),
  created_by_user_id: z.number().int().positive().nullable(),
  created_at: z.string(),
  updated_at: z.string(),
})

const releasePullRequestSchema = z.object({
  id: z.number().int().positive(),
  number: z.number().int().positive(),
  title: z.string(),
  html_url: z.string().nullable(),
  merged_at: z.string().nullable(),
  repository_id: z.number().int().positive(),
  repository_name: z.string(),
})

const releaseRepositorySchema = z.object({
  id: z.number().int().positive(),
  name: z.string(),
  full_name: z.string(),
})

export const releaseChecklistItemSchema = z.object({
  id: z.number().int().positive(),
  label: z.string(),
  is_required: z.boolean(),
  position: z.number().int(),
  completed_at: z.string().nullable(),
  completed_by_user_id: z.number().int().positive().nullable(),
})

const releaseApprovalSchema = z.object({
  id: z.number().int().positive(),
  approver_user_id: z.number().int().positive(),
  approved_at: z.string(),
})

const releaseReadinessWarningSchema = z.object({
  code: z.string(),
  message: z.string(),
})

export const releaseDetailSchema = releaseSchema.extend({
  pull_requests: z.array(releasePullRequestSchema),
  repositories: z.array(releaseRepositorySchema),
  checklist_items: z.array(releaseChecklistItemSchema),
  approvals: z.array(releaseApprovalSchema),
  readiness_warnings: z.array(releaseReadinessWarningSchema),
})

export type Release = z.infer<typeof releaseSchema>
export type ReleaseDetail = z.infer<typeof releaseDetailSchema>
export type ReleaseChecklistItem = z.infer<typeof releaseChecklistItemSchema>

export const releasePolicySchema = z.object({
  approval_mode: z.enum(['none', 'single_approver']),
  allow_self_approval: z.boolean(),
})
export type ReleasePolicy = z.infer<typeof releasePolicySchema>

const releasesResponseSchema = z.object({ data: z.array(releaseSchema) })
const releaseResponseSchema = z.object({ data: releaseSchema })
const releaseDetailResponseSchema = z.object({ data: releaseDetailSchema })
const checklistItemResponseSchema = z.object({ data: releaseChecklistItemSchema })
const policyResponseSchema = z.object({ data: releasePolicySchema })

export async function getReleases(
  organizationId: number,
  state?: string,
): Promise<Release[]> {
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/releases`,
    { params: state ? { state } : undefined },
  )

  return releasesResponseSchema.parse(response.data).data
}

export async function getRelease(
  organizationId: number,
  releaseId: number,
): Promise<ReleaseDetail> {
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/releases/${releaseId}`,
  )

  return releaseDetailResponseSchema.parse(response.data).data
}

export async function createRelease(
  organizationId: number,
  data: { title: string; description?: string; target_release_at?: string },
): Promise<Release> {
  await prepareCsrfCookie()
  const response = await api.post<unknown>(
    `/api/v1/organizations/${organizationId}/releases`,
    data,
  )

  return releaseResponseSchema.parse(response.data).data
}

export async function updateRelease(
  organizationId: number,
  releaseId: number,
  data: { title?: string; description?: string; target_release_at?: string },
): Promise<Release> {
  await prepareCsrfCookie()
  const response = await api.patch<unknown>(
    `/api/v1/organizations/${organizationId}/releases/${releaseId}`,
    data,
  )

  return releaseResponseSchema.parse(response.data).data
}

export async function transitionRelease(
  organizationId: number,
  releaseId: number,
  to: ReleaseState,
): Promise<Release> {
  await prepareCsrfCookie()
  const response = await api.post<unknown>(
    `/api/v1/organizations/${organizationId}/releases/${releaseId}/transition`,
    { to },
  )

  return releaseResponseSchema.parse(response.data).data
}

export async function addReleasePullRequest(
  organizationId: number,
  releaseId: number,
  pullRequestId: number,
): Promise<void> {
  await prepareCsrfCookie()
  await api.post(
    `/api/v1/organizations/${organizationId}/releases/${releaseId}/pull-requests`,
    { pull_request_id: pullRequestId },
  )
}

export async function removeReleasePullRequest(
  organizationId: number,
  releaseId: number,
  pullRequestId: number,
): Promise<void> {
  await prepareCsrfCookie()
  await api.delete(
    `/api/v1/organizations/${organizationId}/releases/${releaseId}/pull-requests/${pullRequestId}`,
  )
}

export async function addChecklistItem(
  organizationId: number,
  releaseId: number,
  label: string,
  isRequired: boolean,
): Promise<ReleaseChecklistItem> {
  await prepareCsrfCookie()
  const response = await api.post<unknown>(
    `/api/v1/organizations/${organizationId}/releases/${releaseId}/checklist-items`,
    { label, is_required: isRequired },
  )

  return checklistItemResponseSchema.parse(response.data).data
}

export async function updateChecklistItem(
  organizationId: number,
  releaseId: number,
  itemId: number,
  completed: boolean,
): Promise<ReleaseChecklistItem> {
  await prepareCsrfCookie()
  const response = await api.patch<unknown>(
    `/api/v1/organizations/${organizationId}/releases/${releaseId}/checklist-items/${itemId}`,
    { completed },
  )

  return checklistItemResponseSchema.parse(response.data).data
}

export async function removeChecklistItem(
  organizationId: number,
  releaseId: number,
  itemId: number,
): Promise<void> {
  await prepareCsrfCookie()
  await api.delete(
    `/api/v1/organizations/${organizationId}/releases/${releaseId}/checklist-items/${itemId}`,
  )
}

export async function approveRelease(
  organizationId: number,
  releaseId: number,
): Promise<void> {
  await prepareCsrfCookie()
  await api.post(
    `/api/v1/organizations/${organizationId}/releases/${releaseId}/approvals`,
  )
}

export async function getReleasePolicy(
  organizationId: number,
): Promise<ReleasePolicy> {
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/release-policy`,
  )

  return policyResponseSchema.parse(response.data).data
}

export async function exportReleaseMarkdown(
  organizationId: number,
  releaseId: number,
): Promise<Blob> {
  const response = await api.get(
    `/api/v1/organizations/${organizationId}/releases/${releaseId}/export.md`,
    { responseType: 'blob' },
  )

  return response.data as Blob
}

export function getReleaseError(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const message = error.response?.data?.error?.message

    if (typeof message === 'string') {
      return message
    }
  }

  return 'Releases are unavailable. Please try again.'
}
