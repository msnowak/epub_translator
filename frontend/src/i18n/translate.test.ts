import { describe, expect, it } from 'vitest'
import { translate } from './translate'

describe('translate', () => {
  it('returns the message for a plain key', () => {
    expect(translate('pl', 'auth.login.heading')).toBe('Zaloguj się')
    expect(translate('en', 'auth.login.heading')).toBe('Sign in')
  })

  it('substitutes named placeholders', () => {
    expect(translate('en', 'chapters.numbered', { number: 4 })).toBe('Chapter 4')
  })

  it('leaves an unknown placeholder verbatim rather than throwing', () => {
    // Katalog i wywolanie rozjezdzaja sie tylko przy blednej edycji - test
    // parzystosci to lapie, a ekran ma w tym czasie dzialac.
    expect(translate('en', 'chapters.numbered')).toBe('Chapter {number}')
  })

  it('picks the Polish plural category from the count', () => {
    const key = 'workerError.model_invalid_translation'

    expect(translate('pl', key, { count: 1 })).toContain('1 próba')
    expect(translate('pl', key, { count: 3 })).toContain('3 próby')
    expect(translate('pl', key, { count: 5 })).toContain('5 prób')
    expect(translate('pl', key, { count: 0 })).toContain('0 prób')
  })

  it('picks the English plural category from the count', () => {
    const key = 'workerError.model_invalid_translation'

    expect(translate('en', key, { count: 1 })).toContain('1 attempt)')
    expect(translate('en', key, { count: 3 })).toContain('3 attempts)')
  })
})
