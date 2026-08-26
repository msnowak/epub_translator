import { describe, expect, it } from 'vitest'
import { applyTranslation, readSegmentId } from './preview'

function documentWith(body: string): Document {
  return new DOMParser().parseFromString(`<html><body>${body}</body></html>`, 'text/html')
}

describe('applyTranslation', () => {
  it('replaces only the addressed block', () => {
    const document = documentWith('<p data-segment-id="a">Stare.</p><p data-segment-id="b">Inne.</p>')

    expect(applyTranslation(document, 'a', 'Nowe <em>i lepsze</em>.')).toBe(true)
    expect(document.querySelector('[data-segment-id="a"]')?.innerHTML).toBe('Nowe <em>i lepsze</em>.')
    expect(document.querySelector('[data-segment-id="b"]')?.innerHTML).toBe('Inne.')
  })

  it('says so when the block is not in this chapter', () => {
    expect(applyTranslation(documentWith('<p>x</p>'), 'a', 'Nowe.')).toBe(false)
  })
})

describe('readSegmentId', () => {
  it('finds the block a click landed inside', () => {
    const document = documentWith('<p data-segment-id="a">Tekst z <em id="inner">emfazą</em>.</p>')

    expect(readSegmentId(document.querySelector('#inner'))).toBe('a')
  })

  it('answers null outside any block', () => {
    expect(readSegmentId(documentWith('<p>x</p>').querySelector('p'))).toBeNull()
  })

  it('finds the block for a node from another realm, like the preview iframe', () => {
    // Budowanie dokumentu przez DOMParser (jak documentWith powyzej) zostaje
    // w tym samym realmie co kod testu, wiec "instanceof Element" tam dziala
    // i niczego by nie wykrylo. Prawdziwa granica realmow to osobny iframe:
    // jego contentDocument ma wlasny konstruktor Element, rozny od tego z
    // realmu rodzica - dokladnie sytuacja z przegladarki.
    const frame = window.document.createElement('iframe')
    window.document.body.append(frame)

    try {
      const inner = frame.contentDocument

      if (null === inner) {
        throw new Error('brak contentDocument ramki w tym srodowisku testowym')
      }

      inner.body.innerHTML = '<p data-segment-id="a">Tekst z <em id="inner">emfazą</em>.</p>'
      const target = inner.querySelector('#inner')

      // Dowod, ze granica realmow jest tu prawdziwa: gdyby target byl
      // instancja Element z realmu rodzica, stara implementacja tez by
      // przeszla i test niczego by nie dowodzil.
      expect(target instanceof Element).toBe(false)

      expect(readSegmentId(target)).toBe('a')
    } finally {
      frame.remove()
    }
  })
})
