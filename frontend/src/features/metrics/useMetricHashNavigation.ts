import { useEffect } from 'react'
import { useLocation } from 'react-router-dom'

export function useMetricHashNavigation() {
  const { hash } = useLocation()

  useEffect(() => {
    if (!hash) {
      return
    }

    const frame = window.requestAnimationFrame(() => {
      const target = document.getElementById(decodeURIComponent(hash.slice(1)))

      target?.scrollIntoView({ block: 'start' })
      target?.focus({ preventScroll: true })
    })

    return () => window.cancelAnimationFrame(frame)
  }, [hash])
}
