# Five-Minute Demo Script

## 0:00-0:30: Product and demo entry

Open the landing page. Explain that ReleaseLens identifies review-flow bottlenecks across repositories without ranking developers. Select **View Demo Workspace** and point out that no login or GitHub authorization is required.

## 0:30-1:30: Dashboard

Show the synthetic read-only banner, freshness timestamp, repository/date filters, median first-review and merge times, waiting count, opened-versus-merged trend, and distributions. Mention sample sizes and `N/A` behavior.

## 1:30-2:30: Traceability

Open **Waiting for review**. Confirm the explorer count matches the card, filters are preserved in the URL, and every row shows repository, age, size, review state, and explicit attention reasons.

## 2:30-3:15: Fair interpretation

Open the metric glossary. Show formulas, cohorts, exclusions, date basis, and limitations. Explain why bots, self-reviews, pending reviews, and drafts are treated deliberately and why no developer score exists.

## 3:15-4:00: Connected workflow

Sign in to a safe test workspace. Show role management, GitHub App status, selected repositories, **Sync now**, queued/running/completed states, and freshness. Explain that V1 uses manual and six-hour polling, not webhooks.

## 4:00-5:00: Engineering evidence

Show the architecture diagram, modular backend, repository interfaces, Zod response validation, deterministic analytics tests, tenant-isolation tests, GitHub client tests, CI workflow, and one-image multi-process deployment.

## Fallback

When free infrastructure is waking, use the committed screenshots and explain the cold-start state. Never demonstrate with an employer, client, or sensitive private repository.

