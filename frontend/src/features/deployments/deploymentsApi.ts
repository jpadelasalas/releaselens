import { z } from 'zod'
import axios from 'axios'

import api from '../../lib/api'
import { prepareCsrfCookie } from '../../lib/csrf'

export const deploymentSchema = z.object({
  id: z.number().int().positive(),
  repository_id: z.number().int().positive(),
  repository_name: z.string(),
  release_id: z.number().int().positive().nullable(),
  ref: z.string(),
  sha: z.string(),
  original_environment: z.string(),
  normalized_environment: z.string(),
  is_production: z.boolean(),
  status: z.string(),
  original_status: z.string(),
  description: z.string().nullable(),
  created_at_github: z.string(),
  updated_at_github: z.string().nullable(),
})
export type Deployment = z.infer<typeof deploymentSchema>

const deploymentStatusEventSchema = z.object({
  id: z.number().int().positive(),
  status: z.string(),
  original_status: z.string(),
  description: z.string().nullable(),
  log_url: z.string().nullable(),
  environment_url: z.string().nullable(),
  occurred_at: z.string(),
})

export const deploymentDetailSchema = deploymentSchema.extend({
  status_events: z.array(deploymentStatusEventSchema),
})
export type DeploymentDetail = z.infer<typeof deploymentDetailSchema>

export const environmentMappingSchema = z.object({
  id: z.number().int().positive(),
  source_environment: z.string(),
  normalized_environment: z.string(),
  is_production: z.boolean(),
})
export type EnvironmentMapping = z.infer<typeof environmentMappingSchema>

const deploymentsResponseSchema = z.object({ data: z.array(deploymentSchema) })
const deploymentDetailResponseSchema = z.object({ data: deploymentDetailSchema })
const environmentMappingsResponseSchema = z.object({ data: z.array(environmentMappingSchema) })

export async function getDeployments(
  organizationId: number,
  filters?: { status?: string; environment?: string },
): Promise<Deployment[]> {
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/deployments`,
    { params: filters },
  )

  return deploymentsResponseSchema.parse(response.data).data
}

export async function getDeployment(
  organizationId: number,
  deploymentId: number,
): Promise<DeploymentDetail> {
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/deployments/${deploymentId}`,
  )

  return deploymentDetailResponseSchema.parse(response.data).data
}

export async function linkDeploymentRelease(
  organizationId: number,
  deploymentId: number,
  releaseId: number | null,
): Promise<void> {
  await prepareCsrfCookie()
  await api.post(
    `/api/v1/organizations/${organizationId}/deployments/${deploymentId}/link-release`,
    { release_id: releaseId },
  )
}

export async function getEnvironmentMappings(
  organizationId: number,
): Promise<EnvironmentMapping[]> {
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/environment-mappings`,
  )

  return environmentMappingsResponseSchema.parse(response.data).data
}

export async function upsertEnvironmentMapping(
  organizationId: number,
  data: { source_environment: string; normalized_environment: string; is_production: boolean },
): Promise<EnvironmentMapping> {
  await prepareCsrfCookie()
  const response = await api.post<unknown>(
    `/api/v1/organizations/${organizationId}/environment-mappings`,
    data,
  )

  return environmentMappingSchema.parse((response.data as { data: unknown }).data)
}

export function getDeploymentError(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const message = error.response?.data?.error?.message

    if (typeof message === 'string') {
      return message
    }
  }

  return 'Deployments are unavailable. Please try again.'
}
