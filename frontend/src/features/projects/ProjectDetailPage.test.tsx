import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '../../test/server'
import { renderWithProviders } from '../../test/renderWithProviders'
import App from '../../App'
import type { Project, ProjectStatus } from '../../api/types'

const API = 'http://localhost:8000'
const session = http.post(`${API}/api/token/refresh`, () => HttpResponse.json({ token: 'fresh' }))

function project(status: ProjectStatus, overrides: Partial<Project> = {}): Project {
  return {
    id: 'p1',
    title: 'Wyspa skarbów',
    sourceLanguage: 'en',
    targetLanguage: 'pl',
    ollamaModel: 'gemma4:12b',
    customPrompt: null,
    status,
    originalFilename: 'treasure.epub',
    errorMessage: null,
    createdAt: '2026-08-23T10:00:00+00:00',
    updatedAt: '2026-08-23T10:00:00+00:00',
    segmentCounts: { pending: 100 },
    totalSegments: 100,
    ...overrides,
  }
}

const noChapters = http.get(`${API}/api/projects/p1/chapters`, () => HttpResponse.json([]))

describe('ProjectDetailPage', () => {
  it('starts the translation and picks up the new status', async () => {
    const started = { yes: false }
    server.use(
      session,
      noChapters,
      http.post(`${API}/api/projects/p1/start`, () => {
        started.yes = true

        return new HttpResponse(null, { status: 200 })
      }),
      http.get(`${API}/api/projects/p1`, () =>
        HttpResponse.json(
          started.yes
            ? project('translating', { segmentCounts: { translated: 5, pending: 95 } })
            : project('ready'),
        ),
      ),
    )
    renderWithProviders(<App />, { route: '/projekty/p1' })

    await userEvent.click(await screen.findByRole('button', { name: 'Rozpocznij tłumaczenie' }))

    expect(await screen.findByText('Tłumaczenie')).toBeVisible()
    expect(started.yes).toBe(true)
  })

  it('offers only the actions the status allows', async () => {
    server.use(session, noChapters, http.get(`${API}/api/projects/p1`, () => HttpResponse.json(project('ready'))))
    renderWithProviders(<App />, { route: '/projekty/p1' })

    expect(await screen.findByRole('button', { name: 'Rozpocznij tłumaczenie' })).toBeEnabled()
    // Wstrzymac mozna tylko to, co sie tlumaczy - przycisk ma nie istniec,
    // zamiast wysylac zadanie, ktore i tak wroci z 409.
    expect(screen.queryByRole('button', { name: 'Wstrzymaj' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Wznów' })).not.toBeInTheDocument()
  })

  it('shows the conflict the server reported', async () => {
    server.use(
      session,
      noChapters,
      http.get(`${API}/api/projects/p1`, () => HttpResponse.json(project('ready'))),
      http.post(`${API}/api/projects/p1/start`, () =>
        HttpResponse.json({ status: 409, detail: 'Projekt jest już tłumaczony.' }, { status: 409 }),
      ),
    )
    renderWithProviders(<App />, { route: '/projekty/p1' })

    await userEvent.click(await screen.findByRole('button', { name: 'Rozpocznij tłumaczenie' }))

    expect(await screen.findByText('Projekt jest już tłumaczony.')).toBeVisible()
  })

  it('lists the chapters with their failed paragraphs', async () => {
    server.use(
      session,
      http.get(`${API}/api/projects/p1`, () =>
        HttpResponse.json(project('completed_with_errors', { segmentCounts: { translated: 97, failed: 3 } })),
      ),
      http.get(`${API}/api/projects/p1/chapters`, () =>
        HttpResponse.json([
          { id: 'c1', spineOrder: 0, title: 'Rozdział pierwszy', segmentCounts: { translated: 50 }, totalSegments: 50 },
          { id: 'c2', spineOrder: 1, title: null, segmentCounts: { translated: 47, failed: 3 }, totalSegments: 50 },
        ]),
      ),
      // failed > 0 montuje FailedSegments, ktory odpytuje ta trase - poza
      // zakresem tego testu (ma wlasny plik), ale bez handlera MSW
      // wypisywalby "unhandled request" na kazde uruchomienie.
      http.get(`${API}/api/projects/p1/segments`, () => HttpResponse.json([])),
    )
    renderWithProviders(<App />, { route: '/projekty/p1' })

    expect(await screen.findByText('Rozdział pierwszy')).toBeVisible()
    // Rozdzial bez tytulu w OPF-ie i tak musi dac sie wskazac.
    expect(screen.getByText('Rozdział 2')).toBeVisible()
    expect(screen.getByText('3 nieudane')).toBeVisible()
  })

  it('deletes the project only after a confirmation', async () => {
    const deleted = { yes: false }
    server.use(
      session,
      noChapters,
      http.get(`${API}/api/projects/p1`, () => HttpResponse.json(project('ready'))),
      http.get(`${API}/api/projects`, () => HttpResponse.json([])),
      http.delete(`${API}/api/projects/p1`, () => {
        deleted.yes = true

        return new HttpResponse(null, { status: 204 })
      }),
    )
    renderWithProviders(<App />, { route: '/projekty/p1' })

    await userEvent.click(await screen.findByRole('button', { name: 'Usuń projekt' }))

    expect(deleted.yes).toBe(false)

    await userEvent.click(await screen.findByRole('button', { name: 'Tak, usuń' }))

    expect(await screen.findByRole('heading', { name: 'Twoje książki' })).toBeVisible()
    expect(deleted.yes).toBe(true)
  })
})
