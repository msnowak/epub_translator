export const LOCALES = ['pl', 'en'] as const

export type Locale = (typeof LOCALES)[number]

// Endonimy - czytelnik szukajacy swojego jezyka rozpoznaje go zapisanego
// wlasnie w tym jezyku, nie w cudzym.
export const LOCALE_NAMES: Record<Locale, string> = {
  pl: 'Polski',
  en: 'English',
}

export const DEFAULT_LOCALE: Locale = 'pl'

export function isLocale(value: unknown): value is Locale {
  return LOCALES.includes(value as Locale)
}
