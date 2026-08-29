import { describe, expect, it } from 'vitest'
import { CATALOGS } from './catalogs'
import { LOCALES, type Locale } from './locales'
import { pl } from './pl'

const KEYS = Object.keys(pl) as (keyof typeof pl)[]

function placeholders(template: string): string[] {
  return [...template.matchAll(/\{(\w+)\}/g)].map((match) => match[1]).sort()
}

function forms(entry: string | Record<string, string>): string[] {
  return 'string' === typeof entry ? [entry] : Object.values(entry)
}

describe('catalog parity', () => {
  it.each(LOCALES)('%s has exactly the Polish key set', (locale: Locale) => {
    expect(Object.keys(CATALOGS[locale]).sort()).toEqual([...KEYS].sort())
  })

  it.each(LOCALES)('%s uses the same placeholders as Polish', (locale: Locale) => {
    for (const key of KEYS) {
      const expected = [...new Set(placeholders(forms(pl[key]).join(' ')))].sort()

      for (const form of forms(CATALOGS[locale][key])) {
        const actual = [...new Set(placeholders(form))].sort()

        // Rownosc w obie strony, nie zawieranie: placeholder nadmiarowy
        // zostalby na ekranie doslownie, a zgubiony po cichu wycialby dane
        // ze zdania - i wlasnie ten drugi przypadek jest tym, ktorego test
        // ma pilnowac.
        expect({ key, locale, placeholders: actual }).toEqual({
          key,
          locale,
          placeholders: expected,
        })
      }
    }
  })

  it.each(LOCALES)('%s covers exactly its own plural categories', (locale: Locale) => {
    const categories = new Intl.PluralRules(locale).resolvedOptions().pluralCategories.sort()

    for (const key of KEYS) {
      const entry = CATALOGS[locale][key]

      if ('string' === typeof entry) {
        continue
      }

      expect({ key, forms: Object.keys(entry).sort() }).toEqual({ key, forms: categories })
    }
  })
})
