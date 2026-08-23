import type { Chapter } from '../../api/types'

function chapterLabel(chapter: Chapter): string {
  // Rozdzial bez tytulu w OPF-ie i tak musi dac sie wskazac, stad numer
  // z kolejnosci w spine.
  return chapter.title ?? `Rozdział ${chapter.spineOrder + 1}`
}

export function ChapterTable({ chapters }: { chapters: Chapter[] }) {
  if (0 === chapters.length) {
    return <p className="text-neutral-600">Rozdziały pojawią się, gdy plik zostanie przeanalizowany.</p>
  }

  return (
    <table className="w-full text-left text-sm">
      <thead>
        <tr className="border-b">
          <th className="py-2">Rozdział</th>
          <th className="py-2">Przetłumaczone</th>
          <th className="py-2">Nieudane</th>
        </tr>
      </thead>
      <tbody>
        {chapters.map((chapter) => {
          const { translated = 0, edited = 0, failed = 0 } = chapter.segmentCounts

          return (
            <tr key={chapter.id} className={failed > 0 ? 'border-b bg-red-50' : 'border-b'}>
              <td className="py-2">{chapterLabel(chapter)}</td>
              <td className="py-2">
                {translated + edited} z {chapter.totalSegments}
              </td>
              <td className="py-2">{failed > 0 ? `${failed} nieudane` : '—'}</td>
            </tr>
          )
        })}
      </tbody>
    </table>
  )
}
