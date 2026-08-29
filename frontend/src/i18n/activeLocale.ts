import { DEFAULT_LOCALE, type Locale } from './locales'
import type { Translate } from './messages'
import { translate } from './translate'

let active: Locale = DEFAULT_LOCALE

/** Pisze wylacznie LocaleProvider - stad brak drugiego zrodla prawdy. */
export function setActiveLocale(locale: Locale): void {
  active = locale
}

export function getActiveLocale(): Locale {
  return active
}

/** Dla modulow poza drzewem Reacta: api/client.ts, api/problem.ts. */
export const tActive = ((key: never, params: never) =>
  translate(active, key, params)) as Translate
