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

  it('differs when a token is missing', () => {
    expect(tokenSignature('[1]a[/1]')).not.toBe(tokenSignature('[1]a'))
  })

  it('ignores the text around the tokens', () => {
    expect(tokenSignature('Source [1]here[/1].')).toBe(tokenSignature('Tłumaczenie [1]tutaj[/1]!'))
  })
})
