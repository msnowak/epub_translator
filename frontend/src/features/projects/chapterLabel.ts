import type { Translate } from '../../i18n/messages'

export function chapterLabel(
  chapter: { spineOrder: number; title: string | null },
  t: Translate,
): string {
  // Rozdzial bez tytulu w OPF-ie i tak musi dac sie wskazac, stad numer
  // z kolejnosci w spine.
  return chapter.title ?? t('chapters.numbered', { number: chapter.spineOrder + 1 })
}
