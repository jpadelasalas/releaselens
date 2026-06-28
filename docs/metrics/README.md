# Metric Definitions and Responsible Use

ReleaseLens describes repository and team workflow conditions. It does not rank developers or calculate productivity scores. Complexity, priority, team structure, time zones, leave, and incident work all affect pull-request metrics; use the results to improve systems, not judge individuals.

All durations use UTC source timestamps. Connected workspaces use current UTC for age; the deterministic demo uses its configured anchor. Active repository and date filters apply consistently to cards, charts, distributions, attention records, and drill-downs.

| Metric | Formula and cohort | Key exclusions and limitations |
| --- | --- | --- |
| Median time to first review | Median hours from PR creation to first qualifying submitted review for non-draft PRs created in range. | Excludes self, bot, pending, dismissed, and missing reviews. Creation is used because V1 lacks ready-for-review history. |
| Median time to merge | Median hours from creation to merge for PRs merged in range. | Excludes open and closed-unmerged PRs. Draft time remains included. |
| Waiting for first review | Open, non-draft PRs created in range with no qualifying review. | No assignment or team-availability context; new PRs can appear immediately. |
| Closed without merge | Closed PRs with no merge timestamp, grouped by closure date. | The reason for closure is unavailable and closure is not inherently negative. |
| Open PR age | Current open PRs bucketed at 24, 72, and 168 hours. | Includes drafts; age alone does not prove delay. |
| PR change size | Additions plus deletions, bucketed at 50, 200, and 500 lines. | Changed lines are only a proxy for complexity. |
| Opened versus merged | Weekly UTC counts by creation date and merge date. | A PR can open and merge in different periods. Volume is not productivity. |
| Attention count | Unique open PRs waiting for review, older than seven days, or over 500 changed lines. | Rules do not know capacity, priority, or planned pauses. Reasons remain explicit; this is not a hidden score. |

Duration cards publish sample sizes and display `N/A` for empty cohorts. Counts display zero when no records match. Each chart segment and applicable card links to the exact filtered pull-request cohort.

The in-product glossary at `/demo/metrics` is the user-facing source. Its typed definitions live in `frontend/src/features/metrics/metricDefinitions.ts`; analytics behavior is implemented in `backend/app/Modules/Analytics/Services/OrganizationAnalyticsService.php` and asserted against deterministic fixtures.

