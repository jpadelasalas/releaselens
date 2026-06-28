# ADR 0004: Single-Origin Deployment

- Status: Accepted
- Date: 2026-06-28

## Context

A split SPA and API deployment adds cross-origin cookies, CSRF, CORS, callback, and local-versus-hosted configuration. V1 also needs persistent worker and scheduler roles.

## Decision

Build React in a multi-stage Docker image and serve its static assets from the Laravel Apache runtime. Run the same image as separate web, queue-worker, and scheduler processes. Use PostgreSQL and a release migration command.

## Consequences

Authentication uses same-origin secure cookies and the GitHub callback has one canonical host. Web, worker, and scheduler must all receive compatible environment variables. A split deployment remains possible, but requires a deliberate security and operations review.

