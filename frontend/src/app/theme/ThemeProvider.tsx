import {
  type ReactNode,
  useEffect,
  useMemo,
  useState,
} from 'react'
import {
  readStoredThemeMode,
  storeThemeMode,
} from './themeStorage'
import { ThemeContext, type ThemeContextValue } from './themeContextInstance'
import type { ThemeMode } from './themeTypes'

type ThemeProviderProps = {
  children: ReactNode
  initialThemeMode?: ThemeMode
}

export function ThemeProvider({
  children,
  initialThemeMode,
}: ThemeProviderProps) {
  const [themeMode, setThemeMode] = useState<ThemeMode>(
    () => initialThemeMode ?? readStoredThemeMode(),
  )

  useEffect(() => {
    document.documentElement.dataset.theme = themeMode
    document.documentElement.style.colorScheme = themeMode
    storeThemeMode(themeMode)
  }, [themeMode])

  const value = useMemo<ThemeContextValue>(
    () => ({
      themeMode,
      toggleThemeMode() {
        setThemeMode((currentThemeMode) =>
          currentThemeMode === 'dark' ? 'light' : 'dark',
        )
      },
    }),
    [themeMode],
  )

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>
}
