# ADR 0002: GitHub App With Bounded Polling

- Status: Accepted
- Date: 2026-06-19

## Context

ReleaseLens needs read-only access to explicitly selected repositories. Free hosting and V1 scope favor a synchronization model that is observable and recoverable without a webhook delivery system.

## Decision

Use a GitHub App with read-only Metadata and Pull requests permissions. Keep ReleaseLens login separate from GitHub installation. Import through manual and six-hour scheduled polling, bounded to 90 days or 200 pull requests per repository.

## Consequences

Repository selection and short-lived installation tokens reduce access risk. Data is not real-time, so freshness and run status must remain visible. Webhook signature validation, delivery deduplication, and replay are deferred to V2.

