import { describe, expect, it } from 'vitest'
import { detokenize, tokenSignature } from './detokenize'

describe('detokenize', () => {
  it('puts the markup back where the tokens were', () => {
    expect(detokenize('To jest [1]bardzo[/1] ważne.', { '1': '<em>' })).toBe('To jest <em>bardzo</em> ważne.')
  })

  it('nests the way the source nested', () => {
    expect(detokenize('[1]a[2]b[/2]c[/1]', { '1': '<em>', '2': '<strong>' })).toBe('<em>a<strong>b</strong>c</em>')
  })

  it('closes with the tag name, not with the whole opening markup', () => {
    expect(detokenize('[1]x[/1]', { '1': '<a data-epub-href="ch2.xhtml">' })).toBe(
      '<a data-epub-href="ch2.xhtml">x</a>',
    )
  })

  it('writes a void element once', () => {
    expect(detokenize('Wiersz[1/]drugi', { '1': '<br/>' })).toBe('Wiersz<br/>drugi')
  })

  it('leaves an unknown token in the text', () => {
    // Ten sam wybor co w InlineTokenizer: lepiej zostawic slad niz po cichu
    // zjesc fragment tresci.
    expect(detokenize('a [7]b[/7]', {})).toBe('a [7]b[/7]')
  })

  it('escapes text the way htmlspecialchars(ENT_NOQUOTES | ENT_XML1) does', () => {
    expect(detokenize('a < b & c > d "e"', {})).toBe('a &lt; b &amp; c &gt; d "e"')
  })
})

describe('tokenSignature', () => {
  it('matches when the same tokens appear in another order', () => {
    expect(tokenSignature('[1]a[/1][2]b[/2]')).toBe(tokenSignature('[2]b[/2][1]a[/1]'))
  })

  it('ignores the text around the tokens', () => {
    expect(tokenSignature('Source [1]here[/1].')).toBe(tokenSignature('Tłumaczenie [1]tutaj[/1]!'))
  })

  it('differs when a token number is missing entirely', () => {
    expect(tokenSignature('[1]a[/1][2]b[/2]')).not.toBe(tokenSignature('[1]a[/1]'))
  })

  it('differs when a token changes kind between void and paired', () => {
    expect(tokenSignature('[1]a[/1]')).not.toBe(tokenSignature('a[1/]'))
  })

  it('matches when one token number repeats a different number of times, like TranslationValidator::tokenKinds()', () => {
    // Backend przypadek z przegladu stage 7: TranslationValidator klucz'uje
    // mape po numerze zetonu, nie po liczbie wystapien, wiec ten sam numer
    // moze powtorzyc sie inna ilosc razy niz w zrodle i backend to
    // zaakceptuje. Przewodnik po stronie przegladarki ma dawac ten sam wynik,
    // zamiast blokowac zapis, ktorego backend by przyjal.
    expect(tokenSignature('The [1]big red[/1] house')).toBe(tokenSignature('[1]Duży[/1] czerwony [1]dom[/1]'))
  })

  it('does not catch an unclosed token - that is assertWellNested(), left to the backend', () => {
    // "[1]a" niesie sam otwierajacy zeton, bez zamykajacego - assertWellNested()
    // po stronie backendu i tak to odrzuci (stos nigdy sie nie domyka), wiec
    // przewodnik nie musi sam wykrywac zle zagniezdzenia; ma tylko nie byc
    // bardziej restrykcyjny niz sama integralnosc zetonow.
    expect(tokenSignature('[1]a[/1]')).toBe(tokenSignature('[1]a'))
  })
})
