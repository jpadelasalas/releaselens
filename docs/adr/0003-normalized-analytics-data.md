# ADR 0003: Normalized Analytics Data

- Status: Accepted
- Date: 2026-06-19

## Context

The product needs explainable metrics, deterministic fixtures, drill-down reconciliation, and bounded storage. Full GitHub payload retention would increase sensitive-data and migration risk.

## Decision

Store normalized repositories, pull requests, GitHub users, submitted reviews, installations, and sync runs. Preserve source IDs and UTC timestamps. Compute V1 metrics from lifecycle records using shared backend domain logic instead of storing aggregate snapshots.

## Consequences

Metrics remain traceable to supporting pull requests and repeated synchronization is idempotent. V1 cannot reconstruct events it does not store, including historical ready-for-review transitions. Aggregates may be introduced after query plans show a measured need.

