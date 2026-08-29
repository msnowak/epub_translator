import { createContext, useCallback, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { setActiveLocale } from './activeLocale'
import type { Locale } from './locales'
import type { Translate } from './messages'
import { readStoredLocale, writeStoredLocale } from './storage'
import { translate } from './translate'

export interface LocaleContextValue {
  t: Translate
  locale: Locale
  setLocale: (next: Locale) => void
}

export const LocaleContext = createContext<LocaleContextValue | null>(null)

export function LocaleProvider({ children, initial }: { children: ReactNode; initial?: Locale }) {
  const [locale, setLocaleState] = useState<Locale>(() => initial ?? readStoredLocale())

  // Modul i atrybut lang musza znac jezyk juz przy pierwszym renderze, nie
  // dopiero po nim - inaczej pierwsze zadanie wyszloby ze starym naglowkiem.
  setActiveLocale(locale)

  useEffect(() => {
    document.documentElement.lang = locale
  }, [locale])

  const setLocale = useCallback((next: Locale) => {
    setActiveLocale(next)
    writeStoredLocale(next)
    setLocaleState(next)
  }, [])

  const value = useMemo<LocaleContextValue>(
    () => ({
      locale,
      setLocale,
      t: ((key: never, params: never) => translate(locale, key, params)) as Translate,
    }),
    [locale, setLocale],
  )

  return <LocaleContext.Provider value={value}>{children}</LocaleContext.Provider>
}
