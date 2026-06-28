import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

test.describe('accessibility', () => {
  test('landing page has no automatically detectable violations', async ({
    page,
  }) => {
    await page.goto('/')
    await expect(
      page.getByRole('heading', {
        level: 1,
        name: 'See where pull-request review flow is slowing down.',
      }),
    ).toBeVisible()

    const results = await new AxeBuilder({ page }).analyze()

    expect(results.violations).toEqual([])

    await page.getByRole('button', { name: 'Switch to dark mode' }).click()
    await expect(
      page.getByRole('button', { name: 'Switch to light mode' }),
    ).toBeVisible()
    await expect(
      page.getByRole('button', { name: 'View Demo Workspace' }),
    ).toHaveCSS('color', 'rgb(6, 20, 18)')

    const darkResults = await new AxeBuilder({ page }).analyze()

    expect(darkResults.violations).toEqual([])
  })

  test('sign-in page has no automatically detectable violations', async ({
    page,
  }) => {
    await page.goto('/sign-in')
    await expect(
      page.getByRole('heading', { name: 'Sign in to ReleaseLens' }),
    ).toBeVisible()

    const results = await new AxeBuilder({ page }).analyze()

    expect(results.violations).toEqual([])
  })
})
