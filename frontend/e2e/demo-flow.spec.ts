import { expect, test } from '@playwright/test'

test('visitor enters demo and opens waiting-for-review records', async ({ page }) => {
  await page.goto('/')

  await page.getByRole('button', { name: 'View Demo Workspace' }).click()

  await expect(page).toHaveURL(/\/demo\/dashboard$/)
  await expect(page.getByRole('heading', { name: 'Northstar Engineering' })).toBeVisible()
  await expect(page.getByLabel('Demo workspace notice')).toContainText(
    'All data is synthetic',
  )

  const waitingCard = page
    .getByRole('article')
    .filter({ hasText: 'Waiting for review' })
  const waitingCount = Number(
    await waitingCard.locator('strong').first().textContent(),
  )

  expect(waitingCount).toBeGreaterThan(0)
  await waitingCard
    .getByRole('link', { name: 'View waiting pull requests' })
    .click()

  await expect(page).toHaveURL(/review_status=waiting/)
  await expect(
    page.getByRole('heading', { name: 'Pull requests waiting for review' }),
  ).toBeVisible()
  await expect(page.getByText(`${waitingCount} matching`)).toBeVisible()
  await expect(page.locator('tbody tr')).toHaveCount(waitingCount)
  await expect(page.getByLabel('Demo workspace notice')).toContainText(
    'This workspace is read-only',
  )
})

test('visitor registers, enters a protected workspace, and signs out', async ({
  browser,
  page,
}) => {
  test.setTimeout(60_000)
  const memberPage = await browser.newPage()
  await memberPage.goto('/register')
  await memberPage.getByLabel('Name').fill('Sam Lee')
  await memberPage.getByLabel('Email address').fill('sam@example.com')
  await memberPage
    .getByLabel('Password', { exact: true })
    .fill('release-lens-2026')
  await memberPage.getByLabel('Confirm password').fill('release-lens-2026')
  await memberPage.getByRole('button', { name: 'Create Account' }).click()
  await expect(memberPage).toHaveURL(/\/app$/)
  await memberPage.getByRole('button', { name: 'Sign Out' }).click()
  await memberPage.close()

  await page.goto('/register')

  await page.getByLabel('Name').fill('Alex Rivera')
  await page.getByLabel('Email address').fill('alex@example.com')
  await page.getByLabel('Password', { exact: true }).fill('release-lens-2026')
  await page.getByLabel('Confirm password').fill('release-lens-2026')
  await page.getByLabel('Timezone').selectOption('Asia/Manila')
  await page.getByRole('button', { name: 'Create Account' }).click()

  await expect(page).toHaveURL(/\/app$/)
  await expect(
    page.getByRole('heading', { name: 'Choose your workspace' }),
  ).toBeVisible()
  await expect(page.getByText('alex@example.com')).toBeVisible()

  await page.getByLabel('Workspace name').fill('Platform Team')
  await page.getByLabel('Workspace timezone').selectOption('Asia/Manila')
  await page.getByRole('button', { name: 'Create Workspace' }).click()

  await expect(
    page.getByRole('heading', { level: 1, name: 'Platform Team' }),
  ).toBeVisible()
  const platformWorkspace = page
    .getByRole('article')
    .filter({ hasText: 'Platform Team' })
  await expect(platformWorkspace).toContainText('owner - Asia/Manila')
  await expect(
    platformWorkspace.getByRole('button', { name: 'Active workspace' }),
  ).toBeDisabled()

  await page.getByLabel('Workspace name').fill('Mobile Team')
  await page.getByRole('button', { name: 'Create Workspace' }).click()
  await expect(
    page.getByRole('heading', { level: 1, name: 'Mobile Team' }),
  ).toBeVisible()

  await platformWorkspace.getByRole('button', { name: 'Open workspace' }).click()
  await expect(
    page.getByRole('heading', { level: 1, name: 'Platform Team' }),
  ).toBeVisible()

  await page.getByLabel('Registered user email').fill('sam@example.com')
  await page.getByRole('button', { name: 'Add Member' }).click()

  const samRow = page.getByRole('row').filter({ hasText: 'sam@example.com' })
  await expect(samRow).toBeVisible()
  await samRow.getByLabel('Role for Sam Lee').selectOption('manager')
  await expect(
    page.getByRole('heading', { name: 'Change member role?' }),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Change Role' }).click()
  await expect(samRow.getByLabel('Role for Sam Lee')).toHaveValue('manager')

  const ownerRow = page.getByRole('row').filter({ hasText: 'alex@example.com' })
  await ownerRow.getByLabel('Role for Alex Rivera').selectOption('manager')
  await expect(
    page.getByRole('heading', { name: 'Change member role?' }),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Change Role' }).click()
  await expect(page.getByRole('alert')).toContainText(
    'Promote another Owner before demoting or removing the final Owner.',
  )

  await samRow.getByRole('button', { name: 'Remove' }).click()
  await expect(
    page.getByRole('heading', { name: 'Remove workspace member?' }),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Remove Member' }).click()
  await expect(samRow).not.toBeVisible()

  await page.getByRole('button', { name: 'Sign Out' }).click()

  await expect(page).toHaveURL(/\/sign-in$/)
  await expect(
    page.getByRole('heading', { name: 'Sign in to ReleaseLens' }),
  ).toBeVisible()

  await page.goto('/app')
  await expect(page).toHaveURL(/\/sign-in$/)
})
