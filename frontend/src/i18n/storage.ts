import { DEFAULT_LOCALE, isLocale, type Locale } from './locales'

const STORAGE_KEY = 'epubTranslator.locale'

/** Zapisany wybor, potem jezyk przegladarki, na koncu polski. */
export function readStoredLocale(): Locale {
  // localStorage rzuca w trybie prywatnym i przy zablokowanych danych witryny,
  // wiec kazdy odczyt i zapis musi to przezyc.
  try {
    const saved = window.localStorage.getItem(STORAGE_KEY)

    if (isLocale(saved)) {
      return saved
    }
  } catch {
    // Brak dostepu do magazynu nie moze przewrocic aplikacji.
  }

  for (const candidate of navigator.languages ?? []) {
    const base = candidate.split('-')[0]

    if (isLocale(base)) {
      return base
    }
  }

  return DEFAULT_LOCALE
}

export function writeStoredLocale(locale: Locale): void {
  try {
    window.localStorage.setItem(STORAGE_KEY, locale)
  } catch {
    // Wybor przetrwa wtedy tylko do przeladowania - lepsze to niz wyjatek.
  }
}
