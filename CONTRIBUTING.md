# Contributing to ReleaseLens

ReleaseLens is an independent portfolio project. Contributions must use synthetic or public test data and must not include employer, client, credential, private repository, or confidential material.

## Development workflow

1. Create a focused branch and keep changes within the relevant frontend feature or backend module.
2. Add tests for behavior, authorization, tenant scope, and failure states affected by the change.
3. Run the checks documented in the root `README.md`.
4. Update documentation or add a superseding ADR when behavior or architecture changes.
5. Open a pull request describing business value, acceptance criteria, security impact, and verification.

Prefer existing module contracts, feature hooks, API schemas, and UI patterns. Do not add a dependency or abstraction without a concrete reduction in complexity.

Report vulnerabilities privately according to `SECURITY.md`; do not place sensitive details in an issue or pull request.
