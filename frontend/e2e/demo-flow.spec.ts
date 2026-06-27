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
