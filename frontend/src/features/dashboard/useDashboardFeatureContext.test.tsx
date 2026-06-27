import { renderHook } from '@testing-library/react'
import { describe, expect, it } from 'vitest'

import { useDashboardWorkspace } from './useDashboardFeatureContext'

describe('dashboard feature context', () => {
  it('requires the dashboard provider', () => {
    expect(() => renderHook(() => useDashboardWorkspace())).toThrow(
      'Dashboard feature hooks must be used within DashboardFeatureProvider.',
    )
  })
})
