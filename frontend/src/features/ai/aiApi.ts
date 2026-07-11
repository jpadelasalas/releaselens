import { z } from 'zod'
import axios from 'axios'

import api from '../../lib/api'
import { prepareCsrfCookie } from '../../lib/csrf'

export const aiGenerationSchema = z.object({
  id: z.number().int().positive(),
  provider: z.string(),
  status: z.enum(['succeeded', 'failed', 'blocked']),
  input_fields: z.array(z.string()),
  output: z.string().nullable(),
  error_message: z.string().nullable(),
  created_at: z.string(),
})
export type AiGeneration = z.infer<typeof aiGenerationSchema>

const generationsResponseSchema = z.object({ data: z.array(aiGenerationSchema) })
const generationResponseSchema = z.object({ data: aiGenerationSchema })

export async function getAiGenerations(
  organizationId: number,
  releaseId: number,
): Promise<AiGeneration[]> {
  const response = await api.get<unknown>(
    `/api/v1/organizations/${organizationId}/releases/${releaseId}/ai-generations`,
  )

  return generationsResponseSchema.parse(response.data).data
}

export async function generateReleaseNotes(
  organizationId: number,
  releaseId: number,
): Promise<AiGeneration> {
  await prepareCsrfCookie()
  const response = await api.post<unknown>(
    `/api/v1/organizations/${organizationId}/releases/${releaseId}/ai-generations`,
  )

  return generationResponseSchema.parse(response.data).data
}

export function getAiGenerationError(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const message = error.response?.data?.error?.message

    if (typeof message === 'string') {
      return message
    }
  }

  return 'Release notes generation is unavailable. Please try again.'
}
