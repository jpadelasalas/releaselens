# ADR 0001: Modular Monolith

- Status: Accepted
- Date: 2026-06-19

## Context

V1 needs clear ownership boundaries for identity, organizations, GitHub integration, repositories, synchronization, pull requests, and analytics, but it does not need independent scaling or deployment.

## Decision

Use one React application and one Laravel application. Organize Laravel by domain module with controllers, services, repository interfaces, and repository implementations. Keep frontend API/schema/query logic in feature folders and compose page behavior through feature contexts.

## Consequences

Transactions, authorization, local development, and deployment remain simple. Module contracts remain testable without the network and operational overhead of microservices. A future extraction requires evidence from load or team ownership, not portfolio signaling.

