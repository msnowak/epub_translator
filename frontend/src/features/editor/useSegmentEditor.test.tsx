import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, renderHook } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import type { ReactNode } from 'react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { setAccessToken } from '../../api/client'
import type { Segment } from '../../api/types'
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
  errorMessage: null,
  previewPlaceholders: { '1': '<em>' },
  chapter: { id: 'ch-1', spineOrder: 0, title: 'Rozdział pierwszy' },
}

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } })

  return <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

describe('useSegmentEditor', () => {
  beforeEach(() => {
    setAccessToken('token')
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
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
      return <QueryClientProvider client={client}>{children}</QueryClientProvider>
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

  it('does not ask the backend to reject a half-typed token', async () => {
    const saved: string[] = []

    server.use(
      http.patch(`${API}/api/segments/seg-1`, async ({ request }) => {
        saved.push(await request.text())

        return HttpResponse.json(segment)
      }),
    )

    const { result } = renderHook(
      () => useSegmentEditor({ segment, chapterId: 'ch-1', onPreview: vi.fn() }),
      { wrapper },
    )

    act(() => {
      result.current.change('Jakieś słowo[/1].')
    })

    await act(async () => {
      await vi.advanceTimersByTimeAsync(2000)
    })

    expect(saved).toHaveLength(0)
    expect(result.current.state).toBe('blocked')
    expect(result.current.message).toContain('znaczniki')
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
})
