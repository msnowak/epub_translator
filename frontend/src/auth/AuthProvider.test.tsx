import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '../test/server'
import { renderWithProviders } from '../test/renderWithProviders'
import App from '../App'

const API = 'http://localhost:8000'

describe('session bootstrap', () => {
  it('lets a returning user straight in when the refresh cookie still works', async () => {
    server.use(
      http.post(`${API}/api/token/refresh`, () => HttpResponse.json({ token: 'fresh' })),
      http.get(`${API}/api/projects`, () => HttpResponse.json([])),
    )

    renderWithProviders(<App />, { route: '/' })

    expect(await screen.findByRole('heading', { name: 'Twoje książki' })).toBeVisible()
  })

  it('shows the login screen when there is no session to restore', async () => {
    server.use(
      http.post(`${API}/api/token/refresh`, () =>
        HttpResponse.json({ detail: 'Brak tokenu odświeżającego.' }, { status: 401 }),
      ),
    )

    renderWithProviders(<App />, { route: '/' })

    expect(await screen.findByRole('heading', { name: 'Zaloguj się' })).toBeVisible()
  })

  it('waits instead of guessing while the refresh is in flight', async () => {
    let release = (): void => {}
    const blocked = new Promise<void>((resolve) => {
      release = () => resolve()
    })
    server.use(
      http.post(`${API}/api/token/refresh`, async () => {
        await blocked

        return HttpResponse.json({ token: 'fresh' })
      }),
      http.get(`${API}/api/projects`, () => HttpResponse.json([])),
    )

    renderWithProviders(<App />, { route: '/' })

    // Ani listy, ani ekranu logowania - dopoki nie wiadomo, kim jest
    // uzytkownik, pokazanie mu logowania byloby wyrzuceniem zalogowanej osoby.
    expect(screen.queryByRole('heading', { name: 'Zaloguj się' })).not.toBeInTheDocument()
    expect(await screen.findByText('Wczytywanie…')).toBeVisible()

    release()

    expect(await screen.findByRole('heading', { name: 'Twoje książki' })).toBeVisible()
  })
})
