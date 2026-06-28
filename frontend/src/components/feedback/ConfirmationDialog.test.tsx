import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'

import { ConfirmationDialog } from './ConfirmationDialog'

describe('ConfirmationDialog', () => {
  it('requires an explicit confirmation', async () => {
    const user = userEvent.setup()
    const onCancel = vi.fn()
    const onConfirm = vi.fn()

    render(
      <ConfirmationDialog
        open
        title="Remove member?"
        description="Their workspace access will be revoked."
        confirmLabel="Remove Member"
        tone="danger"
        onCancel={onCancel}
        onConfirm={onConfirm}
      />,
    )

    expect(screen.getByRole('dialog')).toBeVisible()
    await user.click(screen.getByRole('button', { name: 'Cancel' }))
    expect(onCancel).toHaveBeenCalledOnce()
    expect(onConfirm).not.toHaveBeenCalled()

    await user.click(screen.getByRole('button', { name: 'Remove Member' }))
    expect(onConfirm).toHaveBeenCalledOnce()
  })

  it('keeps the same controls mounted when its content changes', () => {
    const props = {
      open: true,
      title: 'Change member role?',
      confirmLabel: 'Change Role',
      onCancel: vi.fn(),
      onConfirm: vi.fn(),
    }
    const { rerender } = render(
      <ConfirmationDialog {...props} description="Sam will become a manager." />,
    )
    const confirmButton = screen.getByRole('button', { name: 'Change Role' })

    rerender(
      <ConfirmationDialog {...props} description="Alex will become a manager." />,
    )

    expect(screen.getByText('Alex will become a manager.')).toBeVisible()
    expect(screen.getByRole('button', { name: 'Change Role' })).toBe(
      confirmButton,
    )
  })
})
