import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { useRepositoryManagementContext } from '../../../features/repositories/useRepositoryManagementContext'
import { useSynchronizationContext } from '../../../features/synchronization/useSynchronizationContext'
import { RepositoryManagementPanel } from './RepositoryManagementPanel'

vi.mock(
  '../../../features/repositories/useRepositoryManagementContext',
  () => ({ useRepositoryManagementContext: vi.fn() }),
)
vi.mock(
  '../../../features/synchronization/useSynchronizationContext',
  () => ({ useSynchronizationContext: vi.fn() }),
)

const mockedContext = vi.mocked(useRepositoryManagementContext)
const mockedSyncContext = vi.mocked(useSynchronizationContext)
const saveSelection = vi.fn()
const changeMonitoring = vi.fn()

describe('RepositoryManagementPanel', () => {
  beforeEach(() => {
    saveSelection.mockReset()
    saveSelection.mockResolvedValue(undefined)
    changeMonitoring.mockReset()
    changeMonitoring.mockResolvedValue(undefined)
    mockedContext.mockReturnValue({
      repositories: [
        {
          id: 1,
          github_repository_id: 101,
          name: 'api',
          full_name: 'acme/api',
          description: null,
          visibility: 'private',
          default_branch: 'main',
          html_url: 'https://github.com/acme/api',
          is_archived: false,
          sync_enabled: true,
          sync_status: 'never_synced',
          last_sync_at: null,
          last_successful_sync_at: null,
        },
      ],
      availableRepositories: [
        {
          github_repository_id: 101,
          name: 'api',
          full_name: 'acme/api',
          description: null,
          visibility: 'private',
          default_branch: 'main',
          html_url: 'https://github.com/acme/api',
          is_archived: false,
          is_monitored: true,
        },
      ],
      filteredRepositories: [
        {
          github_repository_id: 101,
          name: 'api',
          full_name: 'acme/api',
          description: null,
          visibility: 'private',
          default_branch: 'main',
          html_url: 'https://github.com/acme/api',
          is_archived: false,
          is_monitored: true,
        },
      ],
      selectedRepositoryIds: [101],
      search: '',
      canManage: true,
      hasActiveConnection: true,
      isLoading: false,
      isSaving: false,
      error: null,
      setSearch: vi.fn(),
      toggleSelection: vi.fn(),
      saveSelection,
      changeMonitoring,
      refreshAvailable: vi.fn().mockResolvedValue(undefined),
      clearError: vi.fn(),
    })
    mockedSyncContext.mockReturnValue({
      activeRepositoryId: null,
      runs: [],
      canSync: true,
      isLoadingHistory: false,
      isRequesting: false,
      error: null,
      requestSync: vi.fn().mockResolvedValue(undefined),
      showHistory: vi.fn(),
      closeHistory: vi.fn(),
      refreshHistory: vi.fn().mockResolvedValue(undefined),
      clearError: vi.fn(),
    })
  })

  it('saves repository selection and toggles monitoring', async () => {
    const user = userEvent.setup()
    render(<RepositoryManagementPanel />)

    await user.click(screen.getByRole('button', { name: 'Save selection' }))
    const monitoring = screen.getByRole('checkbox', { name: 'Monitoring' })
    await user.click(monitoring)

    expect(saveSelection).toHaveBeenCalledOnce()
    expect(changeMonitoring).toHaveBeenCalledWith(1, false)
  })
})
