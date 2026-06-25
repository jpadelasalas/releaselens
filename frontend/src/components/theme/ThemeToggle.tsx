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
      <span className="theme-toggle__track" aria-hidden="true">
        <span className="theme-toggle__icon theme-toggle__icon--sun" />
        <span className="theme-toggle__icon theme-toggle__icon--moon" />
        <span className="theme-toggle__thumb" />
      </span>
    </button>
  )
}
