import { getActiveLocale } from '../i18n/activeLocale'
import { API_URL } from './config'
import { ApiError, toApiError } from './problem'

export interface ApiFetchInit extends RequestInit {
  /** Set to false for requests that must not trigger a token refresh. */
  retryOnUnauthorized?: boolean
}

let accessToken: string | null = null
let refreshInFlight: Promise<string> | null = null
const sessionLostListeners = new Set<() => void>()

export function setAccessToken(token: string | null): void {
  accessToken = token
}

export function getAccessToken(): string | null {
  return accessToken
}

/** Subscribes to "the session is gone, show the login screen". */
export function onSessionLost(listener: () => void): () => void {
  sessionLostListeners.add(listener)

  return () => {
    sessionLostListeners.delete(listener)
  }
}

function loseSession(): void {
  accessToken = null

  for (const listener of sessionLostListeners) {
    listener()
  }
}

export function refreshAccessToken(): Promise<string> {
  // Jedno odswiezenie na raz, wspoldzielone przez wszystkie czekajace zadania:
  // RefreshTokenManager rotuje ciasteczko przy kazdym uzyciu, wiec dwa
  // rownolegle uniewaznialyby sie nawzajem.
  refreshInFlight ??= runRefresh()

  return refreshInFlight
}

async function runRefresh(): Promise<string> {
  try {
    const response = await fetch(`${API_URL}/api/token/refresh`, {
      method: 'POST',
      // Ciasteczko ma path=/api/token/refresh i httpOnly - bez tej opcji
      // przegladarka go nie dolaczy i serwer odpowie 401 mimo waznej sesji.
      credentials: 'include',
      headers: { Accept: 'application/json', 'Accept-Language': getActiveLocale() },
    })

    if (!response.ok) {
      throw await toApiError(response)
    }

    const body: unknown = await response.json()
    const token = 'object' === typeof body && null !== body ? (body as Record<string, unknown>).token : null

    if ('string' !== typeof token || '' === token) {
      throw new ApiError(response.status, 'Serwer nie zwrócił tokenu sesji.')
    }

    accessToken = token

    return token
  } finally {
    refreshInFlight = null
  }
}

export async function apiFetch(path: string, init: ApiFetchInit = {}): Promise<Response> {
  const { retryOnUnauthorized = true, ...rest } = init
  const response = await send(path, rest)

  if (401 !== response.status || !retryOnUnauthorized) {
    return response
  }

  try {
    await refreshAccessToken()
  } catch {
    loseSession()

    return response
  }

  // Dokladnie jedno powtorzenie. Drugi 401 znaczy, ze token jest swiezy,
  // a mimo to nie wystarcza - kolejne odswiezenie tego nie naprawi.
  const retried = await send(path, rest)

  if (401 === retried.status) {
    loseSession()
  }

  return retried
}

function send(path: string, init: RequestInit): Promise<Response> {
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  headers.set('Accept-Language', getActiveLocale())

  if (null !== accessToken) {
    headers.set('Authorization', `Bearer ${accessToken}`)
  }

  return fetch(`${API_URL}${path}`, { ...init, headers })
}

export async function apiJson<T>(path: string, init: ApiFetchInit = {}): Promise<T> {
  const response = await apiFetch(path, init)

  if (!response.ok) {
    throw await toApiError(response)
  }

  return (await response.json()) as T
}

export async function apiVoid(path: string, init: ApiFetchInit = {}): Promise<void> {
  const response = await apiFetch(path, init)

  if (!response.ok) {
    throw await toApiError(response)
  }
}
