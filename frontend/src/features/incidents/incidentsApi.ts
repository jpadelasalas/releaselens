import { z } from 'zod'
import axios from 'axios'

import api from '../../lib/api'
import { prepareCsrfCookie } from '../../lib/csrf'

export const incidentSeverities = ['sev1', 'sev2', 'sev3', 'sev4'] as const
export type IncidentSeverity = (typeof incidentSeverities)[number]

export const incidentStates = ['investigating', 'identified', 'monitoring', 'resolved', 'closed'] as const
export type IncidentState = (typeof incidentStates)[number]

export const incidentSchema = z.object({
  id: z.number().int().positive(),
  organization_id: z.number().int().positive(),
  title: z.string(),
  summary: z.string().nullable(),
  severity: z.enum(incidentSeverities),
  state: z.enum(incidentStates),
  started_at: z.string(),
  resolved_at: z.string().nullable(),
  closed_at: z.string().nullable(),
  created_by_user_id: z.number().int().positive().nullable(),
  created_at: z.string(),
  updated_at: z.string(),
})
export type Incident = z.infer<typeof incidentSchema>

const timelineEntrySchema = z.object({
  id: z.number().int().positive(),
  actor_user_id: z.number().int().positive().nullable(),
  entry_type: z.string(),
  message: z.string(),
  occurred_at: z.string(),
})

export const actionItemSchema = z.object({
  id: z.number().int().positive(),
  description: z.string(),
  assigned_to_user_id: z.number().int().positive().nullable(),
  is_completed: z.boolean(),
  completed_at: z.string().nullable(),
  completed_by_user_id: z.number().int().positive().nullable(),
})
export type ActionItem = z.infer<typeof actionItemSchema>

const incidentLinkSchema = z.object({
  id: z.number().int().positive(),
  linkable_type: z.string(),
  linkable_id: z.number().int().positive(),
})

const postmortemSchema = z.object({
  summary: z.string(),
  root_cause: z.string().nullable(),
  impact: z.string().nullable(),
  is_published: z.boolean(),
  published_at: z.string().nullable(),
})
export type Postmortem = z.infer<typeof postmortemSchema>

export const incidentDetailSchema = incidentSchema.extend({
  timeline: z.array(timelineEntrySchema),
  action_items: z.array(actionItemSchema),
  links: z.array(incidentLinkSchema),
  postmortem: postmortemSchema.nullable(),
})
export type IncidentDetail = z.infer<typeof incidentDetailSchema>

const incidentsResponseSchema = z.object({ data: z.array(incidentSchema) })
const incidentResponseSchema = z.object({ data: incidentSchema })
const incidentDetailResponseSchema = z.object({ data: incidentDetailSchema })
const actionItemResponseSchema = z.object({ data: actionItemSchema })
const postmortemResponseSchema = z.object({ data: postmortemSchema })

export async function getIncidents(
  organizationId: number,
  filters?: { state?: string; severity?: string },
): Promise<Incident[]> {
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/incidents`,
    { params: filters },
  )

  return incidentsResponseSchema.parse(response.data).data
}

export async function getIncident(
  organizationId: number,
  incidentId: number,
): Promise<IncidentDetail> {
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/incidents/${incidentId}`,
  )

  return incidentDetailResponseSchema.parse(response.data).data
}

export async function createIncident(
  organizationId: number,
  data: { title: string; severity: IncidentSeverity; summary?: string },
): Promise<Incident> {
  await prepareCsrfCookie()
  const response = await api.post<unknown>(
    `/api/v1/organizations/${organizationId}/incidents`,
    data,
  )

  return incidentResponseSchema.parse(response.data).data
}

export async function transitionIncident(
  organizationId: number,
  incidentId: number,
  to: IncidentState,
): Promise<Incident> {
  await prepareCsrfCookie()
  const response = await api.post<unknown>(
    `/api/v1/organizations/${organizationId}/incidents/${incidentId}/transition`,
    { to },
  )

  return incidentResponseSchema.parse(response.data).data
}

export async function addActionItem(
  organizationId: number,
  incidentId: number,
  description: string,
): Promise<ActionItem> {
  await prepareCsrfCookie()
  const response = await api.post<unknown>(
    `/api/v1/organizations/${organizationId}/incidents/${incidentId}/action-items`,
    { description },
  )

  return actionItemResponseSchema.parse(response.data).data
}

export async function updateActionItem(
  organizationId: number,
  incidentId: number,
  itemId: number,
  completed: boolean,
): Promise<ActionItem> {
  await prepareCsrfCookie()
  const response = await api.patch<unknown>(
    `/api/v1/organizations/${organizationId}/incidents/${incidentId}/action-items/${itemId}`,
    { completed },
  )

  return actionItemResponseSchema.parse(response.data).data
}

export async function savePostmortem(
  organizationId: number,
  incidentId: number,
  data: { summary: string; root_cause?: string; impact?: string },
): Promise<Postmortem> {
  await prepareCsrfCookie()
  const response = await api.put<unknown>(
    `/api/v1/organizations/${organizationId}/incidents/${incidentId}/postmortem`,
    data,
  )

  return postmortemResponseSchema.parse(response.data).data
}

export async function publishPostmortem(
  organizationId: number,
  incidentId: number,
): Promise<Postmortem> {
  await prepareCsrfCookie()
  const response = await api.post<unknown>(
    `/api/v1/organizations/${organizationId}/incidents/${incidentId}/postmortem/publish`,
  )

  return postmortemResponseSchema.parse(response.data).data
}

export function getIncidentError(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const message = error.response?.data?.error?.message

    if (typeof message === 'string') {
      return message
    }
  }

  return 'Incidents are unavailable. Please try again.'
}
