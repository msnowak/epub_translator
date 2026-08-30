import { HttpResponse, http } from 'msw'
import { beforeEach, describe, expect, it } from 'vitest'
import { server } from '../test/server'
import { setAccessToken } from './client'
import { listChapterSegments, updateSegment } from './segments'

const API = 'http://localhost:8000'

describe('segments api', () => {
  beforeEach(() => {
    setAccessToken('token')
  })

  it('treats an empty placeholder map as no tokens at all', async () => {
    server.use(
      http.get(`${API}/api/chapters/ch-1/segments`, () =>
        // PHP serializuje pusta mape jako [], nie {} - front nie moze sie na
        // tym wywrocic, bo to najczestszy przypadek: akapit bez znacznikow.
        HttpResponse.json([
          { ...base, id: 'seg-1', previewPlaceholders: [] },
          { ...base, id: 'seg-2', previewPlaceholders: { '1': '<em>' } },
        ]),
      ),
    )

    const segments = await listChapterSegments('ch-1')

    expect(segments[0].previewPlaceholders).toEqual({})
    expect(segments[1].previewPlaceholders).toEqual({ '1': '<em>' })
  })

  it('sends a merge patch, because plain json answers 415', async () => {
    const captured: { type: string | null; body: string | null } = { type: null, body: null }

    server.use(
      http.patch(`${API}/api/segments/seg-1`, async ({ request }) => {
        captured.type = request.headers.get('Content-Type')
        captured.body = await request.text()

        return HttpResponse.json({ ...base, id: 'seg-1', translatedText: 'Nowe.', status: 'edited' })
      }),
    )

    const saved = await updateSegment('seg-1', 'Nowe.')

    expect(captured.type).toBe('application/merge-patch+json')
    expect(captured.body).toBe('{"translatedText":"Nowe."}')
    expect(saved.status).toBe('edited')
  })
})

const base = {
  position: 0,
  nodeIndex: 0,
  subIndex: 0,
  sourceText: 'Source.',
  translatedText: null,
  status: 'pending',
  errorCode: null,
  errorParams: null,
  previewPlaceholders: {},
  chapter: { id: 'ch-1', spineOrder: 0, title: 'Rozdział pierwszy' },
}
