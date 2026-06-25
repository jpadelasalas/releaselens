import { useThemeContext } from '../../app/theme/useThemeContext'

export function ThemeToggle() {
  const { themeMode, toggleThemeMode } = useThemeContext()
  const isDark = themeMode === 'dark'

  return (
    <button
      type="button"
      className="theme-toggle"
      onClick={toggleThemeMode}
      aria-pressed={isDark}
      aria-label={`Switch to ${isDark ? 'light' : 'dark'} mode`}
    >
      <span aria-hidden="true">{isDark ? 'Light' : 'Dark'}</span>
    </button>
  )
}
