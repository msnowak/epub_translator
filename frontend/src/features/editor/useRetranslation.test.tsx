import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, renderHook, waitFor } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it } from 'vitest'
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

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })

  client.setQueryData(['segments', 'ch-1'], [segment])

  return <QueryClientProvider client={client}>{children}</QueryClientProvider>
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

    const { result } = renderHook(() => useRetranslation('ch-1'), { wrapper })

    act(() => {
      result.current.retranslate('seg-1')
    })

    await waitFor(() => {
      expect(result.current.awaiting.size).toBe(0)
    }, { timeout: 10000 })
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

    const { result } = renderHook(() => useRetranslation('ch-1'), { wrapper })

    act(() => {
      result.current.retranslate('seg-1')
    })

    await waitFor(() => {
      expect(result.current.error).toBe('Ten akapit jest właśnie tłumaczony.')
    })
  })
})
