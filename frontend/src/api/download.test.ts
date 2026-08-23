import { HttpResponse, http } from 'msw'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { server } from '../test/server'
import { setAccessToken } from './client'
import { downloadProject, filenameFromDisposition } from './download'

const API = 'http://localhost:8000'

afterEach(() => {
  setAccessToken(null)
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})

describe('filenameFromDisposition', () => {
  it('prefers the UTF-8 form over the ASCII fallback', () => {
    const header =
      'attachment; filename="Wyspa skarbow-pl.epub"; ' +
      "filename*=UTF-8''Wyspa%20skarb%C3%B3w-pl.epub"

    expect(filenameFromDisposition(header)).toBe('Wyspa skarbów-pl.epub')
  })

  it('falls back to the ASCII name when there is no UTF-8 one', () => {
    expect(filenameFromDisposition('attachment; filename="book-pl.epub"')).toBe('book-pl.epub')
  })

  it('says nothing when the header says nothing', () => {
    expect(filenameFromDisposition(null)).toBeNull()
  })
})

describe('downloadProject', () => {
  it('hands the file to the browser and releases the object URL', async () => {
    setAccessToken('token-1')
    server.use(
      http.get(`${API}/api/projects/p1/download`, () =>
        HttpResponse.text('PK', {
          headers: {
            'Content-Type': 'application/epub+zip',
            'Content-Disposition': 'attachment; filename="book-pl.epub"',
          },
        }),
      ),
    )
    // Podmieniamy dwie metody statyczne, a nie caly obiekt URL: MSW woła
    // new URL() na kazdym zadaniu i stub calosci wywala interceptor.
    const createObjectURL = vi.fn(() => 'blob:test')
    const revokeObjectURL = vi.fn()
    const originalCreate = URL.createObjectURL
    const originalRevoke = URL.revokeObjectURL
    URL.createObjectURL = createObjectURL
    URL.revokeObjectURL = revokeObjectURL
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined)

    try {
      await downloadProject('p1')
    } finally {
      URL.createObjectURL = originalCreate
      URL.revokeObjectURL = originalRevoke
    }

    expect(click).toHaveBeenCalledOnce()
    expect(createObjectURL).toHaveBeenCalledOnce()
    // Bez tego kazde pobranie zostawialoby plik w pamieci karty do konca jej
    // zycia.
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:test')
  })

  it('reports what the server said when the project cannot be downloaded yet', async () => {
    setAccessToken('token-1')
    server.use(
      http.get(`${API}/api/projects/p1/download`, () =>
        HttpResponse.json(
          { status: 409, detail: 'Ta książka nie jest jeszcze gotowa do pobrania.' },
          { status: 409 },
        ),
      ),
    )

    await expect(downloadProject('p1')).rejects.toThrow('Ta książka nie jest jeszcze gotowa do pobrania.')
  })
})
