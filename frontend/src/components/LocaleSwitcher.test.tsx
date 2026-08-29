import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { renderWithProviders } from '../test/renderWithProviders'
import { LocaleSwitcher } from './LocaleSwitcher'

describe('LocaleSwitcher', () => {
  it('offers every language under its own name', async () => {
    renderWithProviders(<LocaleSwitcher />)

    await userEvent.click(screen.getByRole('combobox', { name: 'Język interfejsu' }))

    expect(await screen.findByRole('option', { name: 'Polski' })).toBeVisible()
    expect(screen.getByRole('option', { name: 'English' })).toBeVisible()
  })
})
