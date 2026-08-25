import { Link } from 'react-router-dom'
import type { Chapter } from '../../api/types'
import { chapterLabel } from '../editor/ChapterNav'

export function ChapterTable({ chapters, projectId }: { chapters: Chapter[]; projectId: string }) {
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
              <td className="py-2">
                <Link className="underline" to={`/projekty/${projectId}/rozdzialy/${chapter.id}`}>
                  {chapterLabel(chapter)}
                </Link>
              </td>
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
