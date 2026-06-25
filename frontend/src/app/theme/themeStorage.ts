import { defaultThemeMode, type ThemeMode } from './themeTypes'

const themeStorageKey = 'releaselens.theme'

function isThemeMode(value: string | null): value is ThemeMode {
  return value === 'light' || value === 'dark'
}

export function readStoredThemeMode(): ThemeMode {
  if (typeof window === 'undefined') {
    return defaultThemeMode
  }

  const storedTheme = window.localStorage.getItem(themeStorageKey)

  return isThemeMode(storedTheme) ? storedTheme : defaultThemeMode
}

export function storeThemeMode(themeMode: ThemeMode): void {
  if (typeof window === 'undefined') {
    return
  }

  window.localStorage.setItem(themeStorageKey, themeMode)
}
