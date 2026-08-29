import { getActiveLocale } from '../i18n/activeLocale'
import { apiJson, setAccessToken } from './client'
import { API_URL } from './config'
import { toApiError } from './problem'

interface TokenResponse {
  token: string
}

export async function login(email: string, password: string): Promise<void> {
  // Nie przez apiFetch: logowanie nie ma tokenu do wyslania i nie moze probowac
  // odswiezenia po 401 - 401 znaczy tu "zle haslo".
  const response = await fetch(`${API_URL}/api/login_check`, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'Accept-Language': getActiveLocale() },
    body: JSON.stringify({ email, password }),
  })

  if (!response.ok) {
    throw await toApiError(response)
  }

  const body = (await response.json()) as TokenResponse

  setAccessToken(body.token)
}

export async function register(email: string, plainPassword: string): Promise<void> {
  await apiJson('/api/register', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, plainPassword }),
    retryOnUnauthorized: false,
  })
}

export async function logout(): Promise<void> {
  setAccessToken(null)

  // Bez tego ciasteczko odswiezajace zyloby dalej przez 30 dni i pierwsze
  // przeladowanie strony zalogowaloby uzytkownika z powrotem.
  await fetch(`${API_URL}/api/token/refresh`, {
    method: 'DELETE',
    credentials: 'include',
    headers: { Accept: 'application/json' },
  })
}
