import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { useGitHubConnectionFeatureContext } from '../../../features/github-connection/useGitHubConnectionFeatureContext'
import { GitHubConnectionPanel } from './GitHubConnectionPanel'

vi.mock(
  '../../../features/github-connection/useGitHubConnectionFeatureContext',
  () => ({ useGitHubConnectionFeatureContext: vi.fn() }),
)

const mockedContext = vi.mocked(useGitHubConnectionFeatureContext)
const disconnect = vi.fn()

describe('GitHubConnectionPanel', () => {
  beforeEach(() => {
    disconnect.mockReset()
    disconnect.mockResolvedValue(undefined)
    mockedContext.mockReturnValue({
      connection: {
        status: 'active',
        account: { login: 'acme-engineering', type: 'Organization' },
        repository_selection: 'selected',
        permissions: { pull_requests: 'read' },
        connected_at: '2026-06-27T10:00:00Z',
        suspended_at: null,
      },
      canConnect: true,
      canDisconnect: true,
      isLoading: false,
      isSubmitting: false,
      error: null,
      connect: vi.fn(),
      disconnect,
      clearError: vi.fn(),
    })
  })

  it('requires confirmation before disconnecting', async () => {
    const user = userEvent.setup()
    render(
      <MemoryRouter>
        <GitHubConnectionPanel />
      </MemoryRouter>,
    )

    await user.click(screen.getByRole('button', { name: 'Disconnect' }))

    expect(
      screen.getByRole('heading', { name: 'Disconnect GitHub?' }),
    ).toBeInTheDocument()
    expect(disconnect).not.toHaveBeenCalled()

    const disconnectButtons = screen.getAllByRole('button', {
      name: 'Disconnect',
    })
    await user.click(disconnectButtons.at(-1)!)

    expect(disconnect).toHaveBeenCalledOnce()
  })
})
