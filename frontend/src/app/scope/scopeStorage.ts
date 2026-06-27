import type { AppScope, ScopeStorage } from './scopeTypes'

const scopeStorageKey = 'releaselens.scope.v2'
const legacyScopeStorageKey = 'releaselens.scope'

export function createMemoryScopeStorage(
  initialScope: AppScope | null = null,
): ScopeStorage {
  let storedScope = initialScope

  return {
    read: () => storedScope,
    write: (scope) => {
      storedScope = scope
    },
    clear: () => {
      storedScope = null
    },
  }
}

export const browserScopeStorage: ScopeStorage = {
  read() {
    if (typeof window === 'undefined') {
      return null
    }

    window.sessionStorage.removeItem(legacyScopeStorageKey)

    const storedScope = window.sessionStorage.getItem(scopeStorageKey)

    if (!storedScope) {
      return null
    }

    try {
      const parsedScope: unknown = JSON.parse(storedScope)

      if (!isAppScope(parsedScope)) {
        window.sessionStorage.removeItem(scopeStorageKey)
        return null
      }

      return parsedScope
    } catch {
      window.sessionStorage.removeItem(scopeStorageKey)
      return null
    }
  },
  write(scope) {
    if (typeof window === 'undefined') {
      return
    }

    window.sessionStorage.setItem(scopeStorageKey, JSON.stringify(scope))
  },
  clear() {
    if (typeof window === 'undefined') {
      return
    }

    window.sessionStorage.removeItem(scopeStorageKey)
  },
}

function isAppScope(value: unknown): value is AppScope {
  if (!isRecord(value) || typeof value.kind !== 'string') {
    return false
  }

  if (value.kind === 'anonymous') {
    return true
  }

  if (value.kind === 'connected') {
    return isRecord(value.organization) &&
      typeof value.organization.id === 'number' &&
      typeof value.organization.name === 'string' &&
      typeof value.organization.slug === 'string' &&
      typeof value.organization.timezone === 'string' &&
      ['owner', 'manager', 'viewer'].includes(String(value.role))
  }

  return value.kind === 'demo' &&
    typeof value.sessionId === 'string' &&
    value.readOnly === true &&
    isRecord(value.organization) &&
    typeof value.organization.id === 'number' &&
    typeof value.organization.name === 'string' &&
    isRecord(value.capabilities) &&
    isRecord(value.demo) &&
    typeof value.demo.anchor_date === 'string'
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null
}
