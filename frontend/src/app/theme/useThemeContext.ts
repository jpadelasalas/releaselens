import { useContext } from 'react'
import {
  ThemeContext,
  type ThemeContextValue,
} from './themeContextInstance'

export function useThemeContext(): ThemeContextValue {
  const context = useContext(ThemeContext)

  if (context === null) {
    throw new Error('useThemeContext must be used within ThemeProvider.')
  }

  return context
}
