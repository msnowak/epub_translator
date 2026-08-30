import { describe, expect, it } from 'vitest'
import { translate } from '../../i18n/translate'
import type { Translate } from '../../i18n/messages'
import { workerErrorMessage } from './workerError'

const pl = ((key: never, params: never) => translate('pl', key, params)) as Translate

describe('workerErrorMessage', () => {
  it('renders a code with no parameters', () => {
    expect(workerErrorMessage('epub_unreadable', null, pl)).toContain('struktury pliku EPUB')
  })

  it('declines the attempt count', () => {
    // Polskie kategorie liczby mnogiej: 2-4 to "few", 5-21 to "many" - te
    // wartosci sprawdzaja realna roznice w koncowce, nie tylko obecnosc liczby.
    expect(workerErrorMessage('model_invalid_translation', { attempts: 3 }, pl)).toContain('3 próby')
    expect(workerErrorMessage('model_invalid_translation', { attempts: 5 }, pl)).toContain('5 prób')
  })

  it('returns null when there is no error', () => {
    expect(workerErrorMessage(null, null, pl)).toBeNull()
  })

  it('returns null for a code this frontend does not know', () => {
    // Backend moze dodac kod wczesniej niz front - lepiej nie pokazac nic
    // niz surowy identyfikator.
    expect(workerErrorMessage('something_new', null, pl)).toBeNull()
  })
})
