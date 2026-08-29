import { en } from './en'
import type { Locale } from './locales'
import type { Messages } from './messages'
import { pl } from './pl'

// Record<Locale, Messages> znaczy, ze dopisanie jezyka do LOCALES nie
// skompiluje sie, dopoki nie trafi rowniez tutaj i do LOCALE_NAMES.
export const CATALOGS: Record<Locale, Messages> = { pl, en }
