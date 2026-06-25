import type { DemoSession } from '../../features/demo-session/demoSessionApi'

export type AnonymousScope = {
  kind: 'anonymous'
}

export type DemoScope = {
  kind: 'demo'
  sessionId: string
  readOnly: true
  organization: DemoSession['organization']
  capabilities: DemoSession['capabilities']
}

export type AppScope = AnonymousScope | DemoScope

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
  }
}
