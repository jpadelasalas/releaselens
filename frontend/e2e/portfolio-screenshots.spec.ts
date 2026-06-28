import { expect, test } from '@playwright/test'

test.skip(
  process.env.CAPTURE_PORTFOLIO !== '1',
  'Set CAPTURE_PORTFOLIO=1 to refresh committed portfolio screenshots.',
)

test('capture the public demo journey', async ({ page }) => {
  await page.goto('/')
  await expect(page.getByRole('button', { name: 'View Demo Workspace' })).toBeVisible()
  await page.screenshot({ path: '../docs/screenshots/landing.png', fullPage: true })

  await page.getByRole('button', { name: 'View Demo Workspace' }).click()
  await expect(page.getByRole('heading', { name: 'Northstar Engineering' })).toBeVisible()
  await expect(
    page.getByRole('link', { name: 'View waiting pull requests' }),
  ).toBeVisible()
  await expect(page.getByText('Loading chart...')).not.toBeVisible()
  await page.screenshot({ path: '../docs/screenshots/dashboard.png', fullPage: true })

  const waitingCard = page.getByRole('article').filter({ hasText: 'Waiting for review' })
  await waitingCard.getByRole('link', { name: 'View waiting pull requests' }).click()
  await expect(
    page.getByRole('heading', { name: 'Pull requests waiting for review' }),
  ).toBeVisible()
  await expect(page.locator('tbody tr').first()).toBeVisible()
  await page.screenshot({ path: '../docs/screenshots/pull-request-explorer.png', fullPage: true })

  await page.getByRole('link', { name: 'Metric Glossary' }).click()
  await expect(page.getByRole('heading', { name: 'Metric glossary' })).toBeVisible()
  await page.screenshot({ path: '../docs/screenshots/metric-glossary.png', fullPage: true })
})
