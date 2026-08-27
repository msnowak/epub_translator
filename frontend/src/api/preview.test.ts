import { HttpResponse, http } from 'msw'
import { beforeEach, describe, expect, it } from 'vitest'
import { server } from '../test/server'
import { setAccessToken } from './client'
import { absolutizeAssetUrls, loadChapterPreview } from './preview'

const API = 'http://localhost:8000'

describe('absolutizeAssetUrls', () => {
  it('points asset paths back at the API, because the document lands on our origin', () => {
    const html = absolutizeAssetUrls('<html><body><img src="/api/projects/p/assets/a.png?t=x"/></body></html>')

    expect(html).toContain(`src="${API}/api/projects/p/assets/a.png?t=x"`)
  })

  it('leaves an anchor alone', () => {
    // Kotwica przypisu ma dzialac w obrebie dokumentu; <base> zepsulby ja
    // wlasnie tu, dlatego przepisujemy atrybuty, a nie ustawiamy bazy.
    const html = absolutizeAssetUrls('<html><body><a href="#note-1">1</a></body></html>')

    expect(html).toContain('href="#note-1"')
  })

  it('rewrites the namespaced xlink:href on an EPUB 2 cover image', () => {
    // EPUB 2 embeds its cover as SVG's <image xlink:href="…">. The qualified
    // name is "xlink:href", not "href" - a selector or getAttribute keyed on
    // the plain name misses it and the cover stays relative to the front's
    // own origin (blank white iframe).
    const html = absolutizeAssetUrls(
      '<html><body><svg><image xlink:href="/api/projects/p/assets/cover.jpeg?t=x"></image></svg></body></html>',
    )
    const image = new DOMParser().parseFromString(html, 'text/html').querySelector('image')

    expect(image?.getAttributeNS('http://www.w3.org/1999/xlink', 'href')).toBe(
      `${API}/api/projects/p/assets/cover.jpeg?t=x`,
    )
  })

  it('leaves a detached book link alone', () => {
    const html = absolutizeAssetUrls('<html><body><a data-epub-href="ch2.xhtml">x</a></body></html>')
    // Sprawdzamy atrybut wprost, a nie podciag w html: "data-epub-href=..."
    // zawiera podciag 'href="ch2.xhtml"' jako fragment wlasnej nazwy, wiec
    // toContain zawsze by tu przeszedl - niezaleznie od implementacji.
    const anchor = new DOMParser().parseFromString(html, 'text/html').querySelector('a')

    expect(anchor?.getAttribute('data-epub-href')).toBe('ch2.xhtml')
    expect(anchor?.hasAttribute('href')).toBe(false)
  })
})

describe('loadChapterPreview', () => {
  beforeEach(() => {
    setAccessToken('token')
  })

  it('fetches the document with the token', async () => {
    const captured: { authorization: string | null } = { authorization: null }

    server.use(
      http.get(`${API}/api/projects/p/preview/ch-1`, ({ request }) => {
        captured.authorization = request.headers.get('Authorization')

        return HttpResponse.text('<html><body><p data-segment-id="seg-1">A.</p></body></html>')
      }),
    )

    const html = await loadChapterPreview('p', 'ch-1')

    expect(captured.authorization).toBe('Bearer token')
    expect(html).toContain('data-segment-id="seg-1"')
  })
})
