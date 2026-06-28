# Security and Privacy Design

## Data handled

ReleaseLens stores account identity, organization membership, GitHub installation metadata, selected repository metadata, pull-request lifecycle fields, submitted reviews, and synchronization diagnostics. It does not ingest repository source code or expose installation tokens to React.

## Controls

- Laravel sessions use CSRF protection for authenticated mutations.
- Organization policies enforce Owner, Manager, and Viewer permissions server-side.
- Repository and pull-request queries include organization scope; cross-tenant behavior has feature tests.
- Demo scope is trusted server state and all demo mutations are rejected centrally with `DEMO_READ_ONLY`.
- GitHub state parameters expire, installation tokens are short lived, and the App requests only read-only Metadata and Pull requests access.
- Sensitive log fields are redacted; request correlation IDs support diagnosis without returning stack traces.
- Passwords use Laravel hashing, authentication is rate-limited, and production cookies must be secure and same-site.
- Synchronization is bounded, queued, locked, retried, and recorded as an operational run.

## Secrets

Never commit `.env`, a GitHub private key, installation token, database URL, cookie, or production log. Configure the private key through exactly one of `GITHUB_APP_PRIVATE_KEY_BASE64` or `GITHUB_APP_PRIVATE_KEY_PATH`. Production uses `APP_DEBUG=false`, `LOG_CHANNEL=stderr`, TLS database connections, and secure session cookies.

## Retention and disconnect

Disconnecting GitHub revokes future ReleaseLens access and disables synchronization. Imported analytics records remain as stale historical data in V1; deletion is not silently coupled to disconnect. The public demo contains deterministic fictional identities and repositories only.

## Reporting

Do not open a public issue containing secrets, private repository names, tokens, or personal data. Use the repository owner's private contact channel and include a minimal reproduction, affected version, impact, and correlation ID with sensitive values removed.

