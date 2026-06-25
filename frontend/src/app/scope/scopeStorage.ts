import type { AppScope, ScopeStorage } from './scopeTypes'

const scopeStorageKey = 'releaselens.scope'

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

    const storedScope = window.sessionStorage.getItem(scopeStorageKey)

    if (!storedScope) {
      return null
    }

    try {
      return JSON.parse(storedScope) as AppScope
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
