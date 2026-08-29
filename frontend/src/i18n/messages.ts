import type { pl } from './pl'

/**
 * Kategorie liczby mnogiej zaleza od jezyka - polski uzywa one/few/many/other,
 * angielski one/other - wiec typ wymusza tylko "other". Kompletu pilnuje test
 * parzystosci katalogow, ktory czyta je z Intl.PluralRules.
 */
export type PluralForms = Partial<Record<Intl.LDMLPluralRule, string>> & { other: string }

export type MessageKey = keyof typeof pl

export type PluralKey = {
  [K in MessageKey]: (typeof pl)[K] extends string ? never : K
}[MessageKey]

export type SimpleKey = Exclude<MessageKey, PluralKey>

export type Params = Record<string, string | number>

/**
 * Katalog polski wyznacza zbior kluczy; kazdy inny jezyk musi go spelnic co do
 * klucza, wiec brak wpisu to blad kompilacji, nie cichy fallback w runtime.
 */
export type Messages = {
  [K in MessageKey]: (typeof pl)[K] extends string ? string : PluralForms
}

/** Wywolanie klucza mnogiego bez "count" ma sie nie kompilowac. */
export interface Translate {
  (key: SimpleKey, params?: Params): string
  (key: PluralKey, params: Params & { count: number }): string
}
