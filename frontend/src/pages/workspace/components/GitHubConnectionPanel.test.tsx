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
const refresh = vi.fn()
let contextValue: ReturnType<typeof useGitHubConnectionFeatureContext>

describe('GitHubConnectionPanel', () => {
  beforeEach(() => {
    disconnect.mockReset()
    disconnect.mockResolvedValue(undefined)
    refresh.mockReset()
    refresh.mockResolvedValue(undefined)
    contextValue = {
      connection: {
        status: 'active',
        verification_status: 'verified',
        account: { login: 'acme-engineering', type: 'Organization' },
        repository_selection: 'selected',
        permissions: { pull_requests: 'read' },
        connected_at: '2026-06-27T10:00:00Z',
        suspended_at: null,
      },
      canConnect: true,
      canDisconnect: true,
      isLoading: false,
      isRefreshing: false,
      isSubmitting: false,
      error: null,
      connect: vi.fn(),
      disconnect,
      refresh,
      clearError: vi.fn(),
    }
    mockedContext.mockReturnValue(contextValue)
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

  it('refreshes live GitHub connection metadata on demand', async () => {
    const user = userEvent.setup()
    render(
      <MemoryRouter>
        <GitHubConnectionPanel />
      </MemoryRouter>,
    )

    await user.click(screen.getByRole('button', { name: 'Refresh' }))

    expect(refresh).toHaveBeenCalledOnce()
  })

  it('offers reconnection after a remote uninstall', () => {
    mockedContext.mockReturnValue({
      ...contextValue,
      connection: {
        ...contextValue.connection!,
        status: 'disconnected',
      },
    })

    render(
      <MemoryRouter>
        <GitHubConnectionPanel />
      </MemoryRouter>,
    )

    expect(screen.getByText('Disconnected')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reconnect' })).toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'Disconnect' }),
    ).not.toBeInTheDocument()
  })
})
