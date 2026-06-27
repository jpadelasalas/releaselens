import { z } from 'zod'

export const authUserSchema = z.object({
  id: z.number().int().positive(),
  name: z.string().min(1),
  email: z.email(),
  timezone: z.string().min(1),
})

const organizationMembershipSchema = z.object({
  organization: z.object({
    id: z.number().int().positive(),
    name: z.string().min(1),
    slug: z.string().min(1),
    timezone: z.string().min(1),
  }),
  role: z.enum(['owner', 'manager', 'viewer']),
})

export const authSessionSchema = z.object({
  user: authUserSchema,
  memberships: z.array(organizationMembershipSchema),
  active_organization_id: z.number().int().positive().nullable(),
})

export const authSessionResponseSchema = z.object({
  data: authSessionSchema,
})

export const signInSchema = z.object({
  email: z.email('Enter a valid email address.'),
  password: z.string().min(1, 'Password is required.'),
})

export const registerSchema = z
  .object({
    name: z.string().trim().min(1, 'Name is required.').max(120),
    email: z.email('Enter a valid email address.'),
    password: z
      .string()
      .min(12, 'Use at least 12 characters.')
      .regex(/[A-Za-z]/, 'Include at least one letter.')
      .regex(/[0-9]/, 'Include at least one number.'),
    password_confirmation: z.string(),
    timezone: z.string().min(1),
  })
  .refine((values) => values.password === values.password_confirmation, {
    message: 'Passwords do not match.',
    path: ['password_confirmation'],
  })

export type AuthUser = z.infer<typeof authUserSchema>
export type AuthSession = z.infer<typeof authSessionSchema>
export type SignInValues = z.infer<typeof signInSchema>
export type RegisterValues = z.infer<typeof registerSchema>
