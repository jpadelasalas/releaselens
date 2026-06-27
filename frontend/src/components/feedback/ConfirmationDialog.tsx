import { useEffect, useId, useRef } from 'react'

type ConfirmationDialogProps = {
  open: boolean
  title: string
  description: string
  confirmLabel: string
  isPending?: boolean
  tone?: 'default' | 'danger'
  onConfirm: () => void
  onCancel: () => void
}

export function ConfirmationDialog({
  open,
  title,
  description,
  confirmLabel,
  isPending = false,
  tone = 'default',
  onConfirm,
  onCancel,
}: ConfirmationDialogProps) {
  const dialogRef = useRef<HTMLDialogElement>(null)
  const titleId = useId()
  const descriptionId = useId()

  useEffect(() => {
    const dialog = dialogRef.current

    if (!dialog) {
      return
    }

    if (open && !dialog.open) {
      if (typeof dialog.showModal === 'function') {
        dialog.showModal()
      } else {
        dialog.setAttribute('open', '')
      }
    } else if (!open && dialog.open) {
      if (typeof dialog.close === 'function') {
        dialog.close()
      } else {
        dialog.removeAttribute('open')
      }
    }
  }, [open])

  return (
    <dialog
      ref={dialogRef}
      className="confirmation-dialog m-auto w-[min(92vw,460px)] rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-solid)] p-0 text-[var(--color-text)] shadow-2xl"
      aria-labelledby={titleId}
      aria-describedby={descriptionId}
      onCancel={(event) => {
        event.preventDefault()

        if (!isPending) {
          onCancel()
        }
      }}
    >
      <div className="p-6">
        <h2 id={titleId} className="text-xl text-[var(--color-heading)]">
          {title}
        </h2>
        <p id={descriptionId} className="mt-2 text-sm text-[var(--color-muted)]">
          {description}
        </p>
      </div>
      <div className="flex flex-col-reverse gap-2 border-t border-[var(--color-border)] bg-[var(--color-page-alt)] px-6 py-4 sm:flex-row sm:justify-end">
        <button
          className="min-h-10 rounded-md border border-[var(--color-border-strong)] px-4 text-sm font-bold text-[var(--color-heading)]"
          type="button"
          disabled={isPending}
          onClick={onCancel}
          autoFocus
        >
          Cancel
        </button>
        <button
          className={`min-h-10 rounded-md px-4 text-sm font-bold ${
            tone === 'danger'
              ? 'bg-[var(--color-danger)] text-white'
              : 'bg-[var(--color-primary)] text-[var(--color-on-primary)]'
          }`}
          type="button"
          disabled={isPending}
          onClick={onConfirm}
        >
          {isPending ? 'Working...' : confirmLabel}
        </button>
      </div>
    </dialog>
  )
}
