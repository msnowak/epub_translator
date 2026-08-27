import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { beforeEach, describe, expect, it } from 'vitest'
import { setAccessToken } from '../../api/client'
import { renderWithProviders } from '../../test/renderWithProviders'
import { server } from '../../test/server'
import { FailedSegments } from './FailedSegments'

const API = 'http://localhost:8000'

describe('FailedSegments', () => {
  beforeEach(() => {
    setAccessToken('token')
  })

  it('links a failed paragraph to its place in the editor', async () => {
    server.use(
      http.get(`${API}/api/projects/p/segments`, ({ request }) => {
        expect(new URL(request.url).searchParams.get('status')).toBe('failed')

        return HttpResponse.json([
          {
            id: 'seg-9',
            position: 4,
            nodeIndex: 4,
            subIndex: 0,
            sourceText: 'A paragraph that broke.',
            translatedText: null,
            status: 'failed',
            errorMessage: 'Model nie odpowiedział.',
            previewPlaceholders: [],
            chapter: { id: 'ch-2', spineOrder: 1, title: null },
          },
        ])
      }),
    )

    renderWithProviders(<FailedSegments projectId="p" />)

    const link = await screen.findByRole('link', { name: /Rozdział 2/ })

    expect(link).toHaveAttribute('href', '/projekty/p/rozdzialy/ch-2?akapit=seg-9')
    expect(screen.getByText('Model nie odpowiedział.')).toBeInTheDocument()
  })

  it('says nothing failed when nothing failed', async () => {
    server.use(http.get(`${API}/api/projects/p/segments`, () => HttpResponse.json([])))

    renderWithProviders(<FailedSegments projectId="p" />)

    expect(await screen.findByText('Żaden akapit nie zgłosił błędu.')).toBeInTheDocument()
  })
})
