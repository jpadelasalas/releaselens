export type MetricDefinition = {
  id: string
  name: string
  category: 'Duration' | 'Current state' | 'Distribution' | 'Flow'
  summary: string
  formula: string
  cohort: string
  dateBasis: string
  exclusions: string[]
  sampleSize: string
  interpretation: string
  limitations: string[]
}

export const metricDefinitions: MetricDefinition[] = [
  {
    id: 'median-first-review',
    name: 'Median time to first review',
    category: 'Duration',
    summary: 'Typical elapsed time before a pull request receives its first qualifying review.',
    formula:
      'Median of first qualifying review submitted at minus pull request created at, measured in UTC hours.',
    cohort:
      'Non-draft pull requests created in the selected range with at least one qualifying review.',
    dateBasis: 'Pull request created timestamp.',
    exclusions: [
      'Draft pull requests',
      'Self-reviews and bot reviews',
      'Pending or dismissed reviews',
      'Pull requests without a qualifying review',
    ],
    sampleSize: 'The dashboard displays the number of qualifying reviewed pull requests. Empty cohorts show N/A.',
    interpretation:
      'Lower values generally indicate a faster initial response, but complexity and team context still matter.',
    limitations: [
      'Uses creation time because ready-for-review history is not stored in V1.',
      'The median hides the shape and extremes of the full distribution.',
    ],
  },
  {
    id: 'median-merge-time',
    name: 'Median time to merge',
    category: 'Duration',
    summary: 'Typical elapsed time from pull-request creation until merge.',
    formula:
      'Median of merged at minus pull request created at, measured in UTC hours.',
    cohort: 'Pull requests whose merge timestamp is in the selected range.',
    dateBasis: 'Pull request merged timestamp; the duration begins at created timestamp.',
    exclusions: ['Open pull requests', 'Closed pull requests that were not merged'],
    sampleSize: 'The dashboard displays the merged pull-request count. Empty cohorts show N/A.',
    interpretation:
      'Lower values can indicate smoother delivery flow, while the median limits distortion from old outliers.',
    limitations: [
      'Draft time is included.',
      'Ready-for-review history is not stored, so the duration includes all time since creation.',
    ],
  },
  {
    id: 'waiting-for-review',
    name: 'Waiting for first review',
    category: 'Current state',
    summary: 'Open pull requests that have not received a qualifying human review.',
    formula: 'Count of open, non-draft pull requests with no qualifying submitted review.',
    cohort: 'Open, non-draft pull requests created in the selected range.',
    dateBasis: 'Pull request created timestamp plus current state at the demo anchor date.',
    exclusions: ['Draft pull requests', 'Self-reviews and bot reviews', 'Pending or dismissed reviews'],
    sampleSize: 'Displayed as an exact count; zero means no records match the active filters.',
    interpretation: 'An action metric for work that may need a reviewer assignment or follow-up.',
    limitations: [
      'V1 has no review-request or team-availability context.',
      'No grace period is applied, so newly opened pull requests can appear immediately.',
    ],
  },
  {
    id: 'closed-without-merge',
    name: 'Closed without merge',
    category: 'Flow',
    summary: 'Pull requests that were closed without producing a merge.',
    formula: 'Count where state is closed and merged at is empty.',
    cohort: 'Unmerged pull requests whose closed timestamp is in the selected range.',
    dateBasis: 'Pull request closed timestamp.',
    exclusions: ['Open pull requests', 'Merged pull requests'],
    sampleSize: 'Displayed as an exact count; zero means no records match the active filters.',
    interpretation:
      'Useful context for abandoned or superseded work, but closing without merge is not inherently negative.',
    limitations: [
      'The reason for closing is not available.',
      'The count provides context but does not distinguish abandoned work from intentional closure.',
    ],
  },
  {
    id: 'open-pr-age',
    name: 'Open pull-request age',
    category: 'Distribution',
    summary: 'Current open pull requests grouped by elapsed time since creation.',
    formula: 'Demo anchor timestamp minus pull request created timestamp, measured in UTC hours.',
    cohort: 'Open pull requests created in the selected range, including drafts.',
    dateBasis: 'Pull request created timestamp and the deterministic demo anchor date.',
    exclusions: ['Closed and merged pull requests'],
    sampleSize: 'Every matching open pull request contributes to exactly one bucket.',
    interpretation: 'Older buckets help reveal work that may be stalled or awaiting a decision.',
    limitations: [
      'Age alone does not indicate a problem.',
      'Exact boundaries are: up to 24h, over 24h to 72h, over 72h to 168h, and over 168h.',
    ],
  },
  {
    id: 'pr-size',
    name: 'Pull-request change size',
    category: 'Distribution',
    summary: 'Pull requests grouped by total added and deleted lines.',
    formula: 'Additions plus deletions.',
    cohort: 'Pull requests created in the selected range.',
    dateBasis: 'Pull request created timestamp.',
    exclusions: [],
    sampleSize: 'Every matching pull request contributes to one size bucket.',
    interpretation:
      'Larger changes can be harder to review, but generated files and refactors may legitimately be large.',
    limitations: [
      'Changed lines are an imperfect proxy for complexity.',
      'Buckets are 0-50, 51-200, 201-500, and over 500 changed lines.',
    ],
  },
  {
    id: 'opened-versus-merged',
    name: 'Opened versus merged',
    category: 'Flow',
    summary: 'Weekly opened and merged activity shown as two comparable series.',
    formula: 'Count pull requests by creation week and merged pull requests by merge week.',
    cohort: 'Opened and merged events that occur in the selected range.',
    dateBasis: 'UTC week beginning Monday.',
    exclusions: ['Unmerged pull requests from the merged series'],
    sampleSize: 'Each pull request appears once in opened and, if merged, once in merged.',
    interpretation:
      'Sustained opened volume above merged volume can suggest accumulating work.',
    limitations: [
      'A pull request can open in one selected period and merge in another.',
      'Counts describe flow volume, not developer productivity.',
    ],
  },
  {
    id: 'attention-count',
    name: 'Attention count',
    category: 'Current state',
    summary: 'Unique open pull requests matching one or more explicit attention rules.',
    formula:
      'Union of waiting for first review, open longer than seven days, and changes over 500 lines.',
    cohort: 'Open pull requests created in the selected range.',
    dateBasis: 'Pull request created timestamp plus current state at the demo anchor date.',
    exclusions: ['Drafts from waiting and stale rules', 'Pull requests matching no attention rule'],
    sampleSize: 'Each pull request is counted once even when it has multiple reason codes.',
    interpretation:
      'A triage list with visible reasons, not a hidden health or performance score.',
    limitations: [
      'Rules do not know team capacity, priority, or planned pauses.',
      'A draft can still appear when it exceeds the large-change threshold.',
    ],
  },
]

export function getMetricDefinitionUrl(id: string): string {
  return `/demo/metrics#${id}`
}
