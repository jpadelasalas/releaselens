import { renderHook } from '@testing-library/react'
import { describe, expect, it } from 'vitest'

import { usePullRequestExplorerData } from './usePullRequestExplorerContext'

describe('pull request explorer feature context', () => {
  it('requires the explorer provider', () => {
    expect(() => renderHook(() => usePullRequestExplorerData())).toThrow(
      'Pull request explorer hooks must be used within PullRequestExplorerProvider.',
    )
  })
})
