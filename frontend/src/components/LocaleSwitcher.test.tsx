import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { renderWithProviders } from '../test/renderWithProviders'
import { useT } from '../i18n/useT'
import { LocaleSwitcher } from './LocaleSwitcher'

// Tekst spoza samego przelacznika - jego wlasna etykieta zmienilaby sie nawet
// gdyby setLocale nie bylo w ogole podpiete pod kontrolke, wiec nie dowodzi
// niczego. Ten naglowek zyje w innym komponencie i reaguje tylko na realna
// zmiana locale w kontekscie.
function Heading() {
  const { t } = useT()

  return <h1>{t('auth.login.heading')}</h1>
}

describe('LocaleSwitcher', () => {
  it('offers every language under its own name', async () => {
    renderWithProviders(<LocaleSwitcher />)

    await userEvent.click(screen.getByRole('combobox', { name: 'Język interfejsu' }))

    expect(await screen.findByRole('option', { name: 'Polski' })).toBeVisible()
    expect(screen.getByRole('option', { name: 'English' })).toBeVisible()
  })

  it('actually switches the interface language when an option is chosen', async () => {
    renderWithProviders(
      <>
        <Heading />
        <LocaleSwitcher />
      </>,
      { locale: 'pl' },
    )

    expect(screen.getByText('Zaloguj się')).toBeVisible()

    await userEvent.click(screen.getByRole('combobox', { name: 'Język interfejsu' }))
    await userEvent.click(await screen.findByRole('option', { name: 'English' }))

    expect(await screen.findByText('Sign in')).toBeVisible()
  })
})
