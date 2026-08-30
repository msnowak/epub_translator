import { describe, expect, it } from 'vitest'
import { translate } from '../../i18n/translate'
import { pl as plCatalog } from '../../i18n/pl'
import type { Translate } from '../../i18n/messages'
import { KEYS, workerErrorMessage } from './workerError'

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

  // Ten test istnieje, bo druga polowa tego kontraktu mieszka po drugiej
  // stronie sieci: backend/src/Entity/WorkerError.php to enum z tymi samymi
  // czterema stringami jako backing values, wpisanymi niezaleznie od tej
  // mapy. Zmiana wartosci tam albo literalu tutaj nie wywoluje zadnego
  // bledu typow - kod po prostu przestaje byc rozpoznawany i uzytkownik
  // dostaje puste miejsce zamiast komunikatu. Ten test pilnuje dokladnego
  // zbioru kluczy KEYS (dodanie piatego tez ma go wywalic, zeby ktos
  // zajrzal do pliku WorkerError.php po drugiej stronie) oraz tego, ze
  // kazdy z nich wskazuje na klucz, ktory naprawde istnieje w katalogu.
  it('pins the KEYS map to the backend contract', () => {
    expect(Object.keys(KEYS).sort()).toEqual([
      'epub_unreadable',
      'model_invalid_translation',
      'ollama_unreachable_project',
      'ollama_unreachable_segment',
    ])

    for (const key of Object.values(KEYS)) {
      expect(key in plCatalog).toBe(true)
    }
  })
})
