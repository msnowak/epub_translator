import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it } from 'vitest'
import { setAccessToken } from '../../api/client'
import { renderWithProviders } from '../../test/renderWithProviders'
import { server } from '../../test/server'
import { stubLayoutForVirtualization } from '../../test/virtualization'
import { EditorPage } from './EditorPage'

const API = 'http://localhost:8000'

function handlers() {
  return [
    http.get(`${API}/api/projects/p`, () =>
      HttpResponse.json({
        id: 'p',
        title: 'Testowa książka',
        sourceLanguage: 'en',
        targetLanguage: 'pl',
        ollamaModel: 'gemma4:12b',
        customPrompt: null,
        status: 'completed',
        originalFilename: 'book.epub',
        errorMessage: null,
        createdAt: '2026-08-24T10:00:00+00:00',
        updatedAt: '2026-08-24T10:00:00+00:00',
        segmentCounts: { translated: 2 },
        totalSegments: 2,
      }),
    ),
    http.get(`${API}/api/projects/p/chapters`, () =>
      HttpResponse.json([
        { id: 'ch-1', spineOrder: 0, title: 'Rozdział pierwszy', segmentCounts: { translated: 2 }, totalSegments: 2 },
        { id: 'ch-2', spineOrder: 1, title: null, segmentCounts: {}, totalSegments: 0 },
      ]),
    ),
    http.get(`${API}/api/chapters/ch-1/segments`, () =>
      HttpResponse.json([
        {
          id: 'seg-1',
          position: 0,
          nodeIndex: 0,
          subIndex: 0,
          sourceText: 'A [1]word[/1].',
          translatedText: 'Jakieś [1]słowo[/1].',
          status: 'translated',
          errorMessage: null,
          previewPlaceholders: { '1': '<em>' },
          chapter: { id: 'ch-1', spineOrder: 0, title: 'Rozdział pierwszy' },
        },
      ]),
    ),
    http.get(`${API}/api/projects/p/preview/ch-1`, () =>
      HttpResponse.text(
        '<html><body><p data-segment-id="seg-1">Jakieś <em>słowo</em>.</p></body></html>',
      ),
    ),
  ]
}

function renderEditor() {
  return renderWithProviders(
    <Routes>
      <Route path="/projekty/:id/rozdzialy/:chapterId" element={<EditorPage />} />
    </Routes>,
    { route: '/projekty/p/rozdzialy/ch-1' },
  )
}

describe('EditorPage', () => {
  beforeEach(() => {
    setAccessToken('token')
    stubLayoutForVirtualization()
    server.use(...handlers())
  })

  it('shows the chapter, its paragraphs and the preview', async () => {
    renderEditor()

    expect(await screen.findByText('A [1]word[/1].')).toBeInTheDocument()
    expect(screen.getByDisplayValue('Jakieś [1]słowo[/1].')).toBeInTheDocument()
    expect(screen.getByTitle('Podgląd rozdziału')).toBeInTheDocument()
  })

  it('names a chapter with no title by its place in the spine', async () => {
    renderEditor()

    expect(await screen.findByRole('link', { name: 'Rozdział 2' })).toBeInTheDocument()
  })

  it('sandboxes the preview without letting scripts run', async () => {
    renderEditor()

    const frame = await screen.findByTitle('Podgląd rozdziału')

    // allow-same-origin jest po to, zeby rodzic siegnal do contentDocument;
    // allow-scripts nie ma tu prawa sie pojawic - to ono trzyma ksiazke
    // nieuruchamialna.
    expect(frame).toHaveAttribute('sandbox', 'allow-same-origin')
  })

  it('warns that the numbers are still moving while the book translates', async () => {
    server.use(
      http.get(`${API}/api/projects/p`, () =>
        HttpResponse.json({
          id: 'p',
          title: 'Testowa książka',
          sourceLanguage: 'en',
          targetLanguage: 'pl',
          ollamaModel: 'gemma4:12b',
          customPrompt: null,
          status: 'translating',
          originalFilename: 'book.epub',
          errorMessage: null,
          createdAt: '2026-08-24T10:00:00+00:00',
          updatedAt: '2026-08-24T10:00:00+00:00',
          segmentCounts: { translated: 1, pending: 1 },
          totalSegments: 2,
        }),
      ),
    )

    renderEditor()

    expect(await screen.findByRole('button', { name: 'Wczytaj ponownie' })).toBeInTheDocument()
  })

  it('keeps only the failed paragraphs when asked to', async () => {
    renderEditor()

    await screen.findByText('A [1]word[/1].')
    await userEvent.click(screen.getByRole('button', { name: 'Tylko nieudane' }))

    await waitFor(() => {
      expect(screen.queryByText('A [1]word[/1].')).not.toBeInTheDocument()
    })
    expect(screen.getByText('W tym rozdziale nie ma nieudanych akapitów.')).toBeInTheDocument()
  })
})
