import DeleteOutlineOutlinedIcon from '@mui/icons-material/DeleteOutlineOutlined'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useState } from 'react'

import { ConfirmationDialog } from '../../../components/feedback/ConfirmationDialog'
import {
  addOrganizationMemberSchema,
  type AddOrganizationMemberValues,
  type OrganizationMember,
  type OrganizationRole,
} from '../../../features/organizations/organizationSchemas'
import { useOrganizationFeatureContext } from '../../../features/organizations/useOrganizationFeatureContext'

export function WorkspaceMembersPanel() {
  const [pendingRemoval, setPendingRemoval] =
    useState<OrganizationMember | null>(null)
  const [pendingRoleChange, setPendingRoleChange] = useState<{
    member: OrganizationMember
    role: OrganizationRole
  } | null>(null)
  const {
    members,
    canManageMembers,
    isLoadingMembers,
    isSubmitting,
    memberError,
    addMember,
    changeMemberRole,
    removeMember,
    clearMemberError,
  } = useOrganizationFeatureContext()
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<AddOrganizationMemberValues>({
    resolver: zodResolver(addOrganizationMemberSchema),
    defaultValues: { email: '', role: 'viewer' },
  })
  const onAddMember = handleSubmit(async (values) => {
    clearMemberError()

    try {
      await addMember(values)
      reset({ email: '', role: values.role })
    } catch {
      // Mutation state exposes the controlled API error.
    }
  })

  async function confirmRoleChange() {
    if (!pendingRoleChange) {
      return
    }

    clearMemberError()

    try {
      await changeMemberRole(
        pendingRoleChange.member.id,
        pendingRoleChange.role,
      )
    } catch {
      // Mutation state exposes the controlled policy error.
    } finally {
      setPendingRoleChange(null)
    }
  }

  async function confirmRemoveMember() {
    if (!pendingRemoval) {
      return
    }

    clearMemberError()

    try {
      await removeMember(pendingRemoval.id)
    } catch {
      // Mutation state exposes the controlled policy error.
    } finally {
      setPendingRemoval(null)
    }
  }

  if (!canManageMembers) {
    return null
  }

  return (
    <section className="mt-10 border-t border-[var(--color-border)] pt-8" aria-labelledby="workspace-members-title">
      <div>
        <p className="eyebrow">Owner controls</p>
        <h2 id="workspace-members-title" className="mt-1 text-2xl text-[var(--color-heading)]">
          Workspace members
        </h2>
        <p className="mt-1 text-[var(--color-muted)]">
          Owners manage access. Managers configure integrations, while Viewers have read-only analytics access.
        </p>
      </div>

      <form
        className="mt-5 grid gap-3 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4 md:grid-cols-[minmax(220px,1fr)_160px_auto] md:items-end"
        onSubmit={onAddMember}
        noValidate
      >
        <label className="grid gap-1 text-sm font-bold text-[var(--color-heading)]" htmlFor="member-email">
          Registered user email
          <input
            {...register('email')}
            id="member-email"
            type="email"
            autoComplete="email"
            aria-invalid={Boolean(errors.email)}
            className="min-h-11 rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface-solid)] px-3 font-normal text-[var(--color-text)]"
          />
          {errors.email && (
            <span className="font-normal text-red-700">{errors.email.message}</span>
          )}
        </label>
        <label className="grid gap-1 text-sm font-bold text-[var(--color-heading)]" htmlFor="member-role">
          Role
          <select
            {...register('role')}
            id="member-role"
            className="min-h-11 rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface-solid)] px-3 font-normal text-[var(--color-text)]"
          >
            <option value="viewer">Viewer</option>
            <option value="manager">Manager</option>
            <option value="owner">Owner</option>
          </select>
        </label>
        <button
          className="primary-action min-h-11 justify-center"
          type="submit"
          disabled={isSubmitting}
        >
          Add Member
        </button>
      </form>

      {memberError && (
        <div className="mt-3 rounded-md border border-[var(--color-warning-border)] bg-[var(--color-warning-bg)] p-3 text-sm" role="alert">
          {memberError}
        </div>
      )}

      {isLoadingMembers ? (
        <div className="mt-4 h-28 animate-pulse rounded-lg bg-[var(--color-primary-soft)]" aria-label="Loading members" />
      ) : (
        <div className="mt-4 overflow-x-auto rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]">
          <table className="w-full min-w-[680px] border-collapse text-left">
            <thead className="bg-[var(--color-primary-soft)] text-xs uppercase text-[var(--color-muted)]">
              <tr>
                <th className="p-3">Member</th>
                <th className="p-3">Timezone</th>
                <th className="p-3">Role</th>
                <th className="p-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {members.map((member) => (
                <tr key={member.id} className="border-t border-[var(--color-border)]">
                  <td className="p-3">
                    <strong className="block text-[var(--color-heading)]">{member.user.name}</strong>
                    <span className="text-sm text-[var(--color-muted)]">{member.user.email}</span>
                  </td>
                  <td className="p-3 text-sm text-[var(--color-muted)]">{member.user.timezone}</td>
                  <td className="p-3">
                    <select
                      className="min-h-10 rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface-solid)] px-2 text-sm capitalize text-[var(--color-text)]"
                      value={member.role}
                      disabled={isSubmitting}
                      aria-label={`Role for ${member.user.name}`}
                      onChange={(event) => {
                        const role = event.target.value as OrganizationRole

                        if (role !== member.role) {
                          setPendingRoleChange({ member, role })
                        }
                      }}
                    >
                      <option value="owner">Owner</option>
                      <option value="manager">Manager</option>
                      <option value="viewer">Viewer</option>
                    </select>
                  </td>
                  <td className="p-3 text-right">
                    <button
                      className="inline-flex min-h-10 items-center gap-1 rounded-md border border-[var(--color-border-strong)] px-3 text-sm font-bold text-[var(--color-heading)]"
                      type="button"
                      disabled={isSubmitting}
                      onClick={() => setPendingRemoval(member)}
                    >
                      <DeleteOutlineOutlinedIcon fontSize="small" />
                      Remove
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <ConfirmationDialog
        open={pendingRoleChange !== null}
        title="Change member role?"
        description={
          pendingRoleChange
            ? `${pendingRoleChange.member.user.name} will immediately receive ${pendingRoleChange.role} permissions.`
            : ''
        }
        confirmLabel="Change Role"
        isPending={isSubmitting}
        onCancel={() => setPendingRoleChange(null)}
        onConfirm={() => void confirmRoleChange()}
      />
      <ConfirmationDialog
        open={pendingRemoval !== null}
        title="Remove workspace member?"
        description={
          pendingRemoval
            ? `${pendingRemoval.user.name} will immediately lose access to this workspace. This action cannot be undone.`
            : ''
        }
        confirmLabel="Remove Member"
        tone="danger"
        isPending={isSubmitting}
        onCancel={() => setPendingRemoval(null)}
        onConfirm={() => void confirmRemoveMember()}
      />
    </section>
  )
}
