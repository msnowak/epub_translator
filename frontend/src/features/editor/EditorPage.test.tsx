import { fireEvent, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { setAccessToken } from '../../api/client'
import { captureScrollToIndex, resetScrollToIndexSpy, scrollToIndexSpy } from '../../test/segmentListVirtualizer'
import { renderWithProviders } from '../../test/renderWithProviders'
import { server } from '../../test/server'
import { stubLayoutForVirtualization } from '../../test/virtualization'
import { EditorPage } from './EditorPage'

vi.mock('@tanstack/react-virtual', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@tanstack/react-virtual')>()

  return {
    ...actual,
    useVirtualizer: (options: Parameters<typeof actual.useVirtualizer>[0]) => {
      const instance = actual.useVirtualizer(options)

      captureScrollToIndex(instance)

      return instance
    },
  }
})

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

function renderEditor(route = '/projekty/p/rozdzialy/ch-1') {
  return renderWithProviders(
    <Routes>
      <Route path="/projekty/:id/rozdzialy/:chapterId" element={<EditorPage />} />
    </Routes>,
    { route },
  )
}

describe('EditorPage', () => {
  beforeEach(() => {
    setAccessToken('token')
    stubLayoutForVirtualization()
    resetScrollToIndexSpy()
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

  it('patches the open preview when a retranslation finishes', async () => {
    // Ponowienie tlumaczenia zmienia serwer sam z siebie, wiec (w odroznieniu
    // od recznej edycji) nic w tym przeplywie nie woalo dotad onPreview - do
    // czasu tej poprawki podgladu w ogole to nie widzial, dopoki ktos nie
    // wyszedl z rozdzialu i nie wrocil.
    server.use(
      http.post(`${API}/api/segments/seg-1/retranslate`, () =>
        HttpResponse.json({
          id: 'seg-1',
          position: 0,
          nodeIndex: 0,
          subIndex: 0,
          sourceText: 'A [1]word[/1].',
          translatedText: null,
          status: 'processing',
          errorMessage: null,
          previewPlaceholders: { '1': '<em>' },
          chapter: { id: 'ch-1', spineOrder: 0, title: 'Rozdział pierwszy' },
        }),
      ),
      http.get(`${API}/api/segments/seg-1`, () =>
        HttpResponse.json({
          id: 'seg-1',
          position: 0,
          nodeIndex: 0,
          subIndex: 0,
          sourceText: 'A [1]word[/1].',
          translatedText: 'Świeże [1]tłumaczenie[/1].',
          status: 'translated',
          errorMessage: null,
          previewPlaceholders: { '1': '<em>' },
          chapter: { id: 'ch-1', spineOrder: 0, title: 'Rozdział pierwszy' },
        }),
      ),
    )

    renderEditor()

    await screen.findByText('A [1]word[/1].')

    const frame = screen.getByTitle('Podgląd rozdziału') as HTMLIFrameElement
    const inner = frame.contentDocument

    if (null === inner) {
      throw new Error('brak contentDocument ramki w tym srodowisku testowym')
    }

    // jsdom nie wczytuje tresci srcdoc do contentDocument (znane
    // ograniczenie tego silnika) - odtwarzamy tu recznie to, co w prawdziwej
    // przegladarce zrobilby parser dokumentu wstrzyknietego przez srcDoc,
    // zeby miec realny wezel [data-segment-id], ktory patchPreview podmienia.
    inner.body.innerHTML = '<p data-segment-id="seg-1">Jakieś <em>słowo</em>.</p>'

    await userEvent.click(screen.getByRole('button', { name: 'Przetłumacz ponownie' }))

    await waitFor(
      () => {
        expect(inner.querySelector('[data-segment-id="seg-1"]')?.innerHTML).toBe(
          'Świeże <em>tłumaczenie</em>.',
        )
      },
      { timeout: 10000 },
    )
  }, 12000)

  it('lets a retranslation for a merely-focused paragraph reach the preview', async () => {
    // Klikniecie w akapit zeby go przeczytac, a potem "Przetlumacz ponownie"
    // to zwykly przeplyw - fokus sam z siebie nie chroni niczego (patrz
    // przeglad stage 7, finding 5: straznik kluczowany samym fokusem blokowal
    // ten wlasnie przypadek na stale, bo focus nigdy sie nie czyscil).
    server.use(
      http.post(`${API}/api/segments/seg-1/retranslate`, () =>
        HttpResponse.json({
          id: 'seg-1',
          position: 0,
          nodeIndex: 0,
          subIndex: 0,
          sourceText: 'A [1]word[/1].',
          translatedText: null,
          status: 'processing',
          errorMessage: null,
          previewPlaceholders: { '1': '<em>' },
          chapter: { id: 'ch-1', spineOrder: 0, title: 'Rozdział pierwszy' },
        }),
      ),
      http.get(`${API}/api/segments/seg-1`, () =>
        HttpResponse.json({
          id: 'seg-1',
          position: 0,
          nodeIndex: 0,
          subIndex: 0,
          sourceText: 'A [1]word[/1].',
          translatedText: 'Świeże [1]tłumaczenie[/1].',
          status: 'translated',
          errorMessage: null,
          previewPlaceholders: { '1': '<em>' },
          chapter: { id: 'ch-1', spineOrder: 0, title: 'Rozdział pierwszy' },
        }),
      ),
    )

    renderEditor()

    await screen.findByText('A [1]word[/1].')

    const frame = screen.getByTitle('Podgląd rozdziału') as HTMLIFrameElement
    const inner = frame.contentDocument

    if (null === inner) {
      throw new Error('brak contentDocument ramki w tym srodowisku testowym')
    }

    inner.body.innerHTML = '<p data-segment-id="seg-1">Jakieś <em>słowo</em>.</p>'

    // jsdom nie implementuje scrollIntoView w zadnym realmie. Inne testy w
    // tym pliku go nie potrzebuja, bo ich contentDocument zostaje pusty, wiec
    // findBlock() nic nie znajduje - ten test celowo wypelnia contentDocument
    // prawdziwym wezlem, a fokus nizej wywola scrollSegmentIntoView na nim,
    // w realmie ramki (inny Element niz w tym pliku testowym - patrz preview.
    // test.ts dla wyjasnienia, dlaczego to nie ten sam konstruktor).
    if (null !== inner.defaultView) {
      inner.defaultView.Element.prototype.scrollIntoView = () => {}
    }

    fireEvent.focus(screen.getByLabelText('Tłumaczenie akapitu 1'))

    await userEvent.click(screen.getByRole('button', { name: 'Przetłumacz ponownie' }))

    await waitFor(
      () => {
        expect(inner.querySelector('[data-segment-id="seg-1"]')?.innerHTML).toBe(
          'Świeże <em>tłumaczenie</em>.',
        )
      },
      { timeout: 10000 },
    )
  }, 12000)

  it('leaves an unsaved edit in the preview alone while its retranslation is still pending', async () => {
    // W odroznieniu od testu wyzej: tu uzytkownik faktycznie pisze (nie samo
    // skupienie) - straznik ma chronic akurat ten przypadek, zeby ponowienie
    // w tle nie wygralo wyscigu z odpowiedzia serwera na wlasny debounce
    // zapisu tego wiersza. Patrz komentarz przy dirtyIdsRef w EditorPage.
    server.use(
      http.post(`${API}/api/segments/seg-1/retranslate`, () =>
        HttpResponse.json({
          id: 'seg-1',
          position: 0,
          nodeIndex: 0,
          subIndex: 0,
          sourceText: 'A [1]word[/1].',
          translatedText: null,
          status: 'processing',
          errorMessage: null,
          previewPlaceholders: { '1': '<em>' },
          chapter: { id: 'ch-1', spineOrder: 0, title: 'Rozdział pierwszy' },
        }),
      ),
      http.get(`${API}/api/segments/seg-1`, () =>
        HttpResponse.json({
          id: 'seg-1',
          position: 0,
          nodeIndex: 0,
          subIndex: 0,
          sourceText: 'A [1]word[/1].',
          translatedText: 'Świeże [1]tłumaczenie[/1].',
          status: 'translated',
          errorMessage: null,
          previewPlaceholders: { '1': '<em>' },
          chapter: { id: 'ch-1', spineOrder: 0, title: 'Rozdział pierwszy' },
        }),
      ),
      http.patch(`${API}/api/segments/seg-1`, () =>
        // Zawiesza sie na zawsze - ten test sprawdza tylko, ze podglad zostaje
        // nietkniety, dopoki wlasny zapis wiersza wciaz trwa/czeka.
        new Promise(() => {}),
      ),
    )

    renderEditor()

    await screen.findByText('A [1]word[/1].')

    const frame = screen.getByTitle('Podgląd rozdziału') as HTMLIFrameElement
    const inner = frame.contentDocument

    if (null === inner) {
      throw new Error('brak contentDocument ramki w tym srodowisku testowym')
    }

    inner.body.innerHTML = '<p data-segment-id="seg-1">Jakieś <em>słowo</em>.</p>'

    if (null !== inner.defaultView) {
      inner.defaultView.Element.prototype.scrollIntoView = () => {}
    }

    // fireEvent, nie userEvent.type - patrz komentarz w tescie zapisu wyzej
    // w tym pliku o nawiasach kwadratowych w tokenach.
    fireEvent.change(screen.getByLabelText('Tłumaczenie akapitu 1'), {
      target: { value: 'Moja [1]niezapisana[/1] zmiana.' },
    })

    await userEvent.click(screen.getByRole('button', { name: 'Przetłumacz ponownie' }))

    // Wlasny podglad pisania (debounce 400ms w useSegmentEditor) i tak
    // podmienia ten wezel niezaleznie od straznika ponizej - straznik chroni
    // tylko przed odpowiedzia RETRANSLACJI w tle, nie przed wlasnym podgladem
    // uzytkownika. Odczekujemy dluzej niz jeden cykl odpytywania (2s), zeby
    // dac retranslacji szanse dobiec konca i sprobowac (bezskutecznie)
    // podmienic wezel.
    await waitFor(() => {
      expect(inner.querySelector('[data-segment-id="seg-1"]')?.innerHTML).toBe('Moja <em>niezapisana</em> zmiana.')
    })

    await new Promise((resolve) => setTimeout(resolve, 2500))

    expect(inner.querySelector('[data-segment-id="seg-1"]')?.innerHTML).toBe('Moja <em>niezapisana</em> zmiana.')
  }, 12000)

  it('keeps only the failed paragraphs when asked to', async () => {
    renderEditor()

    await screen.findByText('A [1]word[/1].')
    await userEvent.click(screen.getByRole('button', { name: 'Tylko nieudane' }))

    await waitFor(() => {
      expect(screen.queryByText('A [1]word[/1].')).not.toBeInTheDocument()
    })
    expect(screen.getByText('W tym rozdziale nie ma nieudanych akapitów.')).toBeInTheDocument()
  })

  it('scrolls the paragraph list to the row a preview click landed on', async () => {
    renderEditor()

    await screen.findByText('A [1]word[/1].')

    const frame = screen.getByTitle('Podgląd rozdziału') as HTMLIFrameElement
    const inner = frame.contentDocument

    if (null === inner) {
      throw new Error('brak contentDocument ramki w tym srodowisku testowym')
    }

    // jsdom nie wczytuje tresci srcdoc do contentDocument - patrz komentarz w
    // teście "patches the open preview..." powyżej dla wyjaśnienia. Odtwarzamy
    // tu recznie wezel, ktory PreviewPane sluchalby na kliknieciu.
    inner.body.innerHTML = '<p data-segment-id="seg-1">Jakieś <em>słowo</em>.</p>'

    // jsdom nie implementuje scrollIntoView w zadnym realmie (patrz test
    // "lets a focused paragraph..." powyzej) - activate() je woła zawsze,
    // wiec bez tej zaslepki klikniecie rzucaloby wyjatkiem w nasluchu.
    if (null !== inner.defaultView) {
      inner.defaultView.Element.prototype.scrollIntoView = () => {}
    }

    const target = inner.querySelector('[data-segment-id="seg-1"]')

    if (null === target) {
      throw new Error('brak wezla akapitu w kontencie testowym ramki')
    }

    // PreviewPane podpina nasluch klikniec dopiero w onLoad ramki - w jsdom
    // to zdarzenie ladowania srcdoc jest asynchroniczne, wiec czekamy, az
    // klikniecie faktycznie dotrze do activate().
    await waitFor(() => {
      target.dispatchEvent(new inner.defaultView!.MouseEvent('click', { bubbles: true }))
      expect(scrollToIndexSpy()).toHaveBeenCalledWith(0, { align: 'center' })
    })
  })

  it('does not scroll the paragraph list when a row merely receives focus', async () => {
    renderEditor()

    await screen.findByText('A [1]word[/1].')
    fireEvent.focus(screen.getByLabelText('Tłumaczenie akapitu 1'))

    // Nie ma tu na co czekac asynchronicznie - focus dziala synchronicznie -
    // wiec sprawdzamy wprost, ze przewijanie sie nie zdarzylo.
    expect(scrollToIndexSpy()).not.toHaveBeenCalled()
  })

  it('scrolls to the requested paragraph when opened via ?akapit=', async () => {
    renderEditor('/projekty/p/rozdzialy/ch-1?akapit=seg-1')

    await screen.findByText('A [1]word[/1].')

    await waitFor(() => {
      expect(scrollToIndexSpy()).toHaveBeenCalledWith(0, { align: 'center' })
    })
  })

  it('keeps an edit made just before the row is unmounted, not the pre-edit text', async () => {
    // "Tylko nieudane" odmontowuje ten wiersz od razu (seg-1 ma status
    // "translated", nie "failed") - debounce zapisu (800ms) jeszcze nie
    // zdazyl odpalic, wiec to cwiczy dokladnie sciezke keepalive z cleanup w
    // useSegmentEditor (patrz przeglad stage 7, finding 2), nie zwykly zapis.
    server.use(
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

    fireEvent.change(screen.getByLabelText('Tłumaczenie akapitu 1'), {
      target: { value: 'Nowe [1]słowo[/1].' },
    })

    await userEvent.click(screen.getByRole('button', { name: 'Tylko nieudane' }))
    await screen.findByText('W tym rozdziale nie ma nieudanych akapitów.')

    await userEvent.click(screen.getByRole('button', { name: 'Wszystkie akapity' }))

    await waitFor(() => {
      expect(screen.getByDisplayValue('Nowe [1]słowo[/1].')).toBeInTheDocument()
    })
  })

  it('shows a keepalive save failure written to the query cache as a banner', async () => {
    // useSegmentEditor pisze tu (['segments', 'save-error', chapterId]) kiedy
    // zapis przy odmontowaniu wiersza sie nie uda - nie ma juz komu pokazac
    // bledu lokalnie (patrz przeglad stage 7, finding 2). EditorPage ma
    // czytac ten sam klucz i pokazac go jako baner.
    const { queryClient } = renderEditor()

    await screen.findByText('A [1]word[/1].')

    expect(screen.queryByText('Coś poszło nie tak.')).not.toBeInTheDocument()

    queryClient.setQueryData(['segments', 'save-error', 'ch-1'], 'Coś poszło nie tak.')

    await waitFor(() => {
      expect(screen.getByText('Coś poszło nie tak.')).toBeInTheDocument()
    })
  })

  it('reloads both the paragraph list and the preview from the banner button', async () => {
    // Obie kolumny czytaja ten sam rozdzial - klikniecie "Wczytaj ponownie" ma
    // odswiezyc obie, nie tylko liste akapitow (patrz przeglad stage 7,
    // finding 4).
    let segmentRequests = 0
    let previewRequests = 0

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
      http.get(`${API}/api/chapters/ch-1/segments`, () => {
        segmentRequests += 1

        return HttpResponse.json([
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
        ])
      }),
      http.get(`${API}/api/projects/p/preview/ch-1`, () => {
        previewRequests += 1

        return HttpResponse.text(
          '<html><body><p data-segment-id="seg-1">Jakieś <em>słowo</em>.</p></body></html>',
        )
      }),
    )

    renderEditor()

    await screen.findByText('A [1]word[/1].')
    await waitFor(() => {
      expect(segmentRequests).toBe(1)
      expect(previewRequests).toBe(1)
    })

    await userEvent.click(screen.getByRole('button', { name: 'Wczytaj ponownie' }))

    await waitFor(() => {
      expect(segmentRequests).toBe(2)
      expect(previewRequests).toBe(2)
    })
  })
})
