import { z } from 'zod'

export const analyticsFiltersSchema = z.object({
  repository_ids: z.array(z.number().int().positive()).optional(),
  date_from: z.string().nullable().optional(),
  date_to: z.string().nullable().optional(),
})

const analyticsMetaSchema = z.object({
  applied_filters: analyticsFiltersSchema,
  selected_repository_count: z.number().int().nonnegative(),
  demo_freshness_at: z.string().nullable(),
})

export const analyticsSummarySchema = analyticsMetaSchema.extend({
  metrics: z.object({
    median_first_review_hours: z.number().nonnegative().nullable(),
    median_first_review_sample_size: z.number().int().nonnegative(),
    median_merge_hours: z.number().nonnegative().nullable(),
    median_merge_sample_size: z.number().int().nonnegative(),
    waiting_for_first_review: z.number().int().nonnegative(),
    closed_without_merge: z.number().int().nonnegative(),
    attention_count: z.number().int().nonnegative(),
  }),
})

const analyticsBucketSchema = z.object({
  key: z.string().min(1),
  label: z.string().min(1),
  count: z.number().int().nonnegative(),
})

export const analyticsTrendsSchema = analyticsMetaSchema.extend({
  series: z.object({
    opened_vs_merged_by_week: z.array(
      z.object({
        week: z.string().min(1),
        opened: z.number().int().nonnegative(),
        merged: z.number().int().nonnegative(),
      }),
    ),
  }),
})

export const analyticsDistributionsSchema = analyticsMetaSchema.extend({
  buckets: z.object({
    open_pr_age: z.array(analyticsBucketSchema),
    pr_size: z.array(analyticsBucketSchema),
  }),
})

export const analyticsAttentionSchema = analyticsMetaSchema.extend({
  records: z.array(
    z.object({
      pull_request_id: z.number().int().positive(),
      repository: z.string().min(1),
      number: z.number().int().positive(),
      title: z.string().min(1),
      author: z.string().nullable(),
      age_hours: z.number().int().nonnegative(),
      change_size: z.number().int().nonnegative(),
      reasons: z.array(z.string().min(1)),
    }),
  ),
})

export const analyticsSummaryResponseSchema = z.object({
  data: analyticsSummarySchema,
})

export const analyticsTrendsResponseSchema = z.object({
  data: analyticsTrendsSchema,
})

export const analyticsDistributionsResponseSchema = z.object({
  data: analyticsDistributionsSchema,
})

export const analyticsAttentionResponseSchema = z.object({
  data: analyticsAttentionSchema,
})

export type AnalyticsFilters = z.infer<typeof analyticsFiltersSchema>
export type AnalyticsSummary = z.infer<typeof analyticsSummarySchema>
export type AnalyticsTrends = z.infer<typeof analyticsTrendsSchema>
export type AnalyticsDistributions = z.infer<typeof analyticsDistributionsSchema>
export type AnalyticsBucket = z.infer<typeof analyticsBucketSchema>
export type AnalyticsAttention = z.infer<typeof analyticsAttentionSchema>
export type AnalyticsAttentionRecord = AnalyticsAttention['records'][number]
