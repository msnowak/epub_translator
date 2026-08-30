import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, renderHook, waitFor } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import type { ReactNode } from 'react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { setAccessToken } from '../../api/client'
import type { Segment } from '../../api/types'
import { LocaleProvider } from '../../i18n/LocaleProvider'
import { server } from '../../test/server'
import { useSegmentEditor } from './useSegmentEditor'

const API = 'http://localhost:8000'

const segment: Segment = {
  id: 'seg-1',
  position: 0,
  nodeIndex: 0,
  subIndex: 0,
  sourceText: 'A [1]word[/1].',
  translatedText: 'Jakieś [1]słowo[/1].',
  status: 'translated',
  errorCode: null,
  errorParams: null,
  previewPlaceholders: { '1': '<em>' },
  chapter: { id: 'ch-1', spineOrder: 0, title: 'Rozdział pierwszy' },
}

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } })

  return (
    <LocaleProvider initial="pl">
      <QueryClientProvider client={client}>{children}</QueryClientProvider>
    </LocaleProvider>
  )
}

describe('useSegmentEditor', () => {
  beforeEach(() => {
    setAccessToken('token')
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('reports itself dirty while typing and clean again once the save lands', async () => {
    const onDirtyChange = vi.fn()

    server.use(
      http.patch(`${API}/api/segments/seg-1`, () =>
        HttpResponse.json({ ...segment, translatedText: 'Nowe [1]słowo[/1].', status: 'edited' }),
      ),
    )

    const { result } = renderHook(
      () => useSegmentEditor({ segment, chapterId: 'ch-1', onPreview: vi.fn(), onDirtyChange }),
      { wrapper },
    )

    act(() => {
      result.current.change('Nowe [1]słowo[/1].')
    })

    expect(onDirtyChange).toHaveBeenCalledWith('seg-1', true)

    await act(async () => {
      await vi.advanceTimersByTimeAsync(800)
    })

    expect(result.current.state).toBe('saved')
    expect(onDirtyChange).toHaveBeenLastCalledWith('seg-1', false)
  })

  it('shows the change in the preview before it saves it', async () => {
    const preview = vi.fn()
    const saved: string[] = []

    server.use(
      http.patch(`${API}/api/segments/seg-1`, async ({ request }) => {
        saved.push(await request.text())

        return HttpResponse.json({ ...segment, translatedText: 'Nowe [1]słowo[/1].', status: 'edited' })
      }),
    )

    const { result } = renderHook(() => useSegmentEditor({ segment, chapterId: 'ch-1', onPreview: preview }), {
      wrapper,
    })

    act(() => {
      result.current.change('Nowe [1]słowo[/1].')
    })

    await act(async () => {
      await vi.advanceTimersByTimeAsync(400)
    })

    expect(preview).toHaveBeenCalledWith('seg-1', 'Nowe <em>słowo</em>.')
    expect(saved).toHaveLength(0)

    await act(async () => {
      await vi.advanceTimersByTimeAsync(400)
    })

    expect(saved).toEqual(['{"translatedText":"Nowe [1]słowo[/1]."}'])
    expect(result.current.state).toBe('saved')
  })

  it('invalidates the project-wide failed list once a manual correction saves', async () => {
    server.use(
      http.patch(`${API}/api/segments/seg-1`, () =>
        HttpResponse.json({ ...segment, translatedText: 'Nowe [1]słowo[/1].', status: 'edited' }),
      ),
    )

    const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')

    function localWrapper({ children }: { children: ReactNode }) {
      return (
        <LocaleProvider initial="pl">
          <QueryClientProvider client={client}>{children}</QueryClientProvider>
        </LocaleProvider>
      )
    }

    const { result } = renderHook(
      () => useSegmentEditor({ segment, chapterId: 'ch-1', onPreview: vi.fn() }),
      { wrapper: localWrapper },
    )

    act(() => {
      result.current.change('Nowe [1]słowo[/1].')
    })

    await act(async () => {
      await vi.advanceTimersByTimeAsync(800)
    })

    expect(result.current.state).toBe('saved')
    // Recznie poprawiony akapit wraca "edited" - lista nieudanych akapitow w
    // widoku projektu musi to zauwazyc, a nie trzymac stary blad z cache.
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ['segments', 'failed'] })
  })

  it('clears a stale save-error banner for this chapter as soon as a row mounts', () => {
    // Klucz ['segments', 'save-error', chapterId] ma staleTime: Infinity po
    // stronie EditorPage, wiec bez tego czyszczenia wpis sprzed maks. 5 minut
    // (domyslne gcTime) potrafilby przezyc powrot do tego samego rozdzialu i
    // wyplynac jako baner bez zadnego biezacego bledu za nim.
    const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } })

    client.setQueryData(['segments', 'save-error', 'ch-1'], 'Stary błąd sprzed 5 minut.')

    function localWrapper({ children }: { children: ReactNode }) {
      return (
        <LocaleProvider initial="pl">
          <QueryClientProvider client={client}>{children}</QueryClientProvider>
        </LocaleProvider>
      )
    }

    renderHook(() => useSegmentEditor({ segment, chapterId: 'ch-1', onPreview: vi.fn() }), {
      wrapper: localWrapper,
    })

    expect(client.getQueryData(['segments', 'save-error', 'ch-1'])).toBeNull()
  })

  it('clears the save-error channel for this chapter once a save succeeds', async () => {
    // Dopelnienie testu wyzej: sam mount czysci tylko wpis sprzed montowania -
    // zapis, ktory sie powiodl w trakcie zycia wiersza, ma sprzatnac po sobie
    // rowniez, gdyby kanal zdazyl juz cos zapisac (np. wczesniejszy nieudany
    // zapis tego samego wiersza).
    server.use(
      http.patch(`${API}/api/segments/seg-1`, () =>
        HttpResponse.json({ ...segment, translatedText: 'Nowe [1]słowo[/1].', status: 'edited' }),
      ),
    )

    const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } })

    function localWrapper({ children }: { children: ReactNode }) {
      return (
        <LocaleProvider initial="pl">
          <QueryClientProvider client={client}>{children}</QueryClientProvider>
        </LocaleProvider>
      )
    }

    const { result } = renderHook(
      () => useSegmentEditor({ segment, chapterId: 'ch-1', onPreview: vi.fn() }),
      { wrapper: localWrapper },
    )

    client.setQueryData(['segments', 'save-error', 'ch-1'], 'Poprzedni nieudany zapis.')

    act(() => {
      result.current.change('Nowe [1]słowo[/1].')
    })

    await act(async () => {
      await vi.advanceTimersByTimeAsync(800)
    })

    expect(result.current.state).toBe('saved')
    expect(client.getQueryData(['segments', 'save-error', 'ch-1'])).toBeNull()
  })

  it('blocks a save when a whole token number disappears mid-edit', async () => {
    // tokenSignature() jest teraz nieczuly na liczbe wystapien (patrz nizej),
    // wiec usuniecie jednego z dwoch wystapien tego samego numeru juz nie
    // blokuje - to swiadomy kompromis (patrz detokenize.test.ts). Zniknieciu
    // calego numeru zetonu wciaz ma zapobiegac przed wyslaniem do backendu.
    const saved: string[] = []
    const twoTokenSegment: Segment = {
      ...segment,
      sourceText: 'A [1]big[/1] [2]red[/2] house.',
      translatedText: 'Duży [1]czerwony[/1] [2]dom[/2].',
    }

    server.use(
      http.patch(`${API}/api/segments/seg-1`, async ({ request }) => {
        saved.push(await request.text())

        return HttpResponse.json(twoTokenSegment)
      }),
    )

    const { result } = renderHook(
      () => useSegmentEditor({ segment: twoTokenSegment, chapterId: 'ch-1', onPreview: vi.fn() }),
      { wrapper },
    )

    act(() => {
      result.current.change('Duży czerwony [2]dom[/2].')
    })

    await act(async () => {
      await vi.advanceTimersByTimeAsync(2000)
    })

    expect(saved).toHaveLength(0)
    expect(result.current.state).toBe('blocked')
    expect(result.current.message).toContain('znaczniki')
  })

  it('clears the dirty flag on unmount even while blocked, with no save timer pending', async () => {
    // Regresja: cleanup przy odmontowaniu wracal wczesniej od razu, gdy
    // saveTimer.current === null - a "blocked" (znacznik jeszcze niedomkniety)
    // nigdy nie planuje timera zapisu, wiec dirtyIdsRef w EditorPage nigdy nie
    // odzyskiwalo tego id. Lokalna wartosc i tak przepada przy odmontowaniu,
    // wiec nie ma juz czego chronic.
    const onDirtyChange = vi.fn()

    const { result, unmount } = renderHook(
      () => useSegmentEditor({ segment, chapterId: 'ch-1', onPreview: vi.fn(), onDirtyChange }),
      { wrapper },
    )

    act(() => {
      // Zaden zeton nie zostal - to rozni sie od sygnatury zrodla ('1:paired'),
      // wiec pozostaje zablokowane (patrz test wyzej dla tego samego wzorca).
      result.current.change('Zmiana bez zadnego zetonu')
    })

    expect(result.current.state).toBe('blocked')
    expect(onDirtyChange).toHaveBeenLastCalledWith('seg-1', true)

    unmount()

    expect(onDirtyChange).toHaveBeenLastCalledWith('seg-1', false)
  })

  it('does not block a translation that repeats one token a different number of times than the source', async () => {
    // TranslationValidator::tokenKinds() (backend) klucz'uje po numerze
    // zetonu, nie po liczbie wystapien - ten sam numer moze powtorzyc sie
    // inna ilosc razy niz w zrodle i backend to zaakceptuje. Przewodnik po
    // stronie przegladarki ma dawac ten sam wynik, a nie blokowac zapis,
    // ktory backend by przyjal (patrz przeglad stage 7, finding 3).
    const saved: string[] = []
    const repeatSegment: Segment = { ...segment, sourceText: 'The [1]big red[/1] house.' }

    server.use(
      http.patch(`${API}/api/segments/seg-1`, async ({ request }) => {
        saved.push(await request.text())

        return HttpResponse.json({ ...repeatSegment, translatedText: '[1]Duży[/1] czerwony [1]dom[/1].', status: 'edited' })
      }),
    )

    const { result } = renderHook(
      () => useSegmentEditor({ segment: repeatSegment, chapterId: 'ch-1', onPreview: vi.fn() }),
      { wrapper },
    )

    act(() => {
      result.current.change('[1]Duży[/1] czerwony [1]dom[/1].')
    })

    await act(async () => {
      await vi.advanceTimersByTimeAsync(2000)
    })

    expect(saved).toEqual(['{"translatedText":"[1]Duży[/1] czerwony [1]dom[/1]."}'])
    expect(result.current.state).toBe('saved')
  })

  it('shows what the backend said when it refuses the save', async () => {
    server.use(
      http.patch(`${API}/api/segments/seg-1`, () =>
        HttpResponse.json(
          { status: 422, detail: 'Tłumaczenie musi zawierać te same znaczniki formatowania co oryginał.' },
          { status: 422, headers: { 'Content-Type': 'application/problem+json' } },
        ),
      ),
    )

    const { result } = renderHook(
      () => useSegmentEditor({ segment, chapterId: 'ch-1', onPreview: vi.fn() }),
      { wrapper },
    )

    act(() => {
      result.current.change('Inne [1]słowo[/1].')
    })

    await act(async () => {
      await vi.advanceTimersByTimeAsync(1000)
    })

    expect(result.current.state).toBe('error')
    expect(result.current.message).toContain('te same znaczniki')
  })

  it('takes a new translation from the server while the row is clean', () => {
    const { result, rerender } = renderHook(
      ({ current }: { current: Segment }) =>
        useSegmentEditor({ segment: current, chapterId: 'ch-1', onPreview: vi.fn() }),
      { wrapper, initialProps: { current: segment } },
    )

    rerender({ current: { ...segment, translatedText: 'Po ponowieniu.' } })

    expect(result.current.value).toBe('Po ponowieniu.')
  })

  it('keeps an unsaved change when a new translation arrives from the server', () => {
    // Zarejestrowany handler nie jest tu przedmiotem asercji - zabezpiecza
    // przed onUnhandledRequest: 'error', gdyby debounce zapisu dobiegl konca
    // (np. przy odmontowaniu na koniec testu) bez niego test by sie wywalil.
    server.use(
      http.patch(`${API}/api/segments/seg-1`, () => HttpResponse.json(segment)),
    )

    const { result, rerender } = renderHook(
      ({ current }: { current: Segment }) =>
        useSegmentEditor({ segment: current, chapterId: 'ch-1', onPreview: vi.fn() }),
      { wrapper, initialProps: { current: segment } },
    )

    act(() => {
      // Znaczniki zgadzaja sie ze zrodlem - to zwykla, nieblokowana edycja
      // wciaz czekajaca w debouncie zapisu, nie przypadek "blocked".
      result.current.change('Moja [1]niezapisana[/1] zmiana.')
    })

    // Wiersz jest w tym momencie dirty - odpowiedz z serwera nie moze wygrac.
    rerender({ current: { ...segment, translatedText: 'Z serwera.' } })

    expect(result.current.value).toBe('Moja [1]niezapisana[/1] zmiana.')
  })

  it('keeps the cache holding the saved text after the row unmounts mid-debounce', async () => {
    // SegmentList wirtualizuje wiersze - "napisz, potem przewin liste" zdejmuje
    // wiersz z DOM-u rutynowo, nie wyjatkowo. Ta sciezka ma isc przez ta sama
    // mutacje co zwykly zapis (patrz useSegmentEditor), zeby onSuccess wpisal
    // wynik do cache'u - a nie goly fetch, ktorego nikt nie odbiera.
    vi.useRealTimers()

    const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } })

    client.setQueryData(['segments', 'ch-1'], [segment])

    function localWrapper({ children }: { children: ReactNode }) {
      return (
        <LocaleProvider initial="pl">
          <QueryClientProvider client={client}>{children}</QueryClientProvider>
        </LocaleProvider>
      )
    }

    server.use(
      http.patch(`${API}/api/segments/seg-1`, () =>
        HttpResponse.json({ ...segment, translatedText: 'Nowe [1]słowo[/1].', status: 'edited' }),
      ),
    )

    const { result, unmount } = renderHook(
      () => useSegmentEditor({ segment, chapterId: 'ch-1', onPreview: vi.fn() }),
      { wrapper: localWrapper },
    )

    act(() => {
      result.current.change('Nowe [1]słowo[/1].')
    })

    unmount()

    await waitFor(() => {
      const cached = client.getQueryData<Segment[]>(['segments', 'ch-1'])

      expect(cached?.[0]?.translatedText).toBe('Nowe [1]słowo[/1].')
    })
  })

  it('surfaces a keepalive save failure on the page-level channel once the row is gone', async () => {
    // Po odmontowaniu nie ma juz gdzie pokazac message/state lokalnie (patrz
    // test wyzej) - reportError() w useSegmentEditor ma wtedy pisac do
    // ['segments', 'save-error', chapterId], jedynego kanalu, ktory strone
    // przezywa odmontowanie wiersza.
    vi.useRealTimers()

    const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } })

    function localWrapper({ children }: { children: ReactNode }) {
      return (
        <LocaleProvider initial="pl">
          <QueryClientProvider client={client}>{children}</QueryClientProvider>
        </LocaleProvider>
      )
    }

    server.use(
      http.patch(`${API}/api/segments/seg-1`, () =>
        HttpResponse.json(
          { status: 422, detail: 'Tłumaczenie musi zawierać te same znaczniki formatowania co oryginał.' },
          { status: 422, headers: { 'Content-Type': 'application/problem+json' } },
        ),
      ),
    )

    const { result, unmount } = renderHook(
      () => useSegmentEditor({ segment, chapterId: 'ch-1', onPreview: vi.fn() }),
      { wrapper: localWrapper },
    )

    act(() => {
      result.current.change('Nowe [1]słowo[/1].')
    })

    unmount()

    await waitFor(() => {
      expect(client.getQueryData(['segments', 'save-error', 'ch-1'])).toContain('te same znaczniki')
    })
  })
})
