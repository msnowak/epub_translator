import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '../../test/server'
import { renderWithProviders } from '../../test/renderWithProviders'
import App from '../../App'

const API = 'http://localhost:8000'

const session = http.post(`${API}/api/token/refresh`, () => HttpResponse.json({ token: 'fresh' }))

const project = {
  id: 'p1',
  title: 'Wyspa skarbów',
  targetLanguage: 'pl',
  sourceLanguage: 'en',
  ollamaModel: 'gemma4:12b',
  customPrompt: null,
  status: 'translating',
  originalFilename: 'treasure.epub',
  errorCode: null,
  errorParams: null,
  createdAt: '2026-08-23T10:00:00+00:00',
  updatedAt: '2026-08-23T10:05:00+00:00',
  segmentCounts: { translated: 28, edited: 2, pending: 70 },
  totalSegments: 100,
}

describe('ProjectListPage', () => {
  it('shows a project with its status and progress', async () => {
    server.use(session, http.get(`${API}/api/projects`, () => HttpResponse.json([project])))

    renderWithProviders(<App />, { route: '/' })

    expect(await screen.findByText('Wyspa skarbów')).toBeVisible()
    expect(screen.getByText('Tłumaczenie')).toBeVisible()
    // Recznie poprawione akapity licza sie do postepu tak samo jak
    // przetlumaczone maszynowo, dokladnie jak w ChapterComposer.
    expect(screen.getByText('30 z 100 akapitów (30%)')).toBeVisible()
  })

  it('tells the user what to do when there is nothing yet', async () => {
    server.use(session, http.get(`${API}/api/projects`, () => HttpResponse.json([])))

    renderWithProviders(<App />, { route: '/' })

    expect(await screen.findByText('Nie masz jeszcze żadnej książki.')).toBeVisible()
  })

  it('shows the message the server sent when the list cannot be loaded', async () => {
    server.use(
      session,
      http.get(`${API}/api/projects`, () =>
        HttpResponse.json({ status: 503, detail: 'Baza danych jest niedostępna.' }, { status: 503 }),
      ),
    )

    renderWithProviders(<App />, { route: '/' })

    expect(await screen.findByText('Baza danych jest niedostępna.')).toBeVisible()
  })
})
