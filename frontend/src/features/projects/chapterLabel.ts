export function chapterLabel(chapter: { spineOrder: number; title: string | null }): string {
  // Rozdzial bez tytulu w OPF-ie i tak musi dac sie wskazac, stad numer
  // z kolejnosci w spine.
  return chapter.title ?? `Rozdział ${chapter.spineOrder + 1}`
}
