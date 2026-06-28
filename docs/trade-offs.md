# V1 Trade-offs and Limitations

| Choice | Benefit | Cost / limitation | Revisit when |
| --- | --- | --- | --- |
| Polling instead of webhooks | Smaller, observable V1 reliability surface. | Data is not real-time; worker and scheduler availability affect freshness. | V2 adds signed, deduplicated webhooks plus reconciliation. |
| 90-day / 200-PR import bound | Protects rate limits, storage, and free infrastructure. | Older history may be absent. | Measured customer need and capacity justify configurable retention. |
| GitHub REST API | Explicit endpoints and mature documentation. | Review import can require multiple requests. | Profiling shows GraphQL materially reduces cost. |
| Lifecycle metrics computed on request | Traceable and simple for bounded data. | Query cost grows with dataset size. | Query plans or p95 latency justify cached aggregates. |
| PR creation as review clock start | Available and deterministic. | Includes draft time and cannot model ready-for-review transitions. | Webhook/event history is introduced. |
| Additions + deletions as size | Understandable and available. | Generated files and refactors distort complexity. | A better explainable source signal is available. |
| Retain data after disconnect | Prevents surprising analytics loss. | Historical private metadata remains until explicit deletion exists. | Owner-controlled retention/deletion is designed. |
| Single-origin deployment | Simpler cookies, CSRF, CORS, and GitHub callback. | Frontend and API release together. | Independent scaling or ownership becomes valuable. |
| Database queue | Minimal infrastructure and inspectable jobs. | A persistent worker is still required; free hosts may pause it. | Hosted queue reliability becomes a measured issue. |
| No individual score | Avoids misleading surveillance and supports responsible use. | Cannot answer individual performance questions. | This is a product principle, not a planned limitation to remove. |

Known free-tier behavior is visible to users: services can cold start, scheduled work can be delayed, and freshness is the authority for how current analytics are.

