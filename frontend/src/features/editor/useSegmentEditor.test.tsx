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
})
