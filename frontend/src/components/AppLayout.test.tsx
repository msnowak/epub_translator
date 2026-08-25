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
  it('constrains the default layout to a narrow centered column', () => {
    renderLayout()

    expect(screen.getByRole('main')).toHaveClass('max-w-4xl')
  })

  it('lets the wide variant use the whole window width', () => {
    renderLayout(true)

    expect(screen.getByRole('main')).not.toHaveClass('max-w-4xl')
  })
})
