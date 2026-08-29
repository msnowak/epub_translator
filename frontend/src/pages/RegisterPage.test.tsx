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

describe('RegisterPage', () => {
  it('creates the account, logs in and lands on the project list', async () => {
    server.use(
      noSession,
      http.post(`${API}/api/register`, () => HttpResponse.json({ id: 'u1' }, { status: 201 })),
      http.post(`${API}/api/login_check`, () => HttpResponse.json({ token: 'fresh' })),
      http.get(`${API}/api/projects`, () => HttpResponse.json([])),
    )
    renderWithProviders(<App />, { route: '/register' })

    await userEvent.type(await screen.findByLabelText('Adres e-mail'), 'reader@example.com')
    await userEvent.type(screen.getByLabelText('Hasło'), 'correcthorse')
    await userEvent.click(screen.getByRole('button', { name: 'Załóż konto' }))

    expect(await screen.findByRole('heading', { name: 'Twoje książki' })).toBeVisible()
  })

  it('puts a validation message from the server next to its field', async () => {
    server.use(
      noSession,
      http.post(`${API}/api/register`, () =>
        HttpResponse.json(
          {
            status: 422,
            detail: 'email: Ten adres e-mail jest już zajęty.',
            violations: [{ propertyPath: 'email', message: 'Ten adres e-mail jest już zajęty.' }],
          },
          { status: 422 },
        ),
      ),
    )
    renderWithProviders(<App />, { route: '/register' })

    await userEvent.type(await screen.findByLabelText('Adres e-mail'), 'taken@example.com')
    await userEvent.type(screen.getByLabelText('Hasło'), 'correcthorse')
    await userEvent.click(screen.getByRole('button', { name: 'Załóż konto' }))

    expect(await screen.findByText('Ten adres e-mail jest już zajęty.')).toBeVisible()
  })

  it('refuses a password the backend would reject anyway', async () => {
    server.use(noSession)
    renderWithProviders(<App />, { route: '/register' })

    await userEvent.type(await screen.findByLabelText('Adres e-mail'), 'reader@example.com')
    await userEvent.type(screen.getByLabelText('Hasło'), 'krotkie')
    await userEvent.click(screen.getByRole('button', { name: 'Załóż konto' }))

    // Walidacja po stronie klienta jest lustrem reguly z backendu - zadanie
    // z siedmioznakowym haslem nie ma po co lecieć.
    expect(await screen.findByText('Hasło musi mieć co najmniej 8 znaków.')).toBeVisible()
  })
})
