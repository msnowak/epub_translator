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
})
