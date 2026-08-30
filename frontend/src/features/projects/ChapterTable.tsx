import { Link } from 'react-router-dom'
import type { Chapter } from '../../api/types'
import { useT } from '../../i18n/useT'
import { chapterLabel } from './chapterLabel'

export function ChapterTable({ chapters, projectId }: { chapters: Chapter[]; projectId: string }) {
  const { t } = useT()

  if (0 === chapters.length) {
    return <p className="text-neutral-600">{t('chapters.empty')}</p>
  }

  return (
    <table className="w-full text-left text-sm">
      <thead>
        <tr className="border-b">
          <th className="py-2">{t('chapters.column.chapter')}</th>
          <th className="py-2">{t('chapters.column.translated')}</th>
          <th className="py-2">{t('chapters.column.failed')}</th>
        </tr>
      </thead>
      <tbody>
        {chapters.map((chapter) => {
          const { translated = 0, edited = 0, failed = 0 } = chapter.segmentCounts

          return (
            <tr key={chapter.id} className={failed > 0 ? 'border-b bg-red-50' : 'border-b'}>
              <td className="py-2">
                <Link className="underline" to={`/projects/${projectId}/chapters/${chapter.id}`}>
                  {chapterLabel(chapter, t)}
                </Link>
              </td>
              <td className="py-2">
                {t('chapters.translatedOf', { done: translated + edited, total: chapter.totalSegments })}
              </td>
              <td className="py-2">
                {failed > 0 ? t('chapters.failedCount', { count: failed }) : '—'}
              </td>
            </tr>
          )
        })}
      </tbody>
    </table>
  )
}
