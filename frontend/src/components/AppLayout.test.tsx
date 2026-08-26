import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { Route, Routes } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { server } from '../test/server'
import { renderWithProviders } from '../test/renderWithProviders'
import { AppLayout } from './AppLayout'

const API = 'http://localhost:8000'

// AuthProvider probuje odswiezyc sesje przy montowaniu; bez tej obslugi
// niezaslonieta prosba wywalilaby test (onUnhandledRequest: 'error').
const session = http.post(`${API}/api/token/refresh`, () => HttpResponse.json({ token: 'fresh' }))

function renderLayout(wide?: boolean) {
  server.use(session)

  return renderWithProviders(
    <Routes>
      <Route element={<AppLayout wide={wide} />}>
        <Route path="/" element={<p>Tresc</p>} />
      </Route>
    </Routes>,
    { route: '/' },
  )
}

describe('AppLayout', () => {
  it('centers the narrow layout in a wrapper inside main, not on main itself', () => {
    renderLayout()

    const main = screen.getByRole('main')

    // main must stay a plain flex item (min-h-0 flex-1) so it stretches to
    // the row's full width; putting max-w-4xl on main itself makes it a flex
    // item WITH auto cross-axis margins, which suppresses stretch and
    // shrinks main down to its content width instead.
    expect(main).toHaveClass('flex-1')
    expect(main).not.toHaveClass('max-w-4xl')

    const wrapper = main.querySelector('.max-w-4xl')
    expect(wrapper).not.toBeNull()
    expect(wrapper).not.toBe(main)
  })

  it('lets the wide variant fill main with no max-width wrapper', () => {
    renderLayout(true)

    const main = screen.getByRole('main')

    expect(main).toHaveClass('flex-1')
    expect(main.querySelector('.max-w-4xl')).toBeNull()
  })
})
