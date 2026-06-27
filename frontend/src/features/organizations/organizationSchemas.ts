import { z } from 'zod'

export const createOrganizationSchema = z.object({
  name: z.string().trim().min(1, 'Workspace name is required.').max(120),
  timezone: z.string().min(1, 'Timezone is required.'),
})

export type CreateOrganizationValues = z.infer<
  typeof createOrganizationSchema
>
