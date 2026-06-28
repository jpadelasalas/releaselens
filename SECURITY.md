# Security Policy

## Supported version

Security fixes are applied to the current `main` branch during V1 development.

## Reporting a vulnerability

Do not publish secrets, private repository metadata, personal data, or exploit details in a public issue. Contact the repository owner privately and include:

- The affected route, component, and revision.
- Reproduction steps with synthetic data.
- Expected and observed behavior.
- Security and tenant impact.
- A request correlation ID when available.

Remove authorization headers, cookies, passwords, GitHub tokens, private keys, database URLs, and private account/repository names. You should receive an acknowledgement within seven days; disclosure timing will be coordinated after a fix is available.

## Scope priorities

Reports involving cross-organization access, authentication or authorization bypass, GitHub credential exposure, demo-to-connected data leakage, unsafe redirects, CSRF, injection, or sensitive logging receive priority.

Architecture controls and data handling are documented in [docs/security/README.md](docs/security/README.md).
