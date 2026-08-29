import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '../test/server'
import { renderWithProviders } from '../test/renderWithProviders'
import App from '../App'

const API = 'http://localhost:8000'

const noSession = http.post(`${API}/api/token/refresh`, () =>
  HttpResponse.json({ detail: 'Brak tokenu odświeżającego.' }, { status: 401 }),
)

describe('LoginPage', () => {
  it('logs in and lands on the project list', async () => {
    server.use(
      noSession,
      http.post(`${API}/api/login_check`, () => HttpResponse.json({ token: 'fresh' })),
      http.get(`${API}/api/projects`, () => HttpResponse.json([])),
    )
    renderWithProviders(<App />, { route: '/login' })

    await userEvent.type(await screen.findByLabelText('Adres e-mail'), 'reader@example.com')
    await userEvent.type(screen.getByLabelText('Hasło'), 'correcthorse')
    await userEvent.click(screen.getByRole('button', { name: 'Zaloguj się' }))

    expect(await screen.findByRole('heading', { name: 'Twoje książki' })).toBeVisible()
  })

  it('shows the message the server sent when the password is wrong', async () => {
    server.use(
      noSession,
      http.post(`${API}/api/login_check`, () =>
        HttpResponse.json({ status: 401, detail: 'Nieprawidłowy e-mail lub hasło.' }, { status: 401 }),
      ),
    )
    renderWithProviders(<App />, { route: '/login' })

    await userEvent.type(await screen.findByLabelText('Adres e-mail'), 'reader@example.com')
    await userEvent.type(screen.getByLabelText('Hasło'), 'wrong-password')
    await userEvent.click(screen.getByRole('button', { name: 'Zaloguj się' }))

    expect(await screen.findByText('Nieprawidłowy e-mail lub hasło.')).toBeVisible()
  })
})
