import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { LocaleProvider } from './LocaleProvider'
import { getActiveLocale } from './activeLocale'
import { useT } from './useT'

function Probe() {
  const { t, locale, setLocale } = useT()

  return (
    <div>
      <p>{t('auth.login.heading')}</p>
      <button onClick={() => setLocale('pl' === locale ? 'en' : 'pl')}>swap</button>
    </div>
  )
}

describe('LocaleProvider', () => {
  it('renders in the initial locale and swaps on demand', async () => {
    render(
      <LocaleProvider initial="pl">
        <Probe />
      </LocaleProvider>,
    )

    expect(screen.getByText('Zaloguj się')).toBeVisible()

    await userEvent.click(screen.getByRole('button', { name: 'swap' }))

    expect(screen.getByText('Sign in')).toBeVisible()
  })

  it('remembers the choice and reflects it outside React', async () => {
    const { unmount } = render(
      <LocaleProvider initial="pl">
        <Probe />
      </LocaleProvider>,
    )

    await userEvent.click(screen.getByRole('button', { name: 'swap' }))

    expect(window.localStorage.getItem('epubTranslator.locale')).toBe('en')
    expect(getActiveLocale()).toBe('en')
    expect(document.documentElement.lang).toBe('en')

    unmount()
    render(
      <LocaleProvider>
        <Probe />
      </LocaleProvider>,
    )

    expect(screen.getByText('Sign in')).toBeVisible()
  })
})
