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
})
