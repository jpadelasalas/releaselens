import { z } from 'zod'

export const createOrganizationSchema = z.object({
  name: z.string().trim().min(1, 'Workspace name is required.').max(120),
  timezone: z.string().min(1, 'Timezone is required.'),
})

export type CreateOrganizationValues = z.infer<
  typeof createOrganizationSchema
>

export const organizationRoleSchema = z.enum(['owner', 'manager', 'viewer'])

export const organizationMemberSchema = z.object({
  id: z.number().int().positive(),
  user: z.object({
    id: z.number().int().positive(),
    name: z.string().min(1),
    email: z.email(),
    timezone: z.string().min(1),
  }),
  role: organizationRoleSchema,
  joined_at: z.string().nullable(),
})

export const organizationMembersResponseSchema = z.object({
  data: z.array(organizationMemberSchema),
})

export const organizationMemberResponseSchema = z.object({
  data: organizationMemberSchema,
})

export const addOrganizationMemberSchema = z.object({
  email: z.email('Enter a registered user email address.'),
  role: organizationRoleSchema,
})

export type OrganizationRole = z.infer<typeof organizationRoleSchema>
export type OrganizationMember = z.infer<typeof organizationMemberSchema>
export type AddOrganizationMemberValues = z.infer<
  typeof addOrganizationMemberSchema
>
