import { createContext } from 'react'
import type { ThemeMode } from './themeTypes'

export type ThemeContextValue = {
  themeMode: ThemeMode
  toggleThemeMode: () => void
}

export const ThemeContext = createContext<ThemeContextValue | null>(null)
