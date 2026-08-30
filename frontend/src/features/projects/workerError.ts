import type { MessageKey, Params, Translate } from '../../i18n/messages'

const KEYS: Record<string, MessageKey> = {
  epub_unreadable: 'workerError.epub_unreadable',
  ollama_unreachable_project: 'workerError.ollama_unreachable_project',
  ollama_unreachable_segment: 'workerError.ollama_unreachable_segment',
  model_invalid_translation: 'workerError.model_invalid_translation',
}

export function workerErrorMessage(
  code: string | null,
  params: Params | null,
  t: Translate,
): string | null {
  if (null === code) {
    return null
  }

  const key = KEYS[code]

  // Nieznany kod znaczy, ze backend wyprzedzil frontend - pusto jest lepsze
  // niz surowy identyfikator na ekranie uzytkownika.
  if (undefined === key) {
    return null
  }

  // "attempts" jest nazwa parametru po stronie backendu, "count" steruje
  // liczba mnoga po stronie katalogu.
  const attempts = params?.attempts

  return t(key as never, { ...params, count: 'number' === typeof attempts ? attempts : 0 } as never)
}
