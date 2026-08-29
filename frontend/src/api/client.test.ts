import { HttpResponse, http } from 'msw'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { setActiveLocale } from '../i18n/activeLocale'
import { server } from '../test/server'
import { ApiError } from './problem'
import { apiFetch, apiJson, onSessionLost, refreshAccessToken, setAccessToken } from './client'

const API = 'http://localhost:8000'

afterEach(() => {
  setAccessToken(null)
  setActiveLocale('pl')
})

describe('apiJson', () => {
  it('sends the access token it was given', async () => {
    setAccessToken('token-1')
    let seen: string | null = null
    server.use(
      http.get(`${API}/api/projects`, ({ request }) => {
        seen = request.headers.get('Authorization')

        return HttpResponse.json([])
      }),
    )

    await apiJson('/api/projects')

    expect(seen).toBe('Bearer token-1')
  })

  it('refreshes the token on 401 and repeats the request', async () => {
    setAccessToken('stale')
    const seen: string[] = []
    server.use(
      http.post(`${API}/api/token/refresh`, () => HttpResponse.json({ token: 'fresh' })),
      http.get(`${API}/api/projects`, ({ request }) => {
        const authorization = request.headers.get('Authorization') ?? ''
        seen.push(authorization)

        return 'Bearer fresh' === authorization
          ? HttpResponse.json([{ id: 'p1' }])
          : HttpResponse.json({ code: 401, message: 'Expired JWT Token' }, { status: 401 })
      }),
    )

    const projects = await apiJson<Array<{ id: string }>>('/api/projects')

    expect(projects).toEqual([{ id: 'p1' }])
    expect(seen).toEqual(['Bearer stale', 'Bearer fresh'])
  })

  it('refreshes only once for requests that fail together', async () => {
    setAccessToken('stale')
    let refreshes = 0
    server.use(
      http.post(`${API}/api/token/refresh`, () => {
        refreshes += 1

        return HttpResponse.json({ token: 'fresh' })
      }),
      http.get(`${API}/api/projects`, ({ request }) =>
        (request.headers.get('Authorization') ?? '').startsWith('Bearer fresh')
          ? HttpResponse.json([])
          : HttpResponse.json({ code: 401, message: 'Expired JWT Token' }, { status: 401 }),
      ),
    )

    await Promise.all([apiJson('/api/projects'), apiJson('/api/projects'), apiJson('/api/projects')])

    // Kazde odswiezenie rotuje ciasteczko, wiec trzy rownolegle uniewaznilyby
    // sie nawzajem i sesja padlaby przy trzecim zadaniu.
    expect(refreshes).toBe(1)
  })

  it('gives up and reports a lost session when the refresh fails', async () => {
    setAccessToken('stale')
    const lost = vi.fn()
    const unsubscribe = onSessionLost(lost)
    server.use(
      http.post(`${API}/api/token/refresh`, () =>
        HttpResponse.json({ detail: 'Sesja wygasła. Zaloguj się ponownie.' }, { status: 401 }),
      ),
      http.get(`${API}/api/projects`, () =>
        HttpResponse.json({ code: 401, message: 'Expired JWT Token' }, { status: 401 }),
      ),
    )

    await expect(apiJson('/api/projects')).rejects.toBeInstanceOf(ApiError)
    expect(lost).toHaveBeenCalledOnce()

    unsubscribe()
  })

  it('turns a problem document into an ApiError with its Polish detail', async () => {
    server.use(
      http.post(`${API}/api/projects/p1/start`, () =>
        HttpResponse.json(
          { title: 'An error occurred', status: 409, detail: 'Projekt nie jest gotowy do tłumaczenia.' },
          { status: 409 },
        ),
      ),
    )

    const error = (await apiJson('/api/projects/p1/start', { method: 'POST' }).catch(
      (caught: unknown) => caught,
    )) as ApiError

    expect(error).toBeInstanceOf(ApiError)
    expect(error.status).toBe(409)
    expect(error.detail).toBe('Projekt nie jest gotowy do tłumaczenia.')
  })

  it('maps violations onto form fields', async () => {
    server.use(
      http.post(`${API}/api/register`, () =>
        HttpResponse.json(
          {
            status: 422,
            detail: 'email: To nie jest poprawny adres e-mail.',
            violations: [
              { propertyPath: 'email', message: 'To nie jest poprawny adres e-mail.' },
              { propertyPath: 'plainPassword', message: 'Hasło musi mieć co najmniej 8 znaków.' },
            ],
          },
          { status: 422 },
        ),
      ),
    )

    const error = (await apiJson('/api/register', { method: 'POST' }).catch(
      (caught: unknown) => caught,
    )) as ApiError

    expect(error.fieldErrors()).toEqual({
      email: 'To nie jest poprawny adres e-mail.',
      plainPassword: 'Hasło musi mieć co najmniej 8 znaków.',
    })
  })

  it('sends the active interface language', async () => {
    let seen: string | null = null

    server.use(
      http.get(`${API}/api/ping`, ({ request }) => {
        seen = request.headers.get('Accept-Language')

        return HttpResponse.json({})
      }),
    )
    setActiveLocale('en')

    await apiFetch('/api/ping')

    expect(seen).toBe('en')
  })

  it('sends the active interface language on the refresh request too', async () => {
    let seen: string | null = null

    server.use(
      http.post(`${API}/api/token/refresh`, ({ request }) => {
        seen = request.headers.get('Accept-Language')

        return HttpResponse.json({ token: 'fresh' })
      }),
    )
    setActiveLocale('en')

    await refreshAccessToken()

    expect(seen).toBe('en')
  })
})
