import { beforeEach, describe, expect, it } from 'vitest'
import { browserScopeStorage } from './scopeStorage'

describe('browserScopeStorage', () => {
  beforeEach(() => window.sessionStorage.clear())

  it('removes an outdated demo scope instead of returning invalid state', () => {
    window.sessionStorage.setItem(
      'releaselens.scope.v2',
      JSON.stringify({
        kind: 'demo',
        sessionId: 'demo-session',
        readOnly: true,
        organization: { id: 1, name: 'Northstar Engineering' },
        capabilities: {},
      }),
    )

    expect(browserScopeStorage.read()).toBeNull()
    expect(window.sessionStorage.getItem('releaselens.scope.v2')).toBeNull()
  })

  it('restores a valid connected workspace scope', () => {
    const connectedScope = {
      kind: 'connected' as const,
      organization: {
        id: 42,
        name: 'Platform Team',
        slug: 'platform-team',
        timezone: 'Asia/Manila',
      },
      role: 'owner' as const,
    }

    browserScopeStorage.write(connectedScope)

    expect(browserScopeStorage.read()).toEqual(connectedScope)
  })
})
