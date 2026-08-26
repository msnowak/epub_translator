import { fireEvent, screen, waitFor } from '@testing-library/react'
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

  it('fetches the chapter document once, even after leaving and returning to it', async () => {
    // Powrot do rozdzialu 1 nie ma pobierac dokumentu drugi raz: bez
    // staleTime: Infinity zapytanie najpierw oddaje cache (widoczne od razu),
    // po czym natychmiast leci odswiezenie w tle - to ono migoczaco
    // przeladowuje ramke (kazda zmiana srcDoc kaze jej wczytac sie od zera).
    let ch1Requests = 0

    server.use(
      http.get(`${API}/api/projects/p/preview/ch-1`, () => {
        ch1Requests += 1

        return HttpResponse.text(
          '<html><body><p data-segment-id="seg-1">Jakieś <em>słowo</em>.</p></body></html>',
        )
      }),
      http.get(`${API}/api/chapters/ch-2/segments`, () => HttpResponse.json([])),
      http.get(`${API}/api/projects/p/preview/ch-2`, () => HttpResponse.text('<html><body>Drugi.</body></html>')),
    )

    renderEditor()

    await screen.findByText('A [1]word[/1].')
    await waitFor(() => {
      expect(ch1Requests).toBe(1)
    })

    await userEvent.click(screen.getByRole('link', { name: 'Rozdział 2' }))
    await screen.findByText('Ten rozdział nie ma akapitów.')

    await userEvent.click(screen.getByRole('link', { name: 'Rozdział pierwszy' }))
    await screen.findByText('A [1]word[/1].')

    // Zapytanie w tle (gdyby wciaz istnialo) startuje asynchronicznie zaraz
    // po zamontowaniu - dajemy mu okazje, zeby sie ujawnilo, zanim policzymy.
    await new Promise((resolve) => setTimeout(resolve, 50))

    expect(ch1Requests).toBe(1)
  })

  it('does not reload the open preview out from under an in-progress edit', async () => {
    // Zapis akapitu uniewaznia zapytanie podgladu (patrz useSegmentEditor),
    // ale ramka jest wlasnie otwarta na tym rozdziale - jesli uniewaznienie
    // wyzwoli natychmiastowe odswiezenie aktywnego zapytania, dokument
    // zostanie wyrwany spod kursora w trakcie pisania. To ma nie nastapic.
    let ch1Requests = 0

    server.use(
      http.get(`${API}/api/projects/p/preview/ch-1`, () => {
        ch1Requests += 1

        return HttpResponse.text(
          '<html><body><p data-segment-id="seg-1">Jakieś <em>słowo</em>.</p></body></html>',
        )
      }),
      http.patch(`${API}/api/segments/seg-1`, () =>
        HttpResponse.json({
          id: 'seg-1',
          position: 0,
          nodeIndex: 0,
          subIndex: 0,
          sourceText: 'A [1]word[/1].',
          translatedText: 'Nowe [1]słowo[/1].',
          status: 'edited',
          errorMessage: null,
          previewPlaceholders: { '1': '<em>' },
          chapter: { id: 'ch-1', spineOrder: 0, title: 'Rozdział pierwszy' },
        }),
      ),
    )

    renderEditor()

    await screen.findByText('A [1]word[/1].')
    await waitFor(() => {
      expect(ch1Requests).toBe(1)
    })

    // fireEvent, nie userEvent.type: nawiasy kwadratowe w tokenach maja
    // specjalne znaczenie w skladni userEvent (jak "{enter}") i zostalyby
    // zle zinterpretowane, gdyby wpisywac to znak po znaku.
    const field = screen.getByLabelText('Tłumaczenie akapitu 1')
    fireEvent.change(field, { target: { value: 'Nowe [1]słowo[/1].' } })

    await waitFor(
      () => {
        expect(screen.getByText('Zapisano')).toBeInTheDocument()
      },
      { timeout: 5000 },
    )

    // Zapis juz sie powiodl (a wiec i uniewaznienie zapytania podgladu tez);
    // zapytanie podgladu ma zostac tylko oznaczone jako nieaktualne, a nie
    // odswiezone teraz - stad ta sama, jedna liczba co przed edycja.
    expect(ch1Requests).toBe(1)
  }, 10000)

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
