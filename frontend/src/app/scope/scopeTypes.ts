import type { DemoSession } from '../../features/demo-session/demoSessionApi'
import type { AuthSession } from '../../features/auth/authSchemas'

export type AnonymousScope = {
  kind: 'anonymous'
}

export type DemoScope = {
  kind: 'demo'
  sessionId: string
  readOnly: true
  organization: DemoSession['organization']
  capabilities: DemoSession['capabilities']
  demo: DemoSession['demo']
}

export type ConnectedScope = {
  kind: 'connected'
  organization: AuthSession['memberships'][number]['organization']
  role: AuthSession['memberships'][number]['role']
}

export type AppScope = AnonymousScope | DemoScope | ConnectedScope
export type WorkspaceScope = DemoScope | ConnectedScope

export type ScopeStorage = {
  read: () => AppScope | null
  write: (scope: AppScope) => void
  clear: () => void
}

export const anonymousScope: AnonymousScope = {
  kind: 'anonymous',
}

export function createDemoScope(demoSession: DemoSession): DemoScope {
  return {
    kind: 'demo',
    sessionId: demoSession.session.id,
    readOnly: true,
    organization: demoSession.organization,
    capabilities: demoSession.capabilities,
    demo: demoSession.demo,
  }
}

export function createConnectedScope(
  membership: AuthSession['memberships'][number],
): ConnectedScope {
  return {
    kind: 'connected',
    organization: membership.organization,
    role: membership.role,
  }
}
