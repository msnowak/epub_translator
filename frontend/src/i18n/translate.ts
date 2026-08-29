import { CATALOGS } from './catalogs'
import type { Locale } from './locales'
import type { MessageKey, Params, PluralForms, PluralKey, SimpleKey } from './messages'

export function translate(locale: Locale, key: SimpleKey, params?: Params): string
export function translate(locale: Locale, key: PluralKey, params: Params & { count: number }): string
export function translate(locale: Locale, key: MessageKey, params: Params = {}): string {
  const entry = CATALOGS[locale][key]
  const template = 'string' === typeof entry ? entry : selectPlural(locale, entry, params)

  return interpolate(template, params)
}

function selectPlural(locale: Locale, forms: PluralForms, params: Params): string {
  const count = params.count

  // Klucz mnogi bez "count" nie przechodzi przez typy, wiec tu bronimy sie
  // tylko przed danymi spoza TypeScriptu (np. odpowiedzia serwera).
  if ('number' !== typeof count) {
    return forms.other
  }

  const category = new Intl.PluralRules(locale).select(count)

  return forms[category] ?? forms.other
}

function interpolate(template: string, params: Params): string {
  // Nieznany placeholder zostaje doslownie - ekran ma dzialac, a rozjazd
  // katalogow lapie test parzystosci.
  return template.replace(/\{(\w+)\}/g, (whole, name: string) =>
    name in params ? String(params[name]) : whole,
  )
}
