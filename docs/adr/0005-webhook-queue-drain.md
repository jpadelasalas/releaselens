# ADR 0005: Webhook Queue Drain Uses the Existing Database Worker

- Status: Accepted
- Date: 2026-07-09

## Context

V2.0 introduces `ProcessWebhookDeliveryJob`, a queued job dispatched on every accepted webhook delivery. OQ2-001 in the V2 blueprint asks how queued webhook jobs will be drained on free hosting, since a continuously running background worker is not part of the free-service design on platforms such as Render.

## Decision

Webhook processing jobs use the same `database` queue connection and `default` queue as the existing V1 `SynchronizeRepositoryJob`, drained by the same persistent `queue:work` worker process already required for V1 polling (see ADR 0004 and the root `README.md` worker process). No separate queue, connection, or scheduled-drain mechanism was introduced for webhooks.

## Consequences

Webhook processing shares fate with sync processing: if the worker process is asleep or not running, both webhook jobs and scheduled/manual sync runs queue up until it resumes. This is the same free-tier limitation V1 already discloses, not a new one, and reconciliation (ADR-adjacent, see `SynchronizationService::reconcileEnabledRepositories()`) exists precisely to repair any drift accumulated while the worker was unavailable. No additional infrastructure, queue names, or scheduled-drain command were needed for V2.0.

If webhook volume ever justifies isolating it from sync-job contention, a dedicated queue name (e.g. `webhooks`) and a second `queue:work --queue=webhooks` process is the natural next step, deferred until there is evidence of a real bottleneck.
