import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, renderHook, waitFor } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { setAccessToken } from '../../api/client'
import type { Segment } from '../../api/types'
import { server } from '../../test/server'
import { useRetranslation } from './useRetranslation'

const API = 'http://localhost:8000'

const segment: Segment = {
  id: 'seg-1',
  position: 0,
  nodeIndex: 0,
  subIndex: 0,
  sourceText: 'Source.',
  translatedText: null,
  status: 'failed',
  errorMessage: 'Model nie odpowiedział.',
  previewPlaceholders: {},
  chapter: { id: 'ch-1', spineOrder: 0, title: 'Rozdział pierwszy' },
}

// Zwraca zarowno komponent-wrapper, jak i sam QueryClient - testy musza
// odczytac cache po zakonczeniu odpytywania, zeby stwierdzic, ze poll
// naprawde zapisal to, co przeczytal, a nie tylko wyczyscil zbior awaiting.
function createWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })

  client.setQueryData(['segments', 'ch-1'], [segment])

  function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={client}>{children}</QueryClientProvider>
  }

  return { Wrapper, client }
}

describe('useRetranslation', () => {
  beforeEach(() => {
    setAccessToken('token')
  })

  it('follows one paragraph until the worker is done with it', async () => {
    let reads = 0

    server.use(
      http.post(`${API}/api/segments/seg-1/retranslate`, () =>
        HttpResponse.json({ ...segment, status: 'processing' }),
      ),
      http.get(`${API}/api/segments/seg-1`, () => {
        reads += 1

        return HttpResponse.json(
          reads < 2
            ? { ...segment, status: 'processing' }
            : { ...segment, status: 'translated', translatedText: 'Gotowe.', errorMessage: null },
        )
      }),
    )

    const { Wrapper, client } = createWrapper()
    const onPreview = vi.fn()
    const { result } = renderHook(() => useRetranslation('ch-1', onPreview), { wrapper: Wrapper })

    act(() => {
      result.current.retranslate('seg-1')
    })

    // Przypina moment, w ktorym mutacja sie powiodla i akapit wszedl do
    // awaiting - bez tego pierwsze wywolanie waitFor nizej mogloby przejsc
    // na pustym, poczatkowym stanie, zanim jakikolwiek odczyt sie wydarzy.
    await waitFor(() => {
      expect(result.current.awaiting.size).toBe(1)
    })

    await waitFor(() => {
      expect(result.current.awaiting.size).toBe(0)
    }, { timeout: 10000 })

    // Dowod, ze poll faktycznie zapisal przeczytany wynik do cache, a nie
    // tylko wyczyscil zbior awaiting.
    const cached = client.getQueryData<Segment[]>(['segments', 'ch-1'])
    const updated = cached?.find((item) => 'seg-1' === item.id)

    expect(updated?.status).toBe('translated')
    expect(updated?.translatedText).toBe('Gotowe.')
    // Otwarty podglad ma zobaczyc to samo tlumaczenie, nie tylko cache.
    expect(onPreview).toHaveBeenCalledWith('seg-1', 'Gotowe.')
  })

  it('invalidates the project-wide failed list once a retranslated paragraph settles', async () => {
    server.use(
      http.post(`${API}/api/segments/seg-1/retranslate`, () =>
        HttpResponse.json({ ...segment, status: 'processing' }),
      ),
      http.get(`${API}/api/segments/seg-1`, () =>
        HttpResponse.json({ ...segment, status: 'translated', translatedText: 'Gotowe.', errorMessage: null }),
      ),
    )

    const { Wrapper, client } = createWrapper()
    // ProjectDetailPage nie zna id akapitu, wiec sprawdzamy dokladnie ten
    // klucz-prefiks, po ktorym FailedSegments odczytuje swoje dane.
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')

    const onPreview = vi.fn()
    const { result } = renderHook(() => useRetranslation('ch-1', onPreview), { wrapper: Wrapper })

    act(() => {
      result.current.retranslate('seg-1')
    })

    // Bez tego punktu synchronizacji "size===0" ponizej moglby przejsc na
    // stanie poczatkowym, zanim mutacja w ogole ruszyla - patrz test wyzej.
    await waitFor(() => {
      expect(result.current.awaiting.size).toBe(1)
    })

    await waitFor(() => {
      expect(result.current.awaiting.size).toBe(0)
    }, { timeout: 10000 })

    // Bez tego wywolania widok projektu wciaz pokazywalby ten akapit jako
    // nieudany, mimo ze poll juz go widzial przetlumaczonym.
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ['segments', 'failed'] })
  })

  it('shows what the backend said when the paragraph is already being translated', async () => {
    server.use(
      http.post(`${API}/api/segments/seg-1/retranslate`, () =>
        HttpResponse.json(
          { status: 409, detail: 'Ten akapit jest właśnie tłumaczony.' },
          { status: 409, headers: { 'Content-Type': 'application/problem+json' } },
        ),
      ),
    )

    const { Wrapper } = createWrapper()
    const onPreview = vi.fn()
    const { result } = renderHook(() => useRetranslation('ch-1', onPreview), { wrapper: Wrapper })

    act(() => {
      result.current.retranslate('seg-1')
    })

    await waitFor(() => {
      expect(result.current.error).toBe('Ten akapit jest właśnie tłumaczony.')
    })
  })

  it('stops polling and reports the failure when a poll itself fails', async () => {
    server.use(
      http.post(`${API}/api/segments/seg-1/retranslate`, () =>
        HttpResponse.json({ ...segment, status: 'processing' }),
      ),
      http.get(`${API}/api/segments/seg-1`, () =>
        HttpResponse.json(
          { status: 500, detail: 'Serwer chwilowo nie odpowiada.' },
          { status: 500, headers: { 'Content-Type': 'application/problem+json' } },
        ),
      ),
    )

    const { Wrapper } = createWrapper()
    const onPreview = vi.fn()
    const { result } = renderHook(() => useRetranslation('ch-1', onPreview), { wrapper: Wrapper })

    act(() => {
      result.current.retranslate('seg-1')
    })

    await waitFor(() => {
      expect(result.current.awaiting.size).toBe(1)
    })

    await waitFor(() => {
      expect(result.current.error).toBe('Serwer chwilowo nie odpowiada.')
    }, { timeout: 10000 })

    // Odczyt, ktory sie nie powiodl, musi zdjac akapit z awaiting - inaczej
    // interval probowalby bez konca, mimo widocznego bledu.
    expect(result.current.awaiting.size).toBe(0)
  })

  it('keeps a paragraph on its original poll schedule when a second one starts mid-window', async () => {
    // Zakres tego jednego testu, nie calego pliku - pozostale testy licza na
    // prawdziwy uplyw czasu (patrz komentarze przy setInterval w haku).
    vi.useFakeTimers()

    try {
      let readsA = 0

      server.use(
        http.post(`${API}/api/segments/seg-1/retranslate`, () =>
          HttpResponse.json({ ...segment, status: 'processing' }),
        ),
        http.post(`${API}/api/segments/seg-2/retranslate`, () =>
          HttpResponse.json({ ...segment, id: 'seg-2', status: 'processing' }),
        ),
        http.get(`${API}/api/segments/seg-1`, () => {
          readsA += 1

          return HttpResponse.json({ ...segment, status: 'processing' })
        }),
        http.get(`${API}/api/segments/seg-2`, () => HttpResponse.json({ ...segment, id: 'seg-2', status: 'processing' })),
      )

      const { Wrapper } = createWrapper()
      const onPreview = vi.fn()
      const { result } = renderHook(() => useRetranslation('ch-1', onPreview), { wrapper: Wrapper })

      // t=0: pierwszy akapit uzbraja interwal, ktory ma odpalic nastepny tick w t=2000.
      act(() => {
        result.current.retranslate('seg-1')
      })

      await act(async () => {
        await vi.advanceTimersByTimeAsync(0)
      })

      expect(result.current.awaiting.has('seg-1')).toBe(true)

      // t=1000: w polowie okna seg-1 startuje drugi akapit. Przy starej
      // zaleznosci po tozsamosci Seta to zbroiloby interwal od nowa i
      // przesunelo tick seg-1 na t=3000 zamiast t=2000.
      await act(async () => {
        await vi.advanceTimersByTimeAsync(1000)
      })

      act(() => {
        result.current.retranslate('seg-2')
      })

      await act(async () => {
        await vi.advanceTimersByTimeAsync(0)
      })

      expect(result.current.awaiting.has('seg-2')).toBe(true)

      // Tuz przed pierwotnym tickiem seg-1 (t=2000) - jeszcze nic nie odczytano.
      await act(async () => {
        await vi.advanceTimersByTimeAsync(900)
      })

      expect(readsA).toBe(0)

      // Mijamy t=2000: seg-1 musi zostac odpytany wedlug pierwotnego
      // harmonogramu, a nie dopiero w t=3000.
      await act(async () => {
        await vi.advanceTimersByTimeAsync(200)
      })

      expect(readsA).toBeGreaterThanOrEqual(1)
    } finally {
      vi.useRealTimers()
    }
  })
})
