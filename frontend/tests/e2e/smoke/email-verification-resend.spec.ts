import { expect, test } from '@playwright/test'

test.describe('@smoke Email verification — resend', () => {
  test('uses the signed-in account email when no ?email= query is provided', async ({ page }) => {
    let sentBody: unknown = null

    await page.route('**/api/users/me', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          id: 'fake-uuid',
          email: 'signedin@example.com',
          firstName: 'E2E',
          lastName: 'User',
          roles: ['ROLE_USER'],
          isVerified: false,
          createdAt: new Date().toISOString(),
        }),
      })
    )

    await page.route('**/api/email/resend-verification', (route) => {
      sentBody = route.request().postDataJSON()
      return route.fulfill({
        status: 202,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Verification email sent.' }),
      })
    })

    await page.addInitScript(() => {
      localStorage.setItem('auth_token', 'fake-jwt-token')
    })

    await page.goto('/auth/email-verification')
    await expect(page.getByText('signedin@example.com')).toBeVisible()

    await page.getByRole('button', { name: "Renvoyer l'email" }).click()

    await expect.poll(() => sentBody).toEqual({ email: 'signedin@example.com' })
  })
})
